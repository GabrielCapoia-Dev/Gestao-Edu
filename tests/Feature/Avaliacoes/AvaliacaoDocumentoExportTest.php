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
use App\Services\ServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $usuario->escolas()->attach($escola->id);
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
        $this->assertSame(Aluno::TIPO_VINCULO_PRINCIPAL, $log->parametros['aluno_tipo_vinculo']);
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
        $this->assertSame(4, $logTurma->quantidade_paginas);
        $this->assertSame(2, $logTurma->parametros['vinculos_por_tipo'][Aluno::TIPO_VINCULO_PRINCIPAL]);
        $this->assertSame(0, $logTurma->parametros['vinculos_por_tipo'][Aluno::TIPO_VINCULO_CONTRA_TURNO]);
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

    public function test_exportacoes_distinguem_vinculo_principal_e_contra_turno(): void
    {
        Permission::findOrCreate('Exportar Avaliações');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Exportar Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Vinculo', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Vinculo', 'status' => true]);
        $escola = $this->criarEscola('Escola Vinculo Documento');
        $usuario->escolas()->attach($escola->id);
        $serie = $this->criarSerie('SER-VINC', '2o Ano');
        $turmaPrincipal = $this->criarTurma($escola, $serie, 'Principal');
        $turmaContra = $this->criarTurma($escola, $serie, 'Contra');
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-VINC', 'nome' => 'Arte']);
        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Participa das atividades',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliacao Vinculo Documento',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-20',
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->sync([$pauta->id]);
        $avaliacao->turmas()->sync([$turmaPrincipal->id, $turmaContra->id]);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);

        $principal = Aluno::query()->create([
            'nome' => 'Aluno Vinculo Principal',
            'cgm' => 'CGM-VINC-001',
            'data_nascimento' => '2016-01-01',
            'id_turma' => $turmaPrincipal->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
        ]);
        $contraTurno = Aluno::query()->create([
            'nome' => 'Aluno Vinculo Contra Turno',
            'cgm' => 'CGM-VINC-001',
            'data_nascimento' => '2016-01-01',
            'id_turma' => $turmaContra->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
        ]);

        foreach ([[$principal, $turmaPrincipal], [$contraTurno, $turmaContra]] as [$aluno, $turma]) {
            AvaliacaoResposta::query()->create([
                'avaliacao_id' => $avaliacao->id,
                'pauta_id' => $pauta->id,
                'turma_id' => $turma->id,
                'aluno_id' => $aluno->id,
                'alternativa_id' => $alternativa->id,
                'respondido_em' => now(),
            ]);
        }

        $avaliacao->load('tipo');
        $pauta->load(['componente', 'alternativas']);

        $metodo = new ReflectionMethod(AvaliacaoDocumentoExportService::class, 'montarDocumentoAluno');
        $metodo->setAccessible(true);

        $documento = $metodo->invoke(
            new AvaliacaoDocumentoExportService(),
            $avaliacao,
            $turmaContra->load(['escola', 'serie']),
            $contraTurno,
            collect([$pauta]),
            collect(),
            ['diretor' => '', 'coordenacao' => ''],
            ''
        );

        $this->assertSame('Contra turno', $documento['vinculo']);

        $response = $this->actingAs($usuario)->get(route('avaliacoes.documento.csv', [
            'avaliacao_id' => $avaliacao->id,
            'escopo' => 'escola',
            'escola_id' => $escola->id,
        ]));

        $response->assertOk();
        $conteudo = $response->streamedContent();

        $this->assertStringContainsString('Aluno Vínculo', $conteudo);
        $this->assertStringContainsString('Principal', $conteudo);
        $this->assertStringContainsString('Contra turno', $conteudo);

        $log = AvaliacaoExportacao::query()
            ->where('formato', 'csv')
            ->where('escopo', 'escola')
            ->firstOrFail();

        $this->assertSame(2, $log->quantidade_alunos);
        $this->assertSame(1, $log->parametros['vinculos_por_tipo'][Aluno::TIPO_VINCULO_PRINCIPAL]);
        $this->assertSame(1, $log->parametros['vinculos_por_tipo'][Aluno::TIPO_VINCULO_CONTRA_TURNO]);
    }

    public function test_resolve_diretor_e_coordenador_por_funcoes_do_servidor_para_o_documento(): void
    {
        $escola = $this->criarEscola('Escola Servidor Documento');
        $outraEscola = $this->criarEscola('Outra Escola Servidor');
        $serie = $this->criarSerie('SER-SERV-GEST', '1o Ano');
        $turma = $this->criarTurma($escola, $serie, 'A');

        $funcaoDiretor = FuncaoAdministrativa::query()->create([
            'nome' => 'Direcao Escolar',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'tem_relacao_turma' => false,
            'direcao_escolar' => true,
        ]);
        $funcaoCoordenador = FuncaoAdministrativa::query()->create([
            'nome' => 'Coordenacao Pedagogica',
            'categoria' => FuncaoAdministrativa::CATEGORIA_PEDAGOGICO,
            'ativo' => true,
            'tem_relacao_turma' => true,
            'coordenacao_pedagogica' => true,
        ]);
        $funcaoDirecaoSemFlag = FuncaoAdministrativa::query()->create([
            'nome' => 'Direcao Sem Flag',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'tem_relacao_turma' => false,
        ]);

        app(ServidorService::class)->criarServidorComFuncoes([
            'id_escola' => $escola->id,
            'nome' => 'Diretora da Escola',
            'matricula' => 'DIR-ESCOLA',
            'status' => 'ativo',
        ], [[
            'funcao_administrativa_id' => $funcaoDiretor->id,
            'portaria' => '111/2026',
        ]]);

        app(ServidorService::class)->criarServidorComFuncoes([
            'id_escola' => $outraEscola->id,
            'nome' => 'Diretora da Turma',
            'matricula' => 'DIR-TURMA',
            'status' => 'ativo',
        ], [[
            'funcao_administrativa_id' => $funcaoDiretor->id,
            'portaria' => '222/2026',
            'turma_ids' => [$turma->id],
        ]]);

        app(ServidorService::class)->criarServidorComFuncoes([
            'id_escola' => $escola->id,
            'nome' => 'Coordenadora Documento',
            'matricula' => 'COORD-TURMA',
            'status' => 'ativo',
        ], [[
            'funcao_administrativa_id' => $funcaoCoordenador->id,
            'portaria' => '333/2026',
            'turma_ids' => [$turma->id],
        ]]);

        app(ServidorService::class)->criarServidorComFuncoes([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Sem Flag',
            'matricula' => 'SEM-FLAG',
            'status' => 'ativo',
        ], [$funcaoDirecaoSemFlag->id]);

        $gestores = (new AvaliacaoDocumentoExportService())->gestoresDaTurma($turma);

        $this->assertSame('Diretora da Turma - 222/2026', $gestores['diretor']);
        $this->assertSame('Coordenadora Documento - 333/2026', $gestores['coordenacao']);
        $this->assertTrue($gestores['tem_diretor']);
        $this->assertTrue($gestores['tem_coordenacao']);
        $this->assertTrue($gestores['pode_exportar']);
        $this->assertSame('', $gestores['motivo_bloqueio']);
    }

    public function test_nome_da_funcao_sem_flag_nao_resolve_diretor_ou_coordenador(): void
    {
        $escola = $this->criarEscola('Escola Sem Flag Documento');
        $serie = $this->criarSerie('SER-SEM-FLAG', '1o Ano');
        $turma = $this->criarTurma($escola, $serie, 'A');

        $funcaoDirecaoSemFlag = FuncaoAdministrativa::query()->create([
            'nome' => 'Direcao Escolar',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'tem_relacao_turma' => false,
        ]);

        app(ServidorService::class)->criarServidorComFuncoes([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Direcao Sem Flag',
            'matricula' => 'DIR-SEM-FLAG',
            'status' => 'ativo',
        ], [$funcaoDirecaoSemFlag->id]);

        $gestores = (new AvaliacaoDocumentoExportService())->gestoresDaTurma($turma);

        $this->assertSame('', $gestores['diretor']);
        $this->assertSame('', $gestores['coordenacao']);
        $this->assertFalse($gestores['tem_diretor']);
        $this->assertFalse($gestores['tem_coordenacao']);
        $this->assertFalse($gestores['pode_exportar']);
        $this->assertSame('A turma não possui vínculo com Diretor(a) ou Coordenador(a).', $gestores['motivo_bloqueio']);
    }

    public function test_exportacao_do_parecer_fica_bloqueada_quando_falta_coordenacao_na_turma(): void
    {
        $escola = $this->criarEscola('Escola Sem Coordenacao Documento');
        $serie = $this->criarSerie('SER-SEM-COORD', '1o Ano');
        $turma = $this->criarTurma($escola, $serie, 'A');

        $funcaoDiretor = FuncaoAdministrativa::query()->create([
            'nome' => 'Direcao Escolar',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'tem_relacao_turma' => false,
            'direcao_escolar' => true,
        ]);

        app(ServidorService::class)->criarServidorComFuncoes([
            'id_escola' => $escola->id,
            'nome' => 'Diretora sem Coordenacao',
            'matricula' => 'DIR-SEM-COORD',
            'status' => 'ativo',
        ], [[
            'funcao_administrativa_id' => $funcaoDiretor->id,
            'portaria' => '123/2026',
        ]]);

        $gestores = (new AvaliacaoDocumentoExportService())->gestoresDaTurma($turma);

        $this->assertSame('Diretora sem Coordenacao - 123/2026', $gestores['diretor']);
        $this->assertSame('', $gestores['coordenacao']);
        $this->assertTrue($gestores['tem_diretor']);
        $this->assertFalse($gestores['tem_coordenacao']);
        $this->assertFalse($gestores['pode_exportar']);
        $this->assertSame('A turma não possui vínculo com Diretor(a) ou Coordenador(a).', $gestores['motivo_bloqueio']);
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
        $this->assertSame('Principal', $documento['vinculo']);
    }

    public function test_documento_omite_informacoes_complementares_vazias_e_calcula_periodo_por_matricula_e_transferencia(): void
    {
        Carbon::setTestNow('2026-06-30 10:00:00');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Periodo Aluno', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Periodo Aluno', 'status' => true]);
        $escola = $this->criarEscola('Escola Periodo Documento');
        $serie = $this->criarSerie('SER-PER-DOC', 'Infantil 5');
        $turma = $this->criarTurma($escola, $serie, 'A');
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-PER-DOC', 'nome' => 'Corpo e movimento']);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Participa das atividades propostas',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);

        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliacao Periodo Documento',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => '2026-02-22',
            'data_fim' => '2026-06-15',
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach($turma->id);

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Periodo Documento',
            'cgm' => 'CGM-PER-DOC-001',
            'data_nascimento' => '2015-01-01',
            'data_matricula' => '2026-03-10',
            'id_turma' => $turma->id,
            'status' => Aluno::STATUS_TRANSFERIDO,
            'status_alterado_em' => '2026-05-20 09:00:00',
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

        $this->assertSame('10 de março de 2026 a 20 de maio de 2026', $documento['periodo_avaliacao']);
        $this->assertSame('30 de junho de 2026', $documento['data_impressao']);
        $this->assertFalse($documento['componentes'][0]['mostrar_informacoes_complementares']);

        Carbon::setTestNow();
    }

    public function test_regra_duplex_adiciona_pagina_em_branco_apenas_para_exportacoes_multialuno_com_total_impar(): void
    {
        $metodo = new ReflectionMethod(AvaliacaoDocumentoExportService::class, 'deveAdicionarPaginaEmBranco');
        $metodo->setAccessible(true);
        $service = new AvaliacaoDocumentoExportService();

        $this->assertTrue($metodo->invoke($service, 'turma', 1));
        $this->assertTrue($metodo->invoke($service, 'escola', 3));
        $this->assertFalse($metodo->invoke($service, 'turma', 2));
        $this->assertFalse($metodo->invoke($service, 'aluno', 1));
    }

    public function test_view_do_documento_remove_vinculo_do_cabecalho_e_adiciona_espacamento_antes_da_cidade(): void
    {
        $html = view('relatorios.Avaliacoes.documento', [
            'documentos' => [[
                'logo' => '',
                'escola' => 'Escola Documento',
                'avaliacao_titulo' => 'PARECER TESTE',
                'estudante' => 'Aluno Documento',
                'cgm' => 'CGM-DOC-123',
                'vinculo' => 'Principal',
                'curso' => 'Infantil 5',
                'turma' => 'Turma A',
                'turno' => 'Manhã',
                'documento_tipo' => null,
                'ano_letivo' => '2026',
                'periodo_avaliacao' => '12 de junho de 2026 a 10 de julho de 2026',
                'data_impressao' => '01 de julho de 2026',
                'diretor' => 'Diretora Documento',
                'coordenacao' => 'Coordenadora Documento',
                'legenda' => [],
                'componentes' => [],
            ]],
        ])->render();

        $this->assertStringNotContainsString('Principal', $html);
        $this->assertStringContainsString('.footer-period-spacer {', $html);
        $this->assertStringContainsString('height: 40px;', $html);
        $this->assertStringContainsString('<div class="footer-period-spacer"></div>', $html);
        $this->assertStringContainsString('<p class="footer-city">Umuarama 01 de julho de 2026</p>', $html);
    }

    public function test_legenda_do_documento_respeita_ordem_configurada_das_alternativas(): void
    {
        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Ordem Documento', 'status' => true]);

        $alternativaSim = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Sim',
            'tem_observacao' => false,
            'vai_no_documento' => true,
            'descricao_documento' => 'Atingiu a pauta completamente.',
            'ordem_documento' => 1,
            'status' => true,
        ]);
        $alternativaNao = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'NÃ£o',
            'tem_observacao' => false,
            'vai_no_documento' => true,
            'descricao_documento' => 'NÃ£o atingiu a pauta.',
            'ordem_documento' => 2,
            'status' => true,
        ]);
        $alternativaParcial = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Parcial',
            'tem_observacao' => false,
            'vai_no_documento' => true,
            'descricao_documento' => 'Atingiu a pauta parcialmente.',
            'ordem_documento' => 3,
            'status' => true,
        ]);

        $metodo = new ReflectionMethod(AvaliacaoDocumentoExportService::class, 'montarLegenda');
        $metodo->setAccessible(true);

        $legenda = $metodo->invoke(
            new AvaliacaoDocumentoExportService(),
            [
                1 => collect([$alternativaSim, $alternativaNao, $alternativaParcial]),
            ]
        );

        $this->assertSame(
            ['SIM', mb_strtoupper((string) $alternativaNao->nome), 'PARCIAL'],
            $legenda->pluck('nome')->all()
        );
        $this->assertSame(
            [
                'Atingiu a pauta completamente.',
                (string) $alternativaNao->descricao_documento,
                'Atingiu a pauta parcialmente.',
            ],
            $legenda->pluck('descricao')->all()
        );

    }

    public function test_exportacao_direta_nao_permite_escola_fora_do_vinculo_mesmo_com_listar_avaliacoes(): void
    {
        Permission::findOrCreate('Exportar Avaliações');
        Permission::findOrCreate('Listar Avaliações');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Exportar Avaliações', 'Listar Avaliações']);

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Escopo Exportacao', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Escopo Exportacao', 'status' => true]);
        $escolaPermitida = $this->criarEscola('Escola Permitida Exportacao');
        $escolaBloqueada = $this->criarEscola('Escola Bloqueada Exportacao');
        $usuario->escolas()->attach($escolaPermitida->id);

        $serie = $this->criarSerie('SER-EXP-ESCOPO', '4o Ano');
        $turmaPermitida = $this->criarTurma($escolaPermitida, $serie, 'Permitida');
        $turmaBloqueada = $this->criarTurma($escolaBloqueada, $serie, 'Bloqueada');
        $alunoBloqueado = Aluno::query()->create([
            'nome' => 'Aluno Bloqueado Exportacao',
            'cgm' => 'CGM-EXP-BLOQ',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaBloqueada->id,
        ]);

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-EXP-ESCOPO',
            'nome' => 'Geografia',
        ]);
        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Localiza informacoes geograficas',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliacao Exportacao Escopo',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-20',
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->sync([$pauta->id]);
        $avaliacao->turmas()->sync([$turmaPermitida->id, $turmaBloqueada->id]);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escolaPermitida->id, $escolaBloqueada->id]);

        $this->actingAs($usuario)->get(route('avaliacoes.documento.csv', [
            'avaliacao_id' => $avaliacao->id,
            'escopo' => 'turma',
            'turma_id' => $turmaBloqueada->id,
        ]))->assertNotFound();

        $this->actingAs($usuario)->get(route('avaliacoes.documento.pdf', [
            'avaliacao_id' => $avaliacao->id,
            'escopo' => 'aluno',
            'aluno_id' => $alunoBloqueado->id,
        ]))->assertNotFound();

        $this->actingAs($usuario)->get(route('avaliacoes.documento.csv', [
            'avaliacao_id' => $avaliacao->id,
            'escopo' => 'escola',
            'escola_id' => $escolaBloqueada->id,
        ]))->assertNotFound();
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
