<?php

namespace Tests\Feature\Pessoas;

use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\ProfessorMatricula;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorFuncaoTurma;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\PessoaEquipeGestoraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PessoaEquipeGestoraServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_matricula_da_pessoa_preserva_alias_legado_e_regras_de_turno(): void
    {
        $pessoa = Servidor::query()->create(['nome' => 'Pessoa Matrícula', 'status' => 'ativo']);

        $matricula = $pessoa->matriculas()->create([
            'matricula' => 'MAT-100',
            'turno' => 'integral',
        ]);

        $this->assertInstanceOf(PessoaMatricula::class, $matricula);
        $this->assertInstanceOf(PessoaMatricula::class, ProfessorMatricula::query()->findOrFail($matricula->id));
        $this->assertSame('Integral', $matricula->turnoLabel());
        $this->assertFalse(PessoaMatricula::podeAdicionarMatricula([
            ['matricula' => 'MAT-100', 'turno' => 'integral'],
        ]));
    }

    public function test_cria_diretor_e_coordenador_sem_principalizacao(): void
    {
        $escola = $this->criarEscola('Escola Gestão');
        $turma = $this->criarTurma($escola, 'A');

        $pessoa = app(PessoaEquipeGestoraService::class)->criarPessoaEquipeGestora([
            'nome' => 'Gestora Completa',
            'status' => 'ativo',
        ], $this->dadosGestao($escola, [$turma->id], diretor: true));

        $this->assertCount(2, $pessoa->matriculas);
        $this->assertSame($escola->id, $pessoa->id_escola);

        $direcao = $this->vinculoPorTipo($pessoa, FuncaoAdministrativa::TIPO_DIRECAO);
        $coordenacao = $this->vinculoPorTipo($pessoa, FuncaoAdministrativa::TIPO_COORDENACAO);

        $this->assertFalse($direcao->principal);
        $this->assertSame('PORT-ABC/2026', $direcao->portaria);
        $this->assertSame($direcao->portaria, $coordenacao->portaria);
        $this->assertSame(now()->toDateString(), $direcao->data_inicio->toDateString());

        $this->assertDatabaseHas('servidor_funcao_turma', [
            'servidor_funcao_administrativa_id' => $coordenacao->id,
            'turma_id' => $turma->id,
            'principal' => false,
            'status' => ServidorFuncaoTurma::STATUS_ATIVO,
        ]);
    }

    public function test_secretario_e_exclusivo_e_coordenador_exige_turma_da_mesma_escola(): void
    {
        $escolaA = $this->criarEscola('Escola A');
        $escolaB = $this->criarEscola('Escola B');
        $turmaB = $this->criarTurma($escolaB, 'B');

        try {
            app(PessoaEquipeGestoraService::class)->criarPessoaEquipeGestora([
                'nome' => 'Cargo Inválido',
            ], [
                ...$this->dadosGestao($escolaA, [], diretor: true, coordenador: false),
                'secretario' => true,
            ]);
            $this->fail('Secretário não deveria coexistir com Diretor.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cargos', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        app(PessoaEquipeGestoraService::class)->criarPessoaEquipeGestora([
            'nome' => 'Coordenação Inválida',
        ], $this->dadosGestao($escolaA, [$turmaB->id], diretor: false));
    }

    public function test_bloqueia_gestor_quando_outro_vinculo_ativo_pertence_a_escola_diferente(): void
    {
        $escolaA = $this->criarEscola('Escola Gestora A');
        $escolaB = $this->criarEscola('Escola Vínculo B');
        $turmaA = $this->criarTurma($escolaA, 'A');
        $pessoa = Servidor::query()->create([
            'nome' => 'Pessoa em Duas Escolas',
            'status' => 'ativo',
        ]);
        $funcao = FuncaoAdministrativa::query()->create([
            'nome' => 'Auxiliar Administrativo',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => false,
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => $funcao->id,
            'matricula' => 'AUX-1',
            'id_escola' => $escolaB->id,
            'setor_id' => $escolaB->setor_id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
        ]);

        $this->expectException(ValidationException::class);
        app(PessoaEquipeGestoraService::class)->sincronizar(
            $pessoa,
            $this->dadosGestao($escolaA, [$turmaA->id], diretor: false),
        );
    }

    public function test_remover_e_readicionar_turma_preserva_periodos_historicos(): void
    {
        $escola = $this->criarEscola('Escola Histórico');
        $turmaA = $this->criarTurma($escola, 'A');
        $turmaB = $this->criarTurma($escola, 'B');
        $service = app(PessoaEquipeGestoraService::class);

        $pessoa = $service->criarPessoaEquipeGestora([
            'nome' => 'Coordenadora Histórica',
        ], $this->dadosGestao($escola, [$turmaA->id, $turmaB->id], diretor: false));

        $service->sincronizar($pessoa, $this->dadosGestao($escola, [$turmaA->id], diretor: false));
        $service->sincronizar($pessoa, $this->dadosGestao($escola, [$turmaA->id, $turmaB->id], diretor: false));

        $coordenacao = $this->vinculoPorTipo($pessoa->fresh(), FuncaoAdministrativa::TIPO_COORDENACAO);
        $periodosTurmaB = ServidorFuncaoTurma::query()
            ->where('servidor_funcao_administrativa_id', $coordenacao->id)
            ->where('turma_id', $turmaB->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $periodosTurmaB);
        $this->assertSame(ServidorFuncaoTurma::STATUS_INATIVO, $periodosTurmaB->first()->status);
        $this->assertNotNull($periodosTurmaB->first()->data_fim);
        $this->assertSame(ServidorFuncaoTurma::STATUS_ATIVO, $periodosTurmaB->last()->status);
    }

    public function test_troca_de_portaria_encerra_e_recria_direcao_e_coordenacao(): void
    {
        $escola = $this->criarEscola('Escola Portaria');
        $turma = $this->criarTurma($escola, 'A');
        $service = app(PessoaEquipeGestoraService::class);
        $pessoa = $service->criarPessoaEquipeGestora([
            'nome' => 'Gestora Portaria',
        ], $this->dadosGestao($escola, [$turma->id], diretor: true));

        $novosDados = $this->dadosGestao($escola, [$turma->id], diretor: true);
        $novosDados['portaria'] = 'PORT-NOVA/2026';

        $service->sincronizar($pessoa, $novosDados);

        $vinculos = ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
            ->get();

        $this->assertCount(4, $vinculos);
        $this->assertCount(2, $vinculos->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO));
        $this->assertCount(2, $vinculos->where('status', ServidorFuncaoAdministrativa::STATUS_INATIVO));
        $this->assertTrue($vinculos->where('status', 'ativo')->every(
            fn (ServidorFuncaoAdministrativa $vinculo): bool => $vinculo->portaria === 'PORT-NOVA/2026'
        ));
        $this->assertTrue($vinculos->where('status', 'inativo')->every(
            fn (ServidorFuncaoAdministrativa $vinculo): bool => $vinculo->data_fim?->toDateString() === now()->subDay()->toDateString()
        ));
        $this->assertTrue($vinculos->where('status', 'ativo')->every(
            fn (ServidorFuncaoAdministrativa $vinculo): bool => $vinculo->data_inicio?->toDateString() === now()->toDateString()
        ));
    }

    public function test_troca_de_escola_exige_nova_vigencia_e_nao_sobrepoe_periodos(): void
    {
        $escolaA = $this->criarEscola('Escola Origem Vigência');
        $escolaB = $this->criarEscola('Escola Destino Vigência');
        $turmaA = $this->criarTurma($escolaA, 'A');
        $turmaB = $this->criarTurma($escolaB, 'B');
        $service = app(PessoaEquipeGestoraService::class);
        $pessoa = $service->criarPessoaEquipeGestora([
            'nome' => 'Gestora Transferida',
        ], $this->dadosGestao($escolaA, [$turmaA->id], diretor: true));

        $novosDados = $this->dadosGestao($escolaB, [$turmaB->id], diretor: true);
        $atualizada = $service->sincronizar($pessoa, $novosDados);
        $vinculos = ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
            ->get();

        $this->assertSame($escolaB->id, $atualizada->id_escola);
        $this->assertTrue($vinculos->where('id_escola', $escolaA->id)->every(
            fn (ServidorFuncaoAdministrativa $vinculo): bool =>
                $vinculo->status === ServidorFuncaoAdministrativa::STATUS_INATIVO
                && $vinculo->data_fim?->toDateString() === now()->subDay()->toDateString()
        ));
        $this->assertTrue($vinculos->where('id_escola', $escolaB->id)->every(
            fn (ServidorFuncaoAdministrativa $vinculo): bool =>
                $vinculo->status === ServidorFuncaoAdministrativa::STATUS_ATIVO
                && $vinculo->data_inicio?->toDateString() === now()->toDateString()
        ));
    }

    public function test_inativar_pessoa_encerra_gestao_turmas_e_revoga_role(): void
    {
        $escola = $this->criarEscola('Escola Inativação');
        $turma = $this->criarTurma($escola, 'A');
        $roleEquipe = Role::findOrCreate('Equipe Gestora', 'web');
        $service = app(PessoaEquipeGestoraService::class);
        $pessoa = $service->criarPessoaEquipeGestora([
            'nome' => 'Gestora Inativada',
            'email' => 'gestora.inativada@edu.umuarama.pr.gov.br',
        ], $this->dadosGestao($escola, [$turma->id], diretor: true));

        $this->assertTrue($pessoa->user->hasRole($roleEquipe));

        $inativa = $service->atualizarPessoaEquipeGestora($pessoa, [
            'status' => Servidor::STATUS_INATIVO,
        ], []);

        $this->assertSame(Servidor::STATUS_INATIVO, $inativa->status);
        $this->assertFalse($inativa->vinculosAtivos()
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
            ->exists());
        $this->assertFalse(ServidorFuncaoTurma::query()
            ->whereHas('servidorFuncaoAdministrativa', fn ($vinculos) =>
                $vinculos->where('servidor_id', $pessoa->id))
            ->where('status', ServidorFuncaoTurma::STATUS_ATIVO)
            ->exists());
        $this->assertFalse($inativa->user->fresh()->hasRole($roleEquipe));
    }

    public function test_nova_turma_assume_principal_sem_desmarcar_existente_e_aceita_vacancia_explicita(): void
    {
        $escola = $this->criarEscola('Escola Nova Turma');
        $turmaA = $this->criarTurma($escola, 'A');
        $turmaB = $this->criarTurma($escola, 'B');
        $service = app(PessoaEquipeGestoraService::class);
        $pessoa = $service->criarPessoaEquipeGestora([
            'nome' => 'Coordenadora Novas Turmas',
        ], $this->dadosGestao($escola, [$turmaA->id], diretor: false));

        $dados = $this->dadosGestao($escola, [$turmaA->id, $turmaB->id], diretor: false);
        $dados['coordenador']['turmas_principais_ids'] = [$turmaA->id];
        $service->sincronizar($pessoa, $dados);

        $coordenacao = $this->vinculoPorTipo($pessoa->fresh(), FuncaoAdministrativa::TIPO_COORDENACAO);
        $this->assertFalse($coordenacao->vinculosTurmaAtivos()->where('turma_id', $turmaA->id)->firstOrFail()->principal);
        $this->assertFalse($coordenacao->vinculosTurmaAtivos()->where('turma_id', $turmaB->id)->firstOrFail()->principal);

        $dados['coordenador']['turmas_vacancia_ids'] = [$turmaB->id];
        $service->sincronizar($pessoa, $dados);

        $coordenacao->refresh();
        $this->assertFalse($coordenacao->vinculosTurmaAtivos()->where('turma_id', $turmaA->id)->firstOrFail()->principal);
        $this->assertFalse($coordenacao->vinculosTurmaAtivos()->where('turma_id', $turmaB->id)->firstOrFail()->principal);

        unset($dados['coordenador']['turmas_vacancia_ids']);
        $service->sincronizar($pessoa, $dados);

        $this->assertFalse($coordenacao->vinculosTurmaAtivos()->where('turma_id', $turmaB->id)->firstOrFail()->principal);
    }

    public function test_normalizacao_encerra_vinculo_gestor_legado_duplicado_do_mesmo_tipo(): void
    {
        $escola = $this->criarEscola('Escola Função Legada');
        $turma = $this->criarTurma($escola, 'A');
        $service = app(PessoaEquipeGestoraService::class);
        $pessoa = $service->criarPessoaEquipeGestora([
            'nome' => 'Coordenadora com Duplicidade',
        ], $this->dadosGestao($escola, [$turma->id], diretor: false));
        $funcaoLegada = FuncaoAdministrativa::query()->create([
            'codigo' => 'coordenacao-legada-teste',
            'nome' => 'Coordenação Legada Teste',
            'categoria' => FuncaoAdministrativa::CATEGORIA_PEDAGOGICO,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => true,
            'coordenacao_pedagogica' => true,
        ]);
        $duplicado = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => $funcaoLegada->id,
            'id_escola' => $escola->id,
            'setor_id' => $escola->setor_id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'legado',
            'portaria' => 'PORT-ABC/2026',
            'data_inicio' => '2026-07-01',
        ]);

        $service->sincronizar($pessoa, $this->dadosGestao($escola, [$turma->id], diretor: false));

        $ativosCoordenacao = ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->coordenacao())
            ->get();

        $this->assertCount(1, $ativosCoordenacao);
        $this->assertSame(ServidorFuncaoAdministrativa::STATUS_INATIVO, $duplicado->fresh()->status);
    }

    public function test_substituicao_explicita_dos_principais_e_transacional(): void
    {
        $escola = $this->criarEscola('Escola Principais');
        $turma = $this->criarTurma($escola, 'A');
        $service = app(PessoaEquipeGestoraService::class);

        $primeira = $service->criarPessoaEquipeGestora([
            'nome' => 'Primeira Gestora',
        ], $this->dadosGestao($escola, [$turma->id], diretor: true));
        $segunda = $service->criarPessoaEquipeGestora([
            'nome' => 'Segunda Gestora',
        ], [
            ...$this->dadosGestao($escola, [$turma->id], diretor: true),
            'diretor' => ['ativo' => true, 'principal' => true],
            'coordenador' => [
                'ativo' => true,
                'turma_ids' => [$turma->id],
                'turmas_principais_ids' => [$turma->id],
            ],
        ]);

        $this->assertFalse($this->vinculoPorTipo($primeira->fresh(), FuncaoAdministrativa::TIPO_DIRECAO)->principal);
        $this->assertFalse($this->vinculoPorTipo($segunda->fresh(), FuncaoAdministrativa::TIPO_DIRECAO)->principal);

        $coordPrimeira = $this->vinculoPorTipo($primeira->fresh(), FuncaoAdministrativa::TIPO_COORDENACAO);
        $coordSegunda = $this->vinculoPorTipo($segunda->fresh(), FuncaoAdministrativa::TIPO_COORDENACAO);
        $this->assertFalse($coordPrimeira->vinculosTurmaAtivos()->where('turma_id', $turma->id)->firstOrFail()->principal);
        $this->assertFalse($coordSegunda->vinculosTurmaAtivos()->where('turma_id', $turma->id)->firstOrFail()->principal);
    }

    public function test_conversao_de_professor_bloqueia_vinculo_pedagogico_e_preserva_historico(): void
    {
        $escola = $this->criarEscola('Escola Conversão');
        $turma = $this->criarTurma($escola, 'A');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-CONV',
            'nome' => 'Arte',
        ]);
        [$pessoa, $professor] = $this->criarProfessor($escola);

        TurmaComponenteProfessor::query()->create([
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        try {
            app(PessoaEquipeGestoraService::class)->converterProfessorParaEquipeGestora(
                $pessoa,
                $this->dadosGestao($escola, [$turma->id], diretor: false),
            );
            $this->fail('A conversão deveria ser bloqueada pelo vínculo pedagógico.');
        } catch (ValidationException $exception) {
            $this->assertTrue($professor->fresh()->ativo);
        }

        TurmaComponenteProfessor::query()->where('professor_id', $professor->id)->update([
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $dadosGestao = $this->dadosGestao($escola, [$turma->id], diretor: false);
        $dadosGestao['matriculas'] = [[
            'id' => $professor->professor_matricula_id,
            'matricula' => 'PROF-1',
            'turno' => 'manha',
        ]];
        $convertida = app(PessoaEquipeGestoraService::class)->converterProfessorParaEquipeGestora(
            $pessoa,
            $dadosGestao,
        );

        $this->assertFalse($professor->fresh()->ativo);
        $this->assertDatabaseHas('professor_matriculas', [
            'servidor_id' => $pessoa->id,
            'matricula' => 'PROF-1',
        ]);
        $this->assertNotNull($this->vinculoPorTipo($convertida, FuncaoAdministrativa::TIPO_COORDENACAO));
    }

    public function test_conversao_de_gestor_para_professor_encerra_gestao_e_reativa_lotacao(): void
    {
        $escola = $this->criarEscola('Escola Retorno');
        $turma = $this->criarTurma($escola, 'A');
        $service = app(PessoaEquipeGestoraService::class);
        $pessoa = $service->criarPessoaEquipeGestora([
            'nome' => 'Gestora que Retorna',
        ], $this->dadosGestao($escola, [$turma->id], diretor: false));

        $professora = $service->converterEquipeGestoraParaProfessor($pessoa, [], [[
            'matricula' => 'MAT-MANHA',
            'turno' => 'manha',
            'escolas' => [['id_escola' => $escola->id]],
        ]]);

        $this->assertTrue($professora->professores()->where('ativo', true)->exists());
        $this->assertFalse($professora->vinculosAtivos()
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
            ->exists());
        $this->assertTrue(ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_INATIVO)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->coordenacao())
            ->exists());
    }

    public function test_comando_migra_secretario_legado_inequivoco_sem_duplicar_pessoa_ou_vinculo(): void
    {
        $escola = $this->criarEscola('Escola Secretário Legado');
        $roleLegada = Role::findOrCreate('Secretário', 'web');
        $roleEquipe = Role::findOrCreate('Equipe Gestora', 'web');
        $usuario = User::factory()->create([
            'id_escola' => $escola->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->assignRole($roleLegada);
        $pessoa = Servidor::query()->create([
            'user_id' => $usuario->id,
            'id_escola' => $escola->id,
            'setor_id' => $escola->setor_id,
            'nome' => $usuario->name,
            'email' => $usuario->email,
            'status' => 'ativo',
        ]);
        PessoaMatricula::query()->create([
            'servidor_id' => $pessoa->id,
            'matricula' => 'SEC-001',
            'turno' => 'integral',
        ]);

        $this->artisan('equipe-gestora:sanear')->assertSuccessful();
        $this->assertTrue($usuario->fresh()->hasRole($roleLegada));
        $this->assertFalse($pessoa->vinculosAtivos()
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->secretaria())
            ->exists());

        $this->artisan('equipe-gestora:sanear', ['--aplicar' => true])->assertSuccessful();
        $this->artisan('equipe-gestora:sanear', ['--aplicar' => true])->assertSuccessful();

        $this->assertFalse($usuario->fresh()->hasRole($roleLegada));
        $this->assertTrue($usuario->fresh()->hasRole($roleEquipe));
        $this->assertSame(1, Servidor::query()->where('user_id', $usuario->id)->count());
        $this->assertSame(1, ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->secretaria())
            ->count());
    }

    public function test_saneamento_define_principal_apenas_para_vinculos_elegiveis_e_turma_da_mesma_escola(): void
    {
        $escolaA = $this->criarEscola('Escola Saneamento A');
        $escolaB = $this->criarEscola('Escola Saneamento B');
        $turmaA = $this->criarTurma($escolaA, 'A');
        $turmaB = $this->criarTurma($escolaB, 'B');
        $direcao = FuncaoAdministrativa::direcaoPadrao();
        $coordenacao = FuncaoAdministrativa::coordenacaoPadrao();

        $pessoaValida = Servidor::query()->create([
            'nome' => 'Gestora Elegível',
            'id_escola' => $escolaA->id,
            'setor_id' => $escolaA->setor_id,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $direcaoValida = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoaValida->id,
            'funcao_administrativa_id' => $direcao->id,
            'id_escola' => $escolaA->id,
            'setor_id' => $escolaA->setor_id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'portaria' => 'DIR-VALIDA/2026',
            'data_inicio' => '2026-07-01',
            'principal' => false,
        ]);
        $coordenacaoValida = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoaValida->id,
            'funcao_administrativa_id' => $coordenacao->id,
            'id_escola' => $escolaA->id,
            'setor_id' => $escolaA->setor_id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'portaria' => 'COORD-VALIDA/2026',
            'data_inicio' => '2026-07-01',
        ]);
        $turmaValida = ServidorFuncaoTurma::query()->create([
            'servidor_funcao_administrativa_id' => $coordenacaoValida->id,
            'turma_id' => $turmaA->id,
            'status' => ServidorFuncaoTurma::STATUS_ATIVO,
            'data_inicio' => '2026-07-01',
            'principal' => false,
        ]);
        $turmaOutraEscola = ServidorFuncaoTurma::query()->create([
            'servidor_funcao_administrativa_id' => $coordenacaoValida->id,
            'turma_id' => $turmaB->id,
            'status' => ServidorFuncaoTurma::STATUS_ATIVO,
            'data_inicio' => '2026-07-01',
            'principal' => false,
        ]);

        $pessoaInativa = Servidor::query()->create([
            'nome' => 'Gestora Inelegível',
            'id_escola' => $escolaA->id,
            'setor_id' => $escolaA->setor_id,
            'status' => Servidor::STATUS_INATIVO,
        ]);
        $direcaoInvalida = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoaInativa->id,
            'funcao_administrativa_id' => $direcao->id,
            'id_escola' => $escolaA->id,
            'setor_id' => $escolaA->setor_id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'portaria' => 'DIR-INATIVA/2026',
            'data_inicio' => '2026-07-01',
            'principal' => true,
        ]);
        $direcaoSemPortaria = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoaValida->id,
            'funcao_administrativa_id' => $direcao->id,
            'id_escola' => $escolaA->id,
            'setor_id' => $escolaA->setor_id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'portaria' => null,
            'data_inicio' => null,
            'principal' => true,
        ]);
        $pessoaEscolaInconsistente = Servidor::query()->create([
            'nome' => 'Gestora em Outra Escola',
            'id_escola' => $escolaB->id,
            'setor_id' => $escolaB->setor_id,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $direcaoEscolaInconsistente = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoaEscolaInconsistente->id,
            'funcao_administrativa_id' => $direcao->id,
            'id_escola' => $escolaA->id,
            'setor_id' => $escolaA->setor_id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'portaria' => 'DIR-INCONSISTENTE/2026',
            'data_inicio' => '2026-07-01',
            'principal' => true,
        ]);
        $funcaoInativa = FuncaoAdministrativa::query()->create([
            'nome' => 'Direção Inativa',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => false,
            'exige_professor' => false,
            'tem_relacao_turma' => false,
            'direcao_escolar' => true,
        ]);
        $direcaoFuncaoInativa = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoaValida->id,
            'funcao_administrativa_id' => $funcaoInativa->id,
            'id_escola' => $escolaA->id,
            'setor_id' => $escolaA->setor_id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'portaria' => 'DIR-FUNCAO-INATIVA/2026',
            'data_inicio' => '2026-07-01',
            'principal' => true,
        ]);

        $this->artisan('equipe-gestora:sanear', ['--aplicar' => true])->assertSuccessful();

        $this->assertTrue($direcaoValida->fresh()->principal);
        $this->assertFalse($direcaoInvalida->fresh()->principal);
        $this->assertFalse($direcaoSemPortaria->fresh()->principal);
        $this->assertFalse($direcaoEscolaInconsistente->fresh()->principal);
        $this->assertFalse($direcaoFuncaoInativa->fresh()->principal);
        $this->assertTrue($turmaValida->fresh()->principal);
        $this->assertFalse($turmaOutraEscola->fresh()->principal);
    }

    private function dadosGestao(
        Escola $escola,
        array $turmaIds,
        bool $diretor,
        bool $coordenador = true,
    ): array {
        return [
            'id_escola' => $escola->id,
            'matriculas' => [
                ['matricula' => 'MAT-MANHA', 'turno' => 'manha'],
                ['matricula' => 'MAT-TARDE', 'turno' => 'tarde'],
            ],
            'diretor' => $diretor,
            'coordenador' => $coordenador ? [
                'ativo' => true,
                'turma_ids' => $turmaIds,
            ] : false,
            'secretario' => false,
            'portaria' => 'PORT-ABC/2026',
            'data_inicio' => '2026-07-01',
        ];
    }

    /** @return array{Servidor, Professor} */
    private function criarProfessor(Escola $escola): array
    {
        $pessoa = Servidor::query()->create([
            'nome' => 'Professor Conversão',
            'id_escola' => $escola->id,
            'setor_id' => $escola->setor_id,
            'matricula' => 'PROF-1',
            'status' => 'ativo',
        ]);
        $matricula = PessoaMatricula::query()->create([
            'servidor_id' => $pessoa->id,
            'matricula' => 'PROF-1',
            'turno' => 'manha',
        ]);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $vinculo = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => $funcao->id,
            'matricula' => 'PROF-1',
            'id_escola' => $escola->id,
            'setor_id' => $escola->setor_id,
            'status' => 'ativo',
            'origem' => 'professor',
        ]);
        $professor = Professor::query()->create([
            'servidor_id' => $pessoa->id,
            'professor_matricula_id' => $matricula->id,
            'servidor_funcao_administrativa_id' => $vinculo->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-1',
            'turno' => 'manha',
            'nome' => $pessoa->nome,
            'ativo' => true,
        ]);

        return [$pessoa, $professor];
    }

    private function vinculoPorTipo(Servidor $pessoa, string $tipo): ServidorFuncaoAdministrativa
    {
        return ServidorFuncaoAdministrativa::query()
            ->with('funcaoAdministrativa')
            ->where('servidor_id', $pessoa->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->get()
            ->firstOrFail(fn (ServidorFuncaoAdministrativa $vinculo): bool =>
                $vinculo->funcaoAdministrativa?->tipoEquipeGestora() === $tipo
            );
    }

    private function criarEscola(string $nome): Escola
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor '.$nome,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);

        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }

    private function criarTurma(Escola $escola, string $nome): Turma
    {
        $serie = Serie::query()->create([
            'codigo' => 'SER-'.strtoupper(substr(md5($escola->id.$nome.microtime()), 0, 6)),
            'nome' => 'Série '.$nome,
        ]);

        return Turma::query()->create([
            'codigo' => 'TUR-'.strtoupper(substr(md5($escola->id.$nome.microtime()), 0, 6)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }
}
