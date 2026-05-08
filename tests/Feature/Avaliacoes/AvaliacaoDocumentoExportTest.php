<?php

namespace Tests\Feature\Avaliacoes;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoExportacao;
use App\Models\AvaliacaoInformacaoComplementar;
use App\Models\AvaliacaoResposta;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Services\Avaliacoes\AvaliacaoDocumentoExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AvaliacaoDocumentoExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_exporta_pdf_da_avaliacao_e_registra_log_simples(): void
    {
        Permission::findOrCreate('Exportar Avaliações');
        Permission::findOrCreate('Listar Avaliações');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Exportar Avaliações', 'Listar Avaliações']);

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Descritivo', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Diagnostico', 'status' => true]);

        $escola = $this->criarEscola('Escola Documento');
        $serie = $this->criarSerie('SER-DOC', 'Infantil 4');
        $turma = $this->criarTurma($escola, $serie, 'A');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-DOC',
            'nome' => 'O eu, o outro e o nos',
        ]);

        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-DOC',
            'nome' => 'Professor Documento',
            'email' => 'documento@edu.umuarama.pr.gov.br',
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $alternativaDocumento = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Sim',
            'tem_observacao' => false,
            'observacao' => 'Descricao antiga',
            'vai_no_documento' => true,
            'descricao_documento' => 'Atingiu a pauta completamente',
            'status' => true,
        ]);

        $alternativaInterna = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Uso interno',
            'tem_observacao' => false,
            'vai_no_documento' => false,
            'descricao_documento' => 'Nao deve ir ao documento',
            'status' => true,
        ]);

        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Reconhece combinados da turma',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach([$alternativaDocumento->id, $alternativaInterna->id]);

        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Parecer Periodo Diagnostico',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-20',
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach($turma->id);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Documento',
            'cgm' => 'CGM-DOC-001',
            'data_nascimento' => '2021-01-01',
            'id_turma' => $turma->id,
        ]);

        AvaliacaoResposta::query()->create([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
            'professor_id' => $professor->id,
            'alternativa_id' => $alternativaDocumento->id,
            'observacao' => 'Observacao da professora',
            'respondido_em' => now(),
        ]);

        $segundoAluno = Aluno::query()->create([
            'nome' => 'Aluno Documento Dois',
            'cgm' => 'CGM-DOC-002',
            'data_nascimento' => '2021-01-02',
            'id_turma' => $turma->id,
        ]);

        AvaliacaoResposta::query()->create([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $segundoAluno->id,
            'professor_id' => $professor->id,
            'alternativa_id' => $alternativaDocumento->id,
            'observacao' => 'Observacao da segunda estudante',
            'respondido_em' => now(),
        ]);

        $response = $this->actingAs($usuario)->get(route('avaliacoes.documento.pdf', [
            'avaliacao_id' => $avaliacao->id,
            'escopo' => 'aluno',
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
        ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseHas('avaliacao_exportacoes', [
            'avaliacao_id' => $avaliacao->id,
            'escola_id' => $escola->id,
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
            'user_id' => $usuario->id,
            'escopo' => 'aluno',
            'formato' => 'pdf',
            'quantidade_alunos' => 1,
        ]);

        $log = AvaliacaoExportacao::query()->firstOrFail();

        $this->assertSame('Parecer Periodo Diagnostico', $log->parametros['avaliacao']);
        $this->assertGreaterThan(0, $log->quantidade_paginas);
        $this->assertNotNull($log->exportado_em);

        $response = $this->actingAs($usuario)->get(route('avaliacoes.documento.pdf', [
            'avaliacao_id' => $avaliacao->id,
            'escopo' => 'turma',
            'turma_id' => $turma->id,
        ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $logTurma = AvaliacaoExportacao::query()
            ->where('escopo', 'turma')
            ->firstOrFail();

        $this->assertSame(2, $logTurma->quantidade_alunos);
        $this->assertSame(2, $logTurma->quantidade_paginas);
    }

    public function test_csv_exporta_todos_os_componentes_do_aluno_mesmo_sem_ser_professor_do_componente(): void
    {
        Permission::findOrCreate('Exportar Avaliações');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Exportar Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer CSV', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo CSV', 'status' => true]);
        $escola = $this->criarEscola('Escola CSV');
        $usuario->escolas()->attach($escola->id);
        $serie = $this->criarSerie('SER-CSV', '5o Ano');
        $turma = $this->criarTurma($escola, $serie, 'A');
        $aluno = Aluno::query()->create([
            'nome' => 'Aluno CSV',
            'cgm' => 'CGM-CSV',
            'data_nascimento' => '2014-01-01',
            'id_turma' => $turma->id,
        ]);

        $matematica = ComponenteCurricular::query()->create(['codigo' => 'MAT-CSV', 'nome' => 'Matematica']);
        $historia = ComponenteCurricular::query()->create(['codigo' => 'HIS-CSV', 'nome' => 'Historia']);
        $professorMatematica = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-MAT-CSV',
            'nome' => 'Professor Matematica CSV',
            'email' => 'matematica.csv@edu.umuarama.pr.gov.br',
        ]);
        $turma->componentes()->attach($matematica->id, [
            'professor_id' => $professorMatematica->id,
            'tem_professor' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pautaMatematica = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Resolve problemas',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $matematica->id,
            'status' => true,
        ]);
        $pautaHistoria = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Relaciona fatos historicos',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $historia->id,
            'status' => true,
        ]);
        $pautaMatematica->alternativas()->attach($alternativa->id);
        $pautaHistoria->alternativas()->attach($alternativa->id);

        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliacao CSV Completa',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-20',
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->sync([$pautaMatematica->id, $pautaHistoria->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$matematica->id, $historia->id]);
        $avaliacao->escolas()->sync([$escola->id]);

        foreach ([$pautaMatematica, $pautaHistoria] as $pauta) {
            AvaliacaoResposta::query()->create([
                'avaliacao_id' => $avaliacao->id,
                'pauta_id' => $pauta->id,
                'turma_id' => $turma->id,
                'aluno_id' => $aluno->id,
                'alternativa_id' => $alternativa->id,
                'respondido_em' => now(),
            ]);
        }

        AvaliacaoInformacaoComplementar::query()->create([
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
            'componente_curricular_id' => $historia->id,
            'informacoes_complementares' => 'Texto complementar de historia',
        ]);

        $response = $this->actingAs($usuario)->get(route('avaliacoes.documento.csv', [
            'avaliacao_id' => $avaliacao->id,
            'escopo' => 'aluno',
            'aluno_id' => $aluno->id,
        ]));

        $response->assertOk();
        $conteudo = $response->streamedContent();

        $this->assertStringContainsString('Matematica', $conteudo);
        $this->assertStringContainsString('Historia', $conteudo);
        $this->assertStringContainsString('Texto complementar de historia', $conteudo);
        $this->assertDatabaseHas('avaliacao_exportacoes', [
            'avaliacao_id' => $avaliacao->id,
            'user_id' => $usuario->id,
            'escopo' => 'aluno',
            'formato' => 'csv',
            'quantidade_alunos' => 1,
        ]);
    }

    public function test_resolve_diretor_e_coordenador_da_equipe_gestora_para_o_documento(): void
    {
        $escola = $this->criarEscola('Escola Gestora Documento');
        $outraEscola = $this->criarEscola('Outra Escola Gestora');
        $serie = $this->criarSerie('SER-GEST', '1o Ano');
        $turma = $this->criarTurma($escola, $serie, 'A');

        $funcaoDiretor = FuncaoAdministrativa::query()->create([
            'nome' => 'Diretor(a)',
            'tem_relacao_turma' => true,
        ]);
        $funcaoCoordenador = FuncaoAdministrativa::query()->create([
            'nome' => 'Coordenador(a)',
            'tem_relacao_turma' => true,
        ]);

        $diretor = Professor::query()->create([
            'id_escola' => $outraEscola->id,
            'matricula' => 'DIR-GEST',
            'nome' => 'Diretora Documento',
            'email' => 'diretora.documento@edu.umuarama.pr.gov.br',
            'funcao_administrativa_id' => $funcaoDiretor->id,
            'portaria' => '111/2026',
        ]);
        $diretor->turmasFuncao()->attach($turma->id);

        $coordenadora = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'COORD-GEST',
            'nome' => 'Coordenadora Documento',
            'email' => 'coordenadora.documento@edu.umuarama.pr.gov.br',
            'funcao_administrativa_id' => $funcaoCoordenador->id,
            'portaria' => '222/2026',
        ]);
        $coordenadora->turmasFuncao()->attach($turma->id);

        $metodo = new ReflectionMethod(AvaliacaoDocumentoExportService::class, 'gestoresDaTurma');
        $metodo->setAccessible(true);

        $gestores = $metodo->invoke(new AvaliacaoDocumentoExportService(), $turma);

        $this->assertSame('Diretora Documento - 111/2026', $gestores['diretor']);
        $this->assertSame('Coordenadora Documento - 222/2026', $gestores['coordenacao']);
    }

    public function test_documento_exibe_nao_avaliado_para_pauta_pendente(): void
    {
        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Pendente', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Pendente', 'status' => true]);
        $escola = $this->criarEscola('Escola Pendente Documento');
        $serie = $this->criarSerie('SER-PEND', '1o Ano');
        $turma = $this->criarTurma($escola, $serie, 'A');
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-PEND', 'nome' => 'Lingua Portuguesa']);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta sem resposta',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliacao com Pendencia',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-20',
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach($turma->id);

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Pendente Documento',
            'cgm' => 'CGM-PEND-DOC',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);

        $avaliacao->load('tipo');
        $pauta->load(['componente', 'alternativas']);

        $metodo = new ReflectionMethod(AvaliacaoDocumentoExportService::class, 'montarDocumentoAluno');
        $metodo->setAccessible(true);

        $documento = $metodo->invoke(
            new AvaliacaoDocumentoExportService(),
            $avaliacao,
            $turma->load(['escola', 'serie']),
            $aluno,
            collect([$pauta]),
            collect(),
            ['diretor' => '', 'coordenacao' => ''],
            ''
        );

        $this->assertSame('Não Avaliado', $documento['componentes'][0]['pautas'][0]['resultado']);
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 5)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
    }

    private function criarSerie(string $codigo, string $nome): Serie
    {
        return Serie::query()->create([
            'codigo' => $codigo,
            'nome' => $nome,
        ]);
    }

    private function criarTurma(Escola $escola, Serie $serie, string $nome): Turma
    {
        return Turma::query()->create([
            'codigo' => 'TUR'.strtoupper(substr(md5($nome.microtime()), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }
}
