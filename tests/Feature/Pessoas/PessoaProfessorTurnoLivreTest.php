<?php

namespace Tests\Feature\Pessoas;

use App\Livewire\Pessoas\PessoaForm;
use App\Models\ComponenteCurricular;
use App\Models\DominioEmail;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\PessoaProfessorService;
use App\Services\ServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PessoaProfessorTurnoLivreTest extends TestCase
{
    use RefreshDatabase;

    public function test_matricula_da_manha_pode_receber_turmas_da_tarde_e_integral(): void
    {
        [$escola, $componente, $turmas] = $this->cenarioPedagogico();
        $this->configurarFuncaoProfessor();

        $pessoa = app(ServidorService::class)->criarServidorComFuncoes([
            'cargo' => 'professor',
            'nome' => 'Professor Contraturno',
            'email' => 'professor.contraturno@edu.umuarama.pr.gov.br',
            'status' => Pessoa::STATUS_ATIVO,
            'carga_horaria' => 20,
            'jornada' => false,
        ], [
            'matriculas_professor' => [[
                'matricula' => 'CONTRA-001',
                'turno' => 'manha',
                'escolas' => [[
                    'id_escola' => $escola->id,
                    'vinculos_turma_componente' => [
                        ['turma_id' => $turmas['tarde']->id, 'componente_curricular_id' => $componente->id],
                        ['turma_id' => $turmas['integral']->id, 'componente_curricular_id' => $componente->id],
                    ],
                ]],
            ]],
        ]);

        $professor = $pessoa->professores()->firstOrFail();
        $this->assertSame('manha', $professor->turnoEfetivo());
        $this->assertSame(2, TurmaComponenteProfessor::query()
            ->where('professor_id', $professor->id)
            ->where('tem_professor', true)
            ->count());

        $admin = $this->criarAdmin();
        $formulario = Livewire::actingAs($admin)
            ->test(PessoaForm::class, ['pessoaId' => null]);
        $matriculaKey = (string) array_key_first($formulario->get('matriculas'));
        $formulario
            ->call('cargaHorariaAlterada', 20)
            ->call('turnoAlterado', $matriculaKey, 'manha')
            ->call('adicionarLotacao', $matriculaKey);
        $lotacaoKey = (string) array_key_first($formulario->get("matriculas.{$matriculaKey}.escolas"));

        $formulario
            ->call('escolaAlterada', $matriculaKey, $lotacaoKey, $escola->id)
            ->call('adicionarVinculo', $matriculaKey, $lotacaoKey)
            ->assertSee('Turma Manhã (Manhã)')
            ->assertSee('Turma Tarde (Tarde)')
            ->assertSee('Turma Integral (Integral)');
    }

    public function test_turma_de_outra_escola_continua_bloqueada(): void
    {
        [$escola, $componente, $turmas] = $this->cenarioPedagogico();
        $this->configurarFuncaoProfessor();
        $outraEscola = $this->criarEscola('Outra Escola', $escola->setor);
        $turmaFora = Turma::query()->create([
            'codigo' => 'FORA-01',
            'nome' => 'Turma Fora',
            'turno' => 'tarde',
            'id_serie' => $turmas['tarde']->id_serie,
            'id_escola' => $outraEscola->id,
        ]);
        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'ESCOLA-001',
            'turno' => 'manha',
            'nome' => 'Professor Escola',
            'email' => 'professor.escola@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        try {
            app(PessoaProfessorService::class)->sincronizarTurmasComponentes($professor, [[
                'turma_id' => $turmaFora->id,
                'componente_curricular_id' => $componente->id,
            ]]);
            $this->fail('Era esperado bloqueio para turma de outra escola.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'não pertence à escola',
                collect($exception->errors())->flatten()->implode(' '),
            );
        }
    }

    public function test_formulario_associa_conflitos_e_inconsistencia_aos_campos_corretos(): void
    {
        [$escola] = $this->cenarioPedagogico();
        $this->configurarFuncaoProfessor();
        DominioEmail::query()->firstOrCreate(
            ['dominio_email' => 'edu.umuarama.pr.gov.br'],
            ['status' => true],
        );
        $admin = $this->criarAdmin();

        $cpfArquivado = Servidor::query()->create([
            'nome' => 'Pessoa CPF Arquivado',
            'cpf' => '12345678901',
            'email' => 'cpf.arquivado@edu.umuarama.pr.gov.br',
            'status' => Pessoa::STATUS_INATIVO,
        ]);
        $cpfArquivado->delete();
        [$formCpf] = $this->formularioProfessorValido($admin, $escola, 'cpf.novo@edu.umuarama.pr.gov.br', 'FORM-CPF');
        $formCpf->set('cpf', '123.456.789-01')->call('salvar')->assertHasErrors(['cpf']);

        $emailArquivado = Servidor::query()->create([
            'nome' => 'Pessoa E-mail Arquivado',
            'email' => 'email.arquivado@edu.umuarama.pr.gov.br',
            'status' => Pessoa::STATUS_INATIVO,
        ]);
        $emailArquivado->delete();
        [$formEmail] = $this->formularioProfessorValido($admin, $escola, $emailArquivado->email, 'FORM-EMAIL');
        $formEmail->call('salvar')->assertHasErrors(['email']);

        $userConflito = User::factory()->create(['email' => 'conta.conflito@edu.umuarama.pr.gov.br']);
        Servidor::query()->create([
            'user_id' => $userConflito->id,
            'nome' => 'Pessoa da Conta',
            'email' => 'pessoa.da.conta@edu.umuarama.pr.gov.br',
            'status' => Pessoa::STATUS_INATIVO,
        ]);
        [$formUser] = $this->formularioProfessorValido($admin, $escola, $userConflito->email, 'FORM-USER');
        $formUser->call('salvar')->assertHasErrors(['email']);

        $outraPessoa = Servidor::query()->create([
            'nome' => 'Pessoa da Matrícula',
            'email' => 'pessoa.matricula@edu.umuarama.pr.gov.br',
            'status' => Pessoa::STATUS_INATIVO,
        ]);
        Professor::withoutEvents(fn (): Professor => Professor::query()->create([
            'servidor_id' => $outraPessoa->id,
            'id_escola' => $escola->id,
            'matricula' => 'FORM-MAT',
            'turno' => 'manha',
            'nome' => $outraPessoa->nome,
            'email' => $outraPessoa->email,
            'ativo' => false,
        ]));
        [$formMatricula, $matriculaKey] = $this->formularioProfessorValido(
            $admin,
            $escola,
            'matricula.nova@edu.umuarama.pr.gov.br',
            'FORM-MAT',
        );
        $formMatricula->call('salvar')->assertHasErrors(["matriculas.{$matriculaKey}.matricula"]);

        [$formFuncional] = $this->formularioProfessorValido(
            $admin,
            $escola,
            'funcional.invalido@edu.umuarama.pr.gov.br',
            'FORM-FUNC',
        );
        $formFuncional
            ->set('cargaHoraria', 40)
            ->set('jornada', true)
            ->call('salvar')
            ->assertHasErrors(['jornada']);
    }

    /** @return array{0:Escola,1:ComponenteCurricular,2:array<string,Turma>} */
    private function cenarioPedagogico(): array
    {
        $setor = $this->criarSetor('Setor Pedagógico');
        $escola = $this->criarEscola('Escola Contraturno', $setor);
        $serie = Serie::query()->create(['codigo' => 'SER-CONTRA', 'nome' => '1º Ano']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'MAT-CONTRA', 'nome' => 'Matemática']);
        $serie->componentesCurriculares()->attach($componente->id);

        $turmas = [];
        foreach (['manha' => 'Manhã', 'tarde' => 'Tarde', 'integral' => 'Integral'] as $turno => $label) {
            $turmas[$turno] = Turma::query()->create([
                'codigo' => 'TUR-'.$turno,
                'nome' => "Turma {$label}",
                'turno' => $turno,
                'id_serie' => $serie->id,
                'id_escola' => $escola->id,
            ]);
        }

        return [$escola, $componente, $turmas];
    }

    private function configurarFuncaoProfessor(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']);
        FuncaoAdministrativa::professorPadrao()->rolesPadrao()->sync([$role->id]);
    }

    private function criarAdmin(): User
    {
        $permissoes = collect([
            'Listar Pessoas',
            'Criar Pessoas',
            'Editar Pessoas',
            'Gerenciar Vínculos Estruturais de Pessoas',
            'Editar Turmas e Componentes de Pessoas',
        ])->map(fn (string $permissao): Permission => Permission::findOrCreate($permissao, 'web'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::factory()->create(['email_approved' => true]);
        $admin->assignRole(Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']));
        $admin->syncPermissions($permissoes);
        $pessoaAdmin = Servidor::query()->create([
            'user_id' => $admin->id,
            'nome' => $admin->name,
            'email' => $admin->email,
            'status' => Pessoa::STATUS_ATIVO,
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoaAdmin->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::professorPadrao()->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
        ]);

        return $admin;
    }

    /** @return array{0:mixed,1:string} */
    private function formularioProfessorValido(
        User $admin,
        Escola $escola,
        string $email,
        string $numeroMatricula,
    ): array {
        $formulario = Livewire::actingAs($admin)
            ->test(PessoaForm::class, ['pessoaId' => null]);
        $matriculaKey = (string) array_key_first($formulario->get('matriculas'));

        $formulario
            ->set('nome', 'Pessoa de Validação')
            ->set('email', $email)
            ->call('cargaHorariaAlterada', 20)
            ->set("matriculas.{$matriculaKey}.matricula", $numeroMatricula)
            ->call('turnoAlterado', $matriculaKey, 'manha')
            ->call('adicionarLotacao', $matriculaKey);
        $lotacaoKey = (string) array_key_first($formulario->get("matriculas.{$matriculaKey}.escolas"));
        $formulario->call('escolaAlterada', $matriculaKey, $lotacaoKey, $escola->id);

        return [$formulario, $matriculaKey];
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);
    }

    private function criarEscola(string $nome, Setor $setor): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }
}
