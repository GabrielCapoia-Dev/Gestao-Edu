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
        $this->assertSame(2, $logTurma->quantidade_paginas);
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

        $metodo = new ReflectionMethod(AvaliacaoDocumentoExportService::class, 'gestoresDaTurma');
        $metodo->setAccessible(true);

        $gestores = $metodo->invoke(new AvaliacaoDocumentoExportService(), $turma);

        $this->assertSame('Diretora da Turma - 222/2026', $gestores['diretor']);
        $this->assertSame('Coordenadora Documento - 333/2026', $gestores['coordenacao']);
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

        $metodo = new ReflectionMethod(AvaliacaoDocumentoExportService::class, 'gestoresDaTurma');
        $metodo->setAccessible(true);

        $gestores = $metodo->invoke(new AvaliacaoDocumentoExportService(), $turma);

        $this->assertSame('', $gestores['diretor']);
        $this->assertSame('', $gestores['coordenacao']);
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
