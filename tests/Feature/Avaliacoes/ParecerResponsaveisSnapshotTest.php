<?php

namespace Tests\Feature\Avaliacoes;

use App\Exceptions\ResponsaveisParecerInvalidosException;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumentoHistorico;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\PeriodoAvaliacao;
use App\Models\Pauta;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorFuncaoTurma;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Policies\SetorPolicy;
use App\Services\AlunoTransferenciaParecerService;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;
use App\Services\Avaliacoes\AvaliacaoDocumentoExportService;
use App\Services\Avaliacoes\AvaliacaoParecerSnapshotService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ParecerResponsaveisSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_captura_o_mesmo_snapshot_no_lote_e_o_preserva_na_reexportacao_e_no_historico(): void
    {
        Carbon::setTestNow('2026-07-13 10:00:00');

        [$escola, $turma, $avaliacao] = $this->criarContextoAvaliacao();
        [$diretor, $coordenador] = $this->criarResponsaveisPrincipais($escola, $turma);
        $alunoUm = $this->criarAluno($turma, 'Aluno Um', 'CGM-SNAPSHOT-1');
        $alunoDois = $this->criarAluno($turma, 'Aluno Dois', 'CGM-SNAPSHOT-2');

        $documentos = app(AvaliacaoParecerSnapshotService::class)
            ->capturarParaAlunos($avaliacao, $turma, collect([$alunoUm, $alunoDois]));

        $snapshotUm = $documentos->firstWhere('aluno_id', $alunoUm->id)?->responsaveis_snapshot;
        $snapshotDois = $documentos->firstWhere('aluno_id', $alunoDois->id)?->responsaveis_snapshot;

        $this->assertSame($snapshotUm, $snapshotDois);
        $this->assertSame((int) $diretor->id, $snapshotUm['diretor']['vinculo_id']);
        $this->assertSame((int) $coordenador->id, $snapshotUm['coordenador']['vinculo_id']);
        $this->assertSame((int) $turma->id, $snapshotUm['turma']['id']);
        $this->assertSame('2026-07-13T10:00:00-03:00', $snapshotUm['capturado_em']);

        $diretor->forceFill([
            'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
            'data_fim' => '2026-07-13',
        ])->save();
        ServidorFuncaoTurma::query()
            ->where('servidor_funcao_administrativa_id', $coordenador->id)
            ->update([
                'status' => ServidorFuncaoTurma::STATUS_INATIVO,
                'data_fim' => '2026-07-13',
            ]);

        $reexportado = app(AvaliacaoParecerSnapshotService::class)
            ->capturarParaAluno($avaliacao, $turma, $alunoUm);

        $this->assertSame($snapshotUm, $reexportado->responsaveis_snapshot);
        $this->assertTrue(
            app(AvaliacaoDocumentoExportService::class)
                ->gestoresDaTurma($turma, (int) $avaliacao->id)['pode_exportar'],
        );

        $turmaDestino = $this->criarTurma($escola, $turma->serie, 'Destino');
        $alunoUm->forceFill([
            'status' => Aluno::STATUS_TRANSFERIDO,
            'status_alterado_em' => now(),
        ])->save();
        $alunoDestino = $this->criarAluno($turmaDestino, 'Aluno Um', 'CGM-SNAPSHOT-1');
        $usuario = User::factory()->create();

        app(AvaliacaoAlunoDocumentoService::class)->moverDocumentosDoAluno(
            $alunoUm,
            $alunoDestino,
            AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_TRANSFERENCIA,
            $usuario,
        );

        $historico = AvaliacaoAlunoDocumentoHistorico::query()->firstOrFail();
        $this->assertSame($snapshotUm, $historico->responsaveis_snapshot);
        $this->assertNotNull($historico->responsaveis_snapshot_em);

        Carbon::setTestNow();
    }

    public function test_ambiguidade_de_coordenador_principal_bloqueia_sem_criar_documento(): void
    {
        [$escola, $turma, $avaliacao] = $this->criarContextoAvaliacao();
        $this->criarResponsaveisPrincipais($escola, $turma);

        $funcaoCoordenacao = FuncaoAdministrativa::coordenacaoPadrao();
        $pessoa = $this->criarPessoa('Outra Coordenadora');
        $vinculo = $this->criarVinculo(
            $pessoa,
            $funcaoCoordenacao,
            $escola,
            'PORT-COORD-2',
        );
        ServidorFuncaoTurma::query()->create([
            'servidor_funcao_administrativa_id' => $vinculo->id,
            'turma_id' => $turma->id,
            'principal' => true,
            'status' => ServidorFuncaoTurma::STATUS_ATIVO,
            'data_inicio' => now()->subDay()->toDateString(),
        ]);
        $aluno = $this->criarAluno($turma, 'Aluno Ambiguidade', 'CGM-AMBIGUIDADE');

        try {
            app(AvaliacaoParecerSnapshotService::class)
                ->capturarParaAluno($avaliacao, $turma, $aluno);
            $this->fail('A ambiguidade deveria bloquear o snapshot.');
        } catch (ResponsaveisParecerInvalidosException $exception) {
            $this->assertStringContainsString('mais de uma coordenação', $exception->getMessage());
        }

        $this->assertDatabaseMissing('avaliacao_aluno_documentos', [
            'avaliacao_id' => $avaliacao->id,
            'aluno_id' => $aluno->id,
        ]);
    }

    public function test_transferencia_sem_responsaveis_falha_antes_de_criar_arquivo_ou_mover_aluno(): void
    {
        [, $turma, $avaliacao] = $this->criarContextoAvaliacao();
        $aluno = $this->criarAluno($turma, 'Aluno sem Gestores', 'CGM-SEM-GESTORES');
        $usuario = User::factory()->create(['id_escola' => $turma->id_escola]);
        $arquivosAntes = glob(storage_path('app/parecer-transferencia-*.zip')) ?: [];

        try {
            app(AlunoTransferenciaParecerService::class)->exportarETransferir($aluno, $usuario);
            $this->fail('A transferência deveria ser bloqueada.');
        } catch (ResponsaveisParecerInvalidosException $exception) {
            $this->assertStringContainsString('direção ativa', $exception->getMessage());
        }

        $this->assertSame(Aluno::STATUS_MATRICULADO, $aluno->fresh()->status);
        $this->assertDatabaseMissing('avaliacao_aluno_documentos', [
            'avaliacao_id' => $avaliacao->id,
            'aluno_id' => $aluno->id,
        ]);
        $this->assertSame($arquivosAntes, glob(storage_path('app/parecer-transferencia-*.zip')) ?: []);
    }

    public function test_montagem_bloqueia_documento_sem_snapshot_de_responsaveis(): void
    {
        [, $turma, $avaliacao] = $this->criarContextoAvaliacao();
        $aluno = $this->criarAluno($turma, 'Aluno sem snapshot', 'CGM-SEM-SNAPSHOT');
        $metodo = new ReflectionMethod(AvaliacaoDocumentoExportService::class, 'montarDocumentoAluno');
        $metodo->setAccessible(true);

        $this->expectException(ResponsaveisParecerInvalidosException::class);
        $this->expectExceptionMessage('snapshot de responsáveis');

        $metodo->invoke(
            app(AvaliacaoDocumentoExportService::class),
            $avaliacao,
            $turma->load(['escola', 'serie']),
            $aluno,
            collect(),
            collect(),
            ['diretor' => '', 'coordenacao' => ''],
            '',
        );
    }

    public function test_documento_apresenta_escola_turma_turno_e_serie_do_snapshot(): void
    {
        [$escolaOrigem, $turmaOrigem, $avaliacao] = $this->criarContextoAvaliacao();
        $this->criarResponsaveisPrincipais($escolaOrigem, $turmaOrigem);
        $aluno = $this->criarAluno($turmaOrigem, 'Aluno contexto congelado', 'CGM-CONTEXTO-SNAPSHOT');

        $documentoPersistido = app(AvaliacaoParecerSnapshotService::class)
            ->capturarParaAluno($avaliacao, $turmaOrigem, $aluno);

        $escolaDestino = Escola::query()->create([
            'codigo' => 'ESC-DEST-'.uniqid(),
            'nome' => 'Escola Destino Snapshot',
            'email' => uniqid().'@teste.local',
        ]);
        $serieDestino = Serie::query()->create([
            'codigo' => 'SER-DEST-'.uniqid(),
            'nome' => 'Série Destino Snapshot',
        ]);
        $turmaDestino = $this->criarTurma($escolaDestino, $serieDestino, 'Destino');
        $turmaDestino->forceFill(['turno' => 'tarde'])->save();

        $metodo = new ReflectionMethod(AvaliacaoDocumentoExportService::class, 'montarDocumentoAluno');
        $metodo->setAccessible(true);
        $documento = $metodo->invoke(
            app(AvaliacaoDocumentoExportService::class),
            $avaliacao,
            $turmaDestino->load(['escola', 'serie']),
            $aluno,
            collect(),
            collect(),
            ['diretor' => '', 'coordenacao' => ''],
            '',
        );

        $this->assertSame($escolaOrigem->nome, $documento['escola']);
        $this->assertSame('Turma '.$turmaOrigem->nome, $documento['turma']);
        $this->assertSame('Manha', $documento['turno']);
        $this->assertSame($turmaOrigem->serie->nome, $documento['curso']);
        $this->assertSame((int) $turmaOrigem->id_serie, $documentoPersistido->responsaveis_snapshot['serie']['id']);
    }

    public function test_conflito_de_movimentacao_preserva_snapshot_do_documento_destino_no_historico(): void
    {
        [$escola, $turmaOrigem, $avaliacao] = $this->criarContextoAvaliacao();
        $turmaDestino = $this->criarTurma($escola, $turmaOrigem->serie, 'Destino conflito');
        $alunoOrigem = $this->criarAluno($turmaOrigem, 'Aluno origem conflito', 'CGM-CONFLITO');
        $alunoOrigem->forceFill([
            'status' => Aluno::STATUS_TRANSFERIDO,
            'status_alterado_em' => now(),
        ])->save();
        $alunoDestino = $this->criarAluno($turmaDestino, 'Aluno destino conflito', 'CGM-CONFLITO');
        $service = app(AvaliacaoAlunoDocumentoService::class);

        $documentoOrigem = $service->obterOuCriar($avaliacao, $alunoOrigem, somentePrincipal: false);
        $documentoOrigem->forceFill([
            'responsaveis_snapshot' => ['v' => 1, 'marcador' => 'origem'],
            'responsaveis_snapshot_em' => now(),
            'total_pautas_respondidas' => 2,
        ])->save();

        $documentoDestino = $service->obterOuCriar($avaliacao, $alunoDestino, somentePrincipal: false);
        $documentoDestino->forceFill([
            'responsaveis_snapshot' => ['v' => 1, 'marcador' => 'destino'],
            'responsaveis_snapshot_em' => now(),
            'total_pautas_respondidas' => 1,
        ])->save();

        $service->moverDocumentosDoAluno(
            $alunoOrigem,
            $alunoDestino,
            AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_TRANSFERENCIA,
        );

        $historicos = AvaliacaoAlunoDocumentoHistorico::query()->orderBy('id')->get();
        $historicoDestino = $historicos->firstWhere('documento_id', $documentoDestino->id);

        $this->assertCount(2, $historicos);
        $this->assertNotNull($historicoDestino);
        $this->assertSame('destino', $historicoDestino->responsaveis_snapshot['marcador']);
        $this->assertSame((int) $alunoDestino->id, (int) $historicoDestino->aluno_origem_id);
        $this->assertDatabaseMissing('avaliacao_aluno_documentos', ['id' => $documentoDestino->id]);
    }

    public function test_transferencia_bloqueia_usuario_fora_do_escopo_antes_do_snapshot(): void
    {
        [$escola, $turma, $avaliacao] = $this->criarContextoAvaliacao();
        $this->criarResponsaveisPrincipais($escola, $turma);
        $aluno = $this->criarAluno($turma, 'Aluno fora do escopo', 'CGM-FORA-ESCOPO');
        $outraEscola = Escola::query()->create([
            'codigo' => 'ESC-OUTRA-'.uniqid(),
            'nome' => 'Outra Escola',
            'email' => uniqid().'@teste.local',
        ]);
        $usuario = User::factory()->create(['id_escola' => $outraEscola->id]);

        try {
            app(AlunoTransferenciaParecerService::class)->exportarETransferir($aluno, $usuario);
            $this->fail('A transferência deveria respeitar o escopo escolar do usuário.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('escopo escolar', $exception->getMessage());
        }

        $this->assertSame(Aluno::STATUS_MATRICULADO, $aluno->fresh()->status);
        $this->assertDatabaseMissing('avaliacao_aluno_documentos', [
            'avaliacao_id' => $avaliacao->id,
            'aluno_id' => $aluno->id,
        ]);
    }

    public function test_escopo_global_explicito_pode_preparar_snapshot_sem_escola_vinculada(): void
    {
        Permission::findOrCreate(SetorPolicy::GLOBAL_SCOPE_PERMISSION);
        [$escola, $turma, $avaliacao] = $this->criarContextoAvaliacao();
        $this->criarResponsaveisPrincipais($escola, $turma);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $avaliacao->tipo_avaliacao_id,
            'texto' => 'Pauta para escopo global',
            'serie_id' => $turma->id_serie,
            'status' => true,
        ]);
        $avaliacao->pautas()->attach($pauta->id);
        $aluno = $this->criarAluno($turma, 'Aluno escopo global', 'CGM-ESCOPO-GLOBAL');
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(SetorPolicy::GLOBAL_SCOPE_PERMISSION);

        app(AvaliacaoDocumentoExportService::class)->prepararSnapshotsParecer([
            'avaliacao_id' => $avaliacao->id,
            'escopo' => 'aluno',
            'aluno_id' => $aluno->id,
        ], $usuario);

        $this->assertDatabaseHas('avaliacao_aluno_documentos', [
            'avaliacao_id' => $avaliacao->id,
            'aluno_id' => $aluno->id,
        ]);
    }

    public function test_controller_sincrono_redireciona_quando_responsaveis_estao_ausentes(): void
    {
        Permission::findOrCreate('Exportar Avaliações');
        [, $turma, $avaliacao] = $this->criarContextoAvaliacao();
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $avaliacao->tipo_avaliacao_id,
            'texto' => 'Pauta para bloqueio de responsáveis',
            'serie_id' => $turma->id_serie,
            'status' => true,
        ]);
        $avaliacao->pautas()->attach($pauta->id);
        $aluno = $this->criarAluno($turma, 'Aluno controller', 'CGM-CONTROLLER');
        $usuario = User::factory()->create(['id_escola' => $turma->id_escola]);
        $usuario->givePermissionTo('Exportar Avaliações');

        $response = $this->actingAs($usuario)
            ->from('/origem-exportacao')
            ->get(route('avaliacoes.documento.pdf', [
                'avaliacao_id' => $avaliacao->id,
                'escopo' => 'aluno',
                'aluno_id' => $aluno->id,
            ]));

        $response->assertRedirect('/origem-exportacao');
        $this->assertDatabaseMissing('avaliacao_aluno_documentos', [
            'avaliacao_id' => $avaliacao->id,
            'aluno_id' => $aluno->id,
        ]);
    }

    /**
     * @return array{Escola, Turma, Avaliacao}
     */
    private function criarContextoAvaliacao(): array
    {
        $escola = Escola::query()->create([
            'codigo' => 'ESC'.uniqid(),
            'nome' => 'Escola Snapshot '.uniqid(),
            'email' => uniqid().'@teste.local',
        ]);
        $serie = Serie::query()->create([
            'codigo' => 'SER'.uniqid(),
            'nome' => 'Série Snapshot',
        ]);
        $turma = $this->criarTurma($escola, $serie, 'Origem');
        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Snapshot', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Período Snapshot', 'status' => true]);
        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliação Snapshot',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => now()->subMonth()->toDateString(),
            'data_fim' => now()->addMonth()->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->turmas()->attach($turma->id);

        return [$escola, $turma, $avaliacao];
    }

    /**
     * @return array{ServidorFuncaoAdministrativa, ServidorFuncaoAdministrativa}
     */
    private function criarResponsaveisPrincipais(Escola $escola, Turma $turma): array
    {
        $diretor = $this->criarVinculo(
            $this->criarPessoa('Diretora Snapshot'),
            FuncaoAdministrativa::direcaoPadrao(),
            $escola,
            'PORT-DIR-1',
        );
        $coordenador = $this->criarVinculo(
            $this->criarPessoa('Coordenadora Snapshot'),
            FuncaoAdministrativa::coordenacaoPadrao(),
            $escola,
            'PORT-COORD-1',
        );

        ServidorFuncaoTurma::query()->create([
            'servidor_funcao_administrativa_id' => $coordenador->id,
            'turma_id' => $turma->id,
            'principal' => true,
            'status' => ServidorFuncaoTurma::STATUS_ATIVO,
            'data_inicio' => now()->subDay()->toDateString(),
        ]);

        return [$diretor, $coordenador];
    }

    private function criarVinculo(
        Servidor $pessoa,
        FuncaoAdministrativa $funcao,
        Escola $escola,
        string $portaria,
    ): ServidorFuncaoAdministrativa {
        return ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => $funcao->id,
            'id_escola' => $escola->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'portaria' => $portaria,
            'principal' => true,
            'data_inicio' => now()->subDay()->toDateString(),
        ]);
    }

    private function criarPessoa(string $nome): Servidor
    {
        return Servidor::query()->create([
            'nome' => $nome,
            'email' => uniqid().'@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ]);
    }

    private function criarAluno(Turma $turma, string $nome, string $cgm): Aluno
    {
        return Aluno::query()->create([
            'nome' => $nome,
            'cgm' => $cgm,
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);
    }

    private function criarTurma(Escola $escola, Serie $serie, string $nome): Turma
    {
        return Turma::query()->create([
            'codigo' => 'TUR'.uniqid(),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }
}
