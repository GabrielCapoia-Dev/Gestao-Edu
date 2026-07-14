<?php

namespace Tests\Feature\Alunos;

use App\Exceptions\MatriculaAlunoBloqueadaException;
use App\Filament\Admin\Pages\ParecerTransferenciaAluno;
use App\Filament\Admin\Resources\Alunos\Pages\ListAlunos;
use App\Livewire\AlunoParecerTransferenciaModal;
use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoAlunoDocumentoHistorico;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorFuncaoTurma;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\SistemaNotification;
use App\Services\AlunoMovimentacaoService;
use App\Services\AlunoTransferenciaParecerService;
use App\Services\Avaliacoes\AvaliacaoDocumentoExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AlunoMovimentacaoFluxoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_matricula_pendente_em_outra_escola_e_notifica_origem(): void
    {
        NotificationFacade::fake();

        foreach ([
            'Notificar Status Pendente',
            'Gerar Parecer de Transferencia',
        ] as $permission) {
            Permission::findOrCreate($permission);
        }

        $escolaOrigem = $this->criarEscola('Escola Origem');
        $escolaDestino = $this->criarEscola('Escola Destino');
        $turmaOrigem = $this->criarTurma($escolaOrigem, 'A');
        $turmaDestino = $this->criarTurma($escolaDestino, 'B');

        $alunoAtivo = Aluno::query()->create([
            'nome' => 'Aluno Ativo',
            'cgm' => 'CGM-ATIVO',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        $usuarioNotificado = User::factory()->create([
            'id_escola' => $escolaOrigem->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuarioNotificado->givePermissionTo('Notificar Status Pendente');

        $usuarioSemPermissao = User::factory()->create([
            'id_escola' => $escolaOrigem->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $alunoPendente = app(AlunoMovimentacaoService::class)->criarMatricula([
            'nome' => 'Nome digitado sera ignorado',
            'cgm' => 'CGM-ATIVO',
            'data_nascimento' => '2016-02-02',
            'id_turma' => $turmaDestino->id,
        ]);

        $this->assertDatabaseCount('alunos', 2);
        $this->assertSame(Aluno::STATUS_PENDENTE, $alunoPendente->status);
        $this->assertSame($alunoAtivo->id, $alunoPendente->pendencia_origem_aluno_id);
        $this->assertSame('Aluno Ativo', $alunoPendente->nome);
        $this->assertNull($alunoPendente->cgm_matricula_ativa);
        $this->assertSame($escolaDestino->id.'|CGM-ATIVO', $alunoPendente->cgm_unidade_matricula_ativa);

        NotificationFacade::assertSentTo(
            $usuarioNotificado,
            SistemaNotification::class,
            fn (SistemaNotification $notification): bool => $notification->url === route('filament.admin.pages.parecer-transferencia-aluno', ['aluno' => $alunoAtivo->id])
                && ($notification->metadata['tipo'] ?? null) === 'aluno_transferencia_pendente'
        );

        NotificationFacade::assertNotSentTo($usuarioSemPermissao, SistemaNotification::class);
    }

    public function test_bloqueia_matricula_com_cgm_na_mesma_unidade_ou_ja_pendente(): void
    {
        $escolaOrigem = $this->criarEscola('Escola Origem Bloqueio');
        $escolaDestino = $this->criarEscola('Escola Destino Bloqueio');
        $escolaTerceira = $this->criarEscola('Escola Terceira Bloqueio');
        $turmaOrigem = $this->criarTurma($escolaOrigem, 'A');
        $turmaOrigemB = $this->criarTurma($escolaOrigem, 'B', $turmaOrigem->serie);
        $turmaDestino = $this->criarTurma($escolaDestino, 'C');
        $turmaTerceira = $this->criarTurma($escolaTerceira, 'D');

        Aluno::query()->create([
            'nome' => 'Aluno Ativo',
            'cgm' => 'CGM-BLOQ',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        try {
            app(AlunoMovimentacaoService::class)->criarMatricula([
                'nome' => 'Aluno Duplicado',
                'cgm' => 'CGM-BLOQ',
                'data_nascimento' => '2015-01-01',
                'id_turma' => $turmaOrigemB->id,
            ]);

            $this->fail('A matricula duplicada na mesma unidade deveria ser bloqueada.');
        } catch (MatriculaAlunoBloqueadaException $exception) {
            $this->assertSame('Este CGM ja esta cadastrado nesta unidade.', $exception->getMessage());
        }

        app(AlunoMovimentacaoService::class)->criarMatricula([
            'nome' => 'Aluno Pendente',
            'cgm' => 'CGM-BLOQ',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaDestino->id,
        ]);

        $this->expectException(MatriculaAlunoBloqueadaException::class);
        $this->expectExceptionMessage('matricula pendente');

        app(AlunoMovimentacaoService::class)->criarMatricula([
            'nome' => 'Aluno Terceira Escola',
            'cgm' => 'CGM-BLOQ',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaTerceira->id,
        ]);
    }

    public function test_matricula_novo_contexto_apos_transferencia_copia_respostas_editaveis(): void
    {
        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();

        $alunoTransferido = Aluno::query()->create([
            'nome' => 'Aluno Historico',
            'cgm' => 'CGM-HIST',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
            'status' => Aluno::STATUS_TRANSFERIDO,
        ]);

        $this->criarRespostaDocumento([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $alunoTransferido->id,
            'alternativa_id' => $alternativa->id,
            'observacao' => 'Resposta anterior',
            'respondido_em' => now(),
        ]);

        $novoAluno = app(AlunoMovimentacaoService::class)->criarMatricula([
            'nome' => 'Aluno Historico',
            'cgm' => 'CGM-HIST',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaDestino->id,
        ]);

        $this->assertSame(Aluno::STATUS_MATRICULADO, $novoAluno->status);
        $this->assertSame($alunoTransferido->id, $novoAluno->aluno_origem_id);

        $this->assertDocumentoNoAluno(
            (int) $avaliacao->id,
            (int) $novoAluno->id,
            (int) $turmaDestino->id,
            (int) $pauta->id,
            (int) $alternativa->id,
            'Resposta anterior'
        );
        $this->assertHistoricoMovimentacao(
            (int) $avaliacao->id,
            (int) $alunoTransferido->id,
            (int) $novoAluno->id,
            AlunoMovimentacaoService::MOVIMENTACAO_TRANSFERENCIA
        );

        $this->assertDatabaseHas('alunos', [
            'id' => $alunoTransferido->id,
            'cgm_matricula_ativa' => null,
        ]);
    }

    public function test_remanejamento_marca_origem_e_cria_nova_matricula_com_respostas_editaveis(): void
    {
        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Remanejado',
            'cgm' => 'CGM-REM',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        $this->criarRespostaDocumento([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $aluno->id,
            'alternativa_id' => $alternativa->id,
            'respondido_em' => now(),
        ]);

        $novoAluno = app(AlunoMovimentacaoService::class)->remanejar($aluno, $turmaDestino->id);

        $this->assertDatabaseHas('alunos', [
            'id' => $aluno->id,
            'status' => Aluno::STATUS_REMANEJADO,
            'cgm_matricula_ativa' => null,
        ]);

        $this->assertDatabaseHas('alunos', [
            'id' => $novoAluno->id,
            'status' => Aluno::STATUS_MATRICULADO,
            'cgm_matricula_ativa' => 'CGM-REM',
            'aluno_origem_id' => $aluno->id,
            'turma_origem_id' => $turmaOrigem->id,
            'movimentacao_origem' => AlunoMovimentacaoService::MOVIMENTACAO_REMANEJAMENTO,
        ]);

        $this->assertDocumentoNoAluno(
            (int) $avaliacao->id,
            (int) $novoAluno->id,
            (int) $turmaDestino->id,
            (int) $pauta->id,
            (int) $alternativa->id
        );
        $this->assertHistoricoMovimentacao(
            (int) $avaliacao->id,
            (int) $aluno->id,
            (int) $novoAluno->id,
            AlunoMovimentacaoService::MOVIMENTACAO_REMANEJAMENTO
        );
        $this->assertDatabaseMissing('avaliacao_aluno_documentos', [
            'avaliacao_id' => $avaliacao->id,
            'aluno_id' => $aluno->id,
        ]);
    }

    public function test_remanejamento_nao_permite_turma_de_outra_serie(): void
    {
        [$escola, $serie, $turmaOrigem] = $this->criarCenarioAvaliacaoDuasTurmas();
        $outraSerie = Serie::query()->create(['codigo' => 'SER-OUTRA', 'nome' => '5o Ano']);
        $turmaOutraSerie = $this->criarTurma($escola, 'Outra Serie', $outraSerie);

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Serie Bloqueada',
            'cgm' => 'CGM-SERIE-BLOQ',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mesma serie');

        app(AlunoMovimentacaoService::class)->remanejar($aluno, $turmaOutraSerie->id);
    }

    public function test_remanejamento_vincula_avaliacao_historica_bloqueia_origem_e_deixa_destino_editavel(): void
    {
        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();
        $avaliacao->turmas()->detach($turmaDestino->id);

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Historico Remanejado',
            'cgm' => 'CGM-REM-HIST',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        $respostaOrigem = $this->criarRespostaDocumento([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $aluno->id,
            'alternativa_id' => $alternativa->id,
            'observacao' => 'Resposta antes do remanejamento',
            'respondido_em' => now(),
        ]);

        $informacaoOrigem = $this->criarInformacaoDocumento([
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $aluno->id,
            'componente_curricular_id' => $pauta->componente_curricular_id,
            'informacoes_complementares' => 'Informacao complementar antes do remanejamento',
        ]);

        $novoAluno = app(AlunoMovimentacaoService::class)->remanejar($aluno, $turmaDestino->id);

        $this->assertDatabaseHas('avaliacao_turma', [
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turmaDestino->id,
        ]);

        $this->assertHistoricoMovimentacao(
            (int) $avaliacao->id,
            (int) $aluno->id,
            (int) $novoAluno->id,
            AlunoMovimentacaoService::MOVIMENTACAO_REMANEJAMENTO
        );

        $this->assertDocumentoNoAluno(
            (int) $avaliacao->id,
            (int) $novoAluno->id,
            (int) $turmaDestino->id,
            (int) $pauta->id,
            (int) $alternativa->id,
            'Resposta antes do remanejamento'
        );

        $documentoDestino = AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $avaliacao->id)
            ->where('aluno_id', $novoAluno->id)
            ->first();
        $this->assertNotNull($documentoDestino);
        $info = $documentoDestino->informacaoDoComponente((int) $pauta->componente_curricular_id);
        $this->assertSame('Informacao complementar antes do remanejamento', $info['texto'] ?? null);
        $this->assertDatabaseMissing('avaliacao_aluno_documentos', [
            'avaliacao_id' => $avaliacao->id,
            'aluno_id' => $aluno->id,
        ]);
    }

    public function test_cria_vinculo_de_contra_turno_valido_e_preserva_vinculo_principal(): void
    {
        $escola = $this->criarEscola('Escola Contra Turno');
        $serie = Serie::query()->create(['codigo' => 'SER-CT', 'nome' => '3o Ano']);
        $turmaPrincipal = $this->criarTurma($escola, 'Manha', $serie, 'manha');
        $turmaContraTurno = $this->criarTurma($escola, 'Tarde', $serie, 'tarde');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Contra Turno',
            'cgm' => 'CGM-CT-OK',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaPrincipal->id,
        ]);

        $service = app(AlunoMovimentacaoService::class);
        $service->marcarContraTurno($aluno);
        $contraTurno = $service->vincularContraTurno($aluno, $turmaContraTurno->id);

        $this->assertDatabaseHas('alunos', [
            'id' => $aluno->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'permite_contra_turno' => true,
            'cgm_matricula_ativa' => 'CGM-CT-OK',
            'cgm_contra_turno_ativo' => null,
        ]);

        $this->assertDatabaseHas('alunos', [
            'id' => $contraTurno->id,
            'cgm' => 'CGM-CT-OK',
            'id_turma' => $turmaContraTurno->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
            'status' => Aluno::STATUS_MATRICULADO,
            'aluno_origem_id' => $aluno->id,
            'turma_origem_id' => $turmaPrincipal->id,
            'movimentacao_origem' => AlunoMovimentacaoService::MOVIMENTACAO_CONTRA_TURNO,
            'cgm_contra_turno_ativo' => 'CGM-CT-OK',
            'cgm_matricula_ativa' => null,
        ]);
    }

    public function test_contra_turno_bloqueia_turma_invalida_por_escola_serie_e_turno(): void
    {
        $escola = $this->criarEscola('Escola Contra Turno Regra');
        $outraEscola = $this->criarEscola('Escola Externa Contra Turno');
        $serie = Serie::query()->create(['codigo' => 'SER-CT-REGRA', 'nome' => '4o Ano']);
        $outraSerie = Serie::query()->create(['codigo' => 'SER-CT-OUTRA', 'nome' => '5o Ano']);

        $turmaPrincipal = $this->criarTurma($escola, 'Principal', $serie, 'manha');
        $turmaMesmoTurno = $this->criarTurma($escola, 'Mesmo Turno', $serie, 'manha');
        $turmaOutraSerie = $this->criarTurma($escola, 'Outra Serie', $outraSerie, 'tarde');
        $turmaOutraEscola = $this->criarTurma($outraEscola, 'Outra Escola', $serie, 'tarde');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Regra Contra Turno',
            'cgm' => 'CGM-CT-BLOQ',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaPrincipal->id,
        ]);

        $service = app(AlunoMovimentacaoService::class);

        foreach ([
            [$turmaMesmoTurno->id, 'turno diferente'],
            [$turmaOutraSerie->id, 'mesma serie'],
            [$turmaOutraEscola->id, 'mesma escola'],
        ] as [$turmaId, $mensagem]) {
            try {
                $service->vincularContraTurno($aluno, $turmaId);
                $this->fail('A validacao do contra turno deveria falhar.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString($mensagem, $exception->getMessage());
            }
        }
    }

    public function test_movimentacoes_do_vinculo_principal_falham_com_contra_turno_ativo(): void
    {
        $escola = $this->criarEscola('Escola Movimento Bloqueado');
        $serie = Serie::query()->create(['codigo' => 'SER-MOV-CT', 'nome' => '6o Ano']);
        $turmaPrincipal = $this->criarTurma($escola, 'Principal', $serie, 'manha');
        $turmaContraTurno = $this->criarTurma($escola, 'Contra', $serie, 'tarde');
        $turmaRemanejamento = $this->criarTurma($escola, 'Remanejamento', $serie, 'noite');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Movimento Bloqueado',
            'cgm' => 'CGM-CT-MOV',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaPrincipal->id,
        ]);

        $service = app(AlunoMovimentacaoService::class);
        $service->vincularContraTurno($aluno, $turmaContraTurno->id);

        foreach ([
            fn () => $service->remanejar($aluno, $turmaRemanejamento->id),
            fn () => $service->transferir($aluno, User::factory()->create()),
            fn () => $service->marcarStatusFinal($aluno, Aluno::STATUS_APROVADO),
        ] as $acao) {
            try {
                $acao();
                $this->fail('A movimentacao principal deveria ser bloqueada enquanto houver contra turno ativo.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('contra turno', $exception->getMessage());
            }
        }
    }

    public function test_encerrar_contra_turno_preserva_historico_e_bloqueia_dados_avaliativos(): void
    {
        [$escola, $serie, $turmaPrincipal, $turmaContraTurno, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas('manha', 'tarde');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Encerramento Contra Turno',
            'cgm' => 'CGM-CT-END',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaPrincipal->id,
        ]);

        $service = app(AlunoMovimentacaoService::class);
        $contraTurno = $service->vincularContraTurno($aluno, $turmaContraTurno->id);

        $resposta = $this->criarRespostaDocumento([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaContraTurno->id,
            'aluno_id' => $contraTurno->id,
            'alternativa_id' => $alternativa->id,
            'respondido_em' => now(),
        ]);

        $informacao = $this->criarInformacaoDocumento([
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turmaContraTurno->id,
            'aluno_id' => $contraTurno->id,
            'componente_curricular_id' => $pauta->componente_curricular_id,
            'informacoes_complementares' => 'Registro do contra turno',
        ]);

        $service->encerrarContraTurno($contraTurno);

        $this->assertDatabaseHas('alunos', [
            'id' => $contraTurno->id,
            'status' => Aluno::STATUS_CONTRA_TURNO_ENCERRADO,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
            'cgm_contra_turno_ativo' => null,
        ]);

        $this->assertDatabaseHas('alunos', [
            'id' => $aluno->id,
            'permite_contra_turno' => false,
            'cgm_matricula_ativa' => 'CGM-CT-END',
        ]);

        // Contra-turno não bloqueia mais documentos; a ficha permanece editável no repositório.
        $this->assertDatabaseHas('avaliacao_aluno_documentos', [
            'id' => $resposta->id,
            'aluno_id' => $contraTurno->id,
        ]);
        $this->assertSame('Registro do contra turno', $informacao->fresh()->informacaoDoComponente((int) $pauta->componente_curricular_id)['texto'] ?? null);
    }

    public function test_voltar_para_turma_anterior_registra_novo_remanejamento(): void
    {
        $escola = $this->criarEscola('Escola Retorno');
        $serie = Serie::query()->create(['codigo' => 'SER-RET', 'nome' => '2o Ano']);
        $turmaA = $this->criarTurma($escola, 'A', $serie, 'manha');
        $turmaB = $this->criarTurma($escola, 'B', $serie, 'tarde');

        $alunoInicial = Aluno::query()->create([
            'nome' => 'Aluno Retorno',
            'cgm' => 'CGM-RETORNO',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaA->id,
        ]);

        $alunoRemanejado = app(AlunoMovimentacaoService::class)->remanejar($alunoInicial, $turmaB->id);
        $alunoRetornado = app(AlunoMovimentacaoService::class)->voltarParaTurmaAnterior($alunoRemanejado);

        $this->assertDatabaseHas('alunos', [
            'id' => $alunoRemanejado->id,
            'status' => Aluno::STATUS_REMANEJADO,
            'cgm_matricula_ativa' => null,
        ]);

        $this->assertDatabaseHas('alunos', [
            'id' => $alunoRetornado->id,
            'id_turma' => $turmaA->id,
            'status' => Aluno::STATUS_MATRICULADO,
            'aluno_origem_id' => $alunoRemanejado->id,
            'turma_origem_id' => $turmaB->id,
            'movimentacao_origem' => AlunoMovimentacaoService::MOVIMENTACAO_REMANEJAMENTO,
            'cgm_matricula_ativa' => 'CGM-RETORNO',
        ]);
    }

    public function test_gerar_parecer_de_transferencia_exporta_zip_e_marca_aluno_como_transferido(): void
    {
        Permission::findOrCreate('Gerar Parecer de Transferencia');

        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
            'id_escola' => $escola->id,
        ]);
        $usuario->givePermissionTo('Gerar Parecer de Transferencia');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Transferencia',
            'cgm' => 'CGM-TRF',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        $respostaOrigem = $this->criarRespostaDocumento([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $aluno->id,
            'alternativa_id' => $alternativa->id,
            'respondido_em' => now(),
        ]);
        $informacaoOrigem = $this->criarInformacaoDocumento([
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $aluno->id,
            'componente_curricular_id' => $pauta->componente_curricular_id,
            'informacoes_complementares' => 'Informacao complementar antes da transferencia',
        ]);

        $escolaPendente = $this->criarEscola('Escola Destino Pendente');
        $turmaPendente = $this->criarTurma($escolaPendente, 'Pendente', $serie);
        $avaliacao->turmas()->attach($turmaPendente->id);

        $alunoPendente = app(AlunoMovimentacaoService::class)->criarMatricula([
            'nome' => 'Aluno Transferencia',
            'cgm' => 'CGM-TRF',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaPendente->id,
        ]);

        $this->assertSame(Aluno::STATUS_PENDENTE, $alunoPendente->status);

        $this->mock(AvaliacaoDocumentoExportService::class, function ($mock): void {
            $mock
                ->shouldReceive('gerarPdfAluno')
                ->once()
                ->andReturn([
                    'filename' => 'parecer-transferencia-avaliacao.pdf',
                    'contents' => '%PDF-1.4 teste',
                ]);
        });

        $response = app(AlunoTransferenciaParecerService::class)->exportarETransferir($aluno, $usuario);
        $zipPath = $response->getFile()->getPathname();
        $zip = new \ZipArchive();

        $this->assertSame('application/zip', $response->headers->get('content-type'));
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame('parecer-transferencia-avaliacao.pdf', $zip->getNameIndex(0));
        $zip->close();
        @unlink($zipPath);

        $this->assertDatabaseHas('alunos', [
            'id' => $aluno->id,
            'status' => Aluno::STATUS_TRANSFERIDO,
            'cgm_matricula_ativa' => null,
        ]);

        $this->assertDatabaseHas('alunos', [
            'id' => $alunoPendente->id,
            'status' => Aluno::STATUS_MATRICULADO,
            'cgm_matricula_ativa' => 'CGM-TRF',
            'pendencia_origem_aluno_id' => null,
            'aluno_origem_id' => $aluno->id,
        ]);

        $this->assertDatabaseMissing('avaliacao_aluno_documentos', [
            'avaliacao_id' => $avaliacao->id,
            'aluno_id' => $aluno->id,
        ]);

        $this->assertDocumentoNoAluno(
            (int) $avaliacao->id,
            (int) $alunoPendente->id,
            (int) $turmaPendente->id,
            (int) $pauta->id,
            (int) $alternativa->id
        );
        $this->assertHistoricoMovimentacao(
            (int) $avaliacao->id,
            (int) $aluno->id,
            (int) $alunoPendente->id,
            AlunoMovimentacaoService::MOVIMENTACAO_TRANSFERENCIA
        );

        $documentoDestino = AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $avaliacao->id)
            ->where('aluno_id', $alunoPendente->id)
            ->first();
        $this->assertNotNull($documentoDestino);
        $this->assertSame(
            'Informacao complementar antes da transferencia',
            $documentoDestino->informacaoDoComponente((int) $pauta->componente_curricular_id)['texto'] ?? null
        );
    }

    public function test_documento_unico_segue_aluno_sem_bloqueio_pos_rebind(): void
    {
        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Documento Unico',
            'cgm' => 'CGM-DOC-UNICO',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        $this->criarRespostaDocumento([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $aluno->id,
            'alternativa_id' => $alternativa->id,
            'respondido_em' => now(),
        ]);

        $novoAluno = app(AlunoMovimentacaoService::class)->remanejar($aluno, $turmaDestino->id);

        $this->assertEquals(1, AvaliacaoAlunoDocumento::query()->where('avaliacao_id', $avaliacao->id)->whereIn('aluno_id', [$aluno->id, $novoAluno->id])->count());
        $this->assertDocumentoNoAluno((int) $avaliacao->id, (int) $novoAluno->id, (int) $turmaDestino->id, (int) $pauta->id, (int) $alternativa->id);
        $this->assertDatabaseHas('avaliacao_aluno_documentos_historico', [
            'aluno_origem_id' => $aluno->id,
            'aluno_destino_id' => $novoAluno->id,
            'movimentacao_tipo' => AlunoMovimentacaoService::MOVIMENTACAO_REMANEJAMENTO,
        ]);
    }

    public function test_parecer_transferencia_salva_resposta_marcada_no_slideover_antes_de_transferir(): void
    {
        Permission::findOrCreate('Gerar Parecer de Transferencia');

        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Gerar Parecer de Transferencia');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Parecer Resposta',
            'cgm' => 'CGM-RESP',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        $this->mock(AvaliacaoDocumentoExportService::class, function ($mock): void {
            $mock
                ->shouldReceive('gerarPdfAluno')
                ->once()
                ->andReturn([
                    'filename' => 'parecer-transferencia-avaliacao.pdf',
                    'contents' => '%PDF-1.4 teste',
                ]);
        });

        $component = Livewire::actingAs($usuario)
            ->test(ParecerTransferenciaAluno::class)
            ->call('selecionarAluno', $aluno->id)
            ->set("respostasParecer.{$avaliacao->id}.{$pauta->id}", (string) $alternativa->id);

        $this->assertDocumentoNoAluno((int) $avaliacao->id, (int) $aluno->id, (int) $turmaOrigem->id, (int) $pauta->id, (int) $alternativa->id);

        $component->call('gerarParecerTransferencia');

        $this->assertDocumentoNoAluno((int) $avaliacao->id, (int) $aluno->id, (int) $turmaOrigem->id, (int) $pauta->id, (int) $alternativa->id);

        $this->assertDatabaseHas('alunos', [
            'id' => $aluno->id,
            'status' => Aluno::STATUS_TRANSFERIDO,
            'cgm_matricula_ativa' => null,
        ]);
    }

    public function test_parecer_transferencia_salva_observacao_obrigatoria_e_informacao_complementar(): void
    {
        Permission::findOrCreate('Gerar Parecer de Transferencia');

        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();
        $alternativa->update(['tem_observacao' => true]);
        $alternativa->refresh();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Gerar Parecer de Transferencia');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Parecer Observacao',
            'cgm' => 'CGM-OBS',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        $this->mock(AvaliacaoDocumentoExportService::class, function ($mock): void {
            $mock
                ->shouldReceive('gerarPdfAluno')
                ->once()
                ->andReturn([
                    'filename' => 'parecer-transferencia-avaliacao.pdf',
                    'contents' => '%PDF-1.4 teste',
                ]);
        });

        $component = Livewire::actingAs($usuario)
            ->test(ParecerTransferenciaAluno::class)
            ->call('selecionarAluno', $aluno->id)
            ->set("respostasParecer.{$avaliacao->id}.{$pauta->id}", (string) $alternativa->id)
            ->set("observacoesParecer.{$avaliacao->id}.{$pauta->id}", 'Observacao obrigatoria registrada.')
            ->set("informacoesComplementaresParecer.{$avaliacao->id}.{$pauta->componente_curricular_id}", 'Informacao complementar do componente.');

        $this->assertDocumentoNoAluno(
            (int) $avaliacao->id,
            (int) $aluno->id,
            (int) $turmaOrigem->id,
            (int) $pauta->id,
            (int) $alternativa->id,
            'Observacao obrigatoria registrada.'
        );

        $documento = AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $avaliacao->id)
            ->where('aluno_id', $aluno->id)
            ->first();
        $this->assertSame(
            'Informacao complementar do componente.',
            $documento?->informacaoDoComponente((int) $pauta->componente_curricular_id)['texto'] ?? null
        );

        $component->call('gerarParecerTransferencia');
    }

    public function test_parecer_transferencia_nao_transfere_sem_observacao_obrigatoria(): void
    {
        Permission::findOrCreate('Gerar Parecer de Transferencia');

        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();
        $alternativa->update(['tem_observacao' => true]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Gerar Parecer de Transferencia');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Parecer Sem Observacao',
            'cgm' => 'CGM-SEM-OBS',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        Livewire::actingAs($usuario)
            ->test(ParecerTransferenciaAluno::class)
            ->call('selecionarAluno', $aluno->id)
            ->set("respostasParecer.{$avaliacao->id}.{$pauta->id}", (string) $alternativa->id)
            ->call('gerarParecerTransferencia');

        $this->assertDatabaseMissing('alunos', [
            'id' => $aluno->id,
            'status' => Aluno::STATUS_TRANSFERIDO,
        ]);

        $this->assertDatabaseHas('avaliacao_aluno_documentos', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $aluno->id,
            'alternativa_id' => $alternativa->id,
        ]);
        $doc = AvaliacaoAlunoDocumento::query()->where('avaliacao_id', $avaliacao->id)->where('aluno_id', $aluno->id)->first();
        $this->assertNotNull($doc);
        $resp = $doc->respostaDaPauta((int) $pauta->id);
        $this->assertTrue($resp === null || blank($resp['observacao'] ?? null));
    }

    public function test_parecer_transferencia_filtra_por_escola_serie_turma_e_pendencia_sem_professor(): void
    {
        Permission::findOrCreate('Gerar Parecer de Transferencia');

        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Gerar Parecer de Transferencia');

        $alunoOrigem = Aluno::query()->create([
            'nome' => 'Aluno Turma Origem',
            'cgm' => 'CGM-FILTRO-1',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        Aluno::query()->create([
            'nome' => 'Aluno Turma Destino',
            'cgm' => 'CGM-FILTRO-2',
            'data_nascimento' => '2015-01-02',
            'id_turma' => $turmaDestino->id,
        ]);

        $this->criarRespostaDocumento([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $alunoOrigem->id,
            'alternativa_id' => $alternativa->id,
            'respondido_em' => now(),
        ]);

        $outraEscola = $this->criarEscola('Outra Escola Filtro');
        $outraSerie = Serie::query()->create(['codigo' => 'SER-FILTRO', 'nome' => '2o Ano Filtro']);
        $outraTurma = $this->criarTurma($outraEscola, 'C', $outraSerie);
        $avaliacao->turmas()->attach($outraTurma->id);

        Aluno::query()->create([
            'nome' => 'Aluno Outra Escola',
            'cgm' => 'CGM-FILTRO-3',
            'data_nascimento' => '2015-01-03',
            'id_turma' => $outraTurma->id,
        ]);

        Livewire::actingAs($usuario)
            ->test(ParecerTransferenciaAluno::class)
            ->set('escolaFiltro', (string) $outraEscola->id)
            ->assertSee('Aluno Outra Escola')
            ->assertDontSee('Aluno Turma Origem')
            ->set('escolaFiltro', '')
            ->set('serieFiltro', (string) $outraSerie->id)
            ->assertSee('Aluno Outra Escola')
            ->assertDontSee('Aluno Turma Destino')
            ->set('serieFiltro', '')
            ->set('turmaFiltro', (string) $turmaDestino->id)
            ->assertSee('Aluno Turma Destino')
            ->assertDontSee('Aluno Turma Origem')
            ->set('turmaFiltro', '')
            ->set('semProfessorFiltro', '1')
            ->assertSee('Aluno Turma Destino')
            ->assertDontSee('Aluno Turma Origem');
    }

    public function test_parecer_transferencia_tem_paginacao_e_abre_slideover_com_avaliacoes(): void
    {
        Permission::findOrCreate('Gerar Parecer de Transferencia');

        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Gerar Parecer de Transferencia');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Parecer Slideover',
            'cgm' => 'CGM-SLIDE',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Aluno::query()->create([
                'nome' => 'Aluno Paginacao '.$i,
                'cgm' => 'CGM-PAG-'.$i,
                'data_nascimento' => '2015-01-0'.$i,
                'id_turma' => $turmaOrigem->id,
            ]);
        }

        $turmaSemAvaliacao = $this->criarTurma($escola, 'Sem Avaliacao', $serie);
        Aluno::query()->create([
            'nome' => 'Aluno Sem Avaliacao',
            'cgm' => 'CGM-SEM-AVAL',
            'data_nascimento' => '2015-01-07',
            'id_turma' => $turmaSemAvaliacao->id,
        ]);

        $this->criarRespostaDocumento([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $aluno->id,
            'alternativa_id' => $alternativa->id,
            'respondido_em' => now(),
        ]);

        Livewire::actingAs($usuario)
            ->test(ParecerTransferenciaAluno::class)
            ->set('porPagina', '5')
            ->assertSee('6 resultado(s)')
            ->assertDontSee('Aluno Sem Avaliacao')
            ->assertSee('Mostrando')
            ->assertSee('de 6')
            ->call('selecionarAluno', $aluno->id)
            ->assertSet('slideoverAberto', true)
            ->assertSet("avaliacoesExpandidas.{$avaliacao->id}", false)
            ->assertSee('Aluno Parecer Slideover')
            ->assertDontSee('Pauta de teste')
            ->assertSee('Gerar Parecer de Transferência')
            ->assertSee('100%')
            ->call('alternarAvaliacaoParecer', $avaliacao->id)
            ->assertSet("avaliacoesExpandidas.{$avaliacao->id}", true)
            ->assertSee('Pauta de teste');
    }

    public function test_modal_parecer_na_tela_de_alunos_autosalva_e_inicia_recolhido(): void
    {
        Permission::findOrCreate('Gerar Parecer de Transferencia');

        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Gerar Parecer de Transferencia');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Modal Parecer',
            'cgm' => 'CGM-MODAL-PARECER',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        Livewire::actingAs($usuario)
            ->test(AlunoParecerTransferenciaModal::class, ['alunoId' => $aluno->id])
            ->assertSet("avaliacoesExpandidas.{$avaliacao->id}", false)
            ->assertDontSee('Pauta de teste')
            ->call('alternarAvaliacaoParecer', $avaliacao->id)
            ->assertSet("avaliacoesExpandidas.{$avaliacao->id}", true)
            ->assertSee('Pauta de teste')
            ->set("respostasParecer.{$avaliacao->id}.{$pauta->id}", (string) $alternativa->id);

        $this->assertDocumentoNoAluno((int) $avaliacao->id, (int) $aluno->id, (int) $turmaOrigem->id, (int) $pauta->id, (int) $alternativa->id);
    }

    public function test_tela_de_alunos_lista_todos_e_parecer_aparece_para_historico_com_avaliacao(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Gerar Parecer de Transferencia');

        [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa] = $this->criarCenarioAvaliacaoDuasTurmas();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Alunos', 'Gerar Parecer de Transferencia']);

        $alunoRespondido = Aluno::query()->create([
            'nome' => 'Aluno Respondido',
            'cgm' => 'CGM-RESPONDIDO',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);

        $this->criarRespostaDocumento([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaOrigem->id,
            'aluno_id' => $alunoRespondido->id,
            'alternativa_id' => $alternativa->id,
            'respondido_em' => now(),
        ]);

        $alunoHistorico = Aluno::query()->create([
            'nome' => 'Aluno Historico Com Avaliacao',
            'cgm' => 'CGM-HIST-LISTA',
            'data_nascimento' => '2015-01-02',
            'id_turma' => $turmaOrigem->id,
            'status' => Aluno::STATUS_TRANSFERIDO,
        ]);

        $alunoPendenteSemProfessor = Aluno::query()->create([
            'nome' => 'Aluno Pendente Sem Professor',
            'cgm' => 'CGM-SEM-PROF',
            'data_nascimento' => '2015-01-03',
            'id_turma' => $turmaDestino->id,
        ]);

        $turmaSemAvaliacao = $this->criarTurma($escola, 'Sem Avaliacao Alunos', $serie);
        $alunoSemAvaliacao = Aluno::query()->create([
            'nome' => 'Aluno Sem Avaliacao Na Lista',
            'cgm' => 'CGM-LISTA-SEM-AVAL',
            'data_nascimento' => '2015-01-04',
            'id_turma' => $turmaSemAvaliacao->id,
        ]);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->assertCanSeeTableRecords([$alunoRespondido, $alunoHistorico, $alunoPendenteSemProfessor, $alunoSemAvaliacao])
            ->assertTableActionVisible('parecer_transferencia', $alunoHistorico)
            ->assertTableActionHidden('parecer_transferencia', $alunoSemAvaliacao)
            ->mountTableAction('parecer_transferencia', $alunoHistorico)
            ->assertSee('Aluno Historico Com Avaliacao')
            ->unmountTableAction()
            ->filterTable('sem_professor', '1')
            ->assertCanSeeTableRecords([$alunoPendenteSemProfessor])
            ->assertCanNotSeeTableRecords([$alunoRespondido]);
    }

    private function criarCenarioAvaliacaoDuasTurmas(string $turnoOrigem = 'manha', string $turnoDestino = 'manha'): array
    {
        $escola = $this->criarEscola('Escola Avaliacao');
        $serie = Serie::query()->create(['codigo' => 'SER'.uniqid(), 'nome' => '1o Ano '.uniqid()]);
        $turmaOrigem = $this->criarTurma($escola, 'A', $serie, $turnoOrigem);
        $turmaDestino = $this->criarTurma($escola, 'B', $serie, $turnoDestino);
        $this->criarResponsaveisParecer($escola, $turmaOrigem);

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer '.uniqid(), 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo '.uniqid(), 'status' => true]);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP'.uniqid(), 'nome' => 'Componente']);
        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'vai_no_documento' => true,
            'status' => true,
        ]);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta de teste',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = Avaliacao::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'nome' => 'Avaliacao '.uniqid(),
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach([$turmaOrigem->id, $turmaDestino->id]);

        return [$escola, $serie, $turmaOrigem, $turmaDestino, $avaliacao, $pauta, $alternativa];
    }

    private function criarResponsaveisParecer(Escola $escola, Turma $turma): void
    {
        $diretora = Servidor::query()->create([
            'id_escola' => $escola->id,
            'nome' => 'Diretora Parecer',
            'email' => uniqid().'@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $diretora->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::direcaoPadrao()->id,
            'id_escola' => $escola->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'portaria' => 'PORT-DIR-TRF',
            'principal' => true,
            'data_inicio' => now()->subDay()->toDateString(),
        ]);

        $coordenadora = Servidor::query()->create([
            'id_escola' => $escola->id,
            'nome' => 'Coordenadora Parecer',
            'email' => uniqid().'@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $coordenacao = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $coordenadora->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::coordenacaoPadrao()->id,
            'id_escola' => $escola->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'portaria' => 'PORT-COORD-TRF',
            'data_inicio' => now()->subDay()->toDateString(),
        ]);
        ServidorFuncaoTurma::query()->create([
            'servidor_funcao_administrativa_id' => $coordenacao->id,
            'turma_id' => $turma->id,
            'principal' => true,
            'status' => ServidorFuncaoTurma::STATUS_ATIVO,
            'data_inicio' => now()->subDay()->toDateString(),
        ]);
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome.uniqid()), 0, 5)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
    }

    private function criarTurma(Escola $escola, string $sufixo, ?Serie $serie = null, string $turno = 'manha'): Turma
    {
        $serie ??= Serie::query()->create([
            'codigo' => 'SER'.$sufixo.uniqid(),
            'nome' => 'Serie '.$sufixo,
        ]);

        return Turma::query()->create([
            'codigo' => 'TUR'.$sufixo.uniqid(),
            'nome' => $sufixo,
            'turno' => $turno,
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarRespostaDocumento(array $dados): AvaliacaoAlunoDocumento
    {
        $aluno = Aluno::query()->findOrFail($dados['aluno_id']);
        $service = app(AvaliacaoAlunoDocumentoService::class);
        $documento = $service->obterOuCriar((int) $dados['avaliacao_id'], $aluno, somentePrincipal: false);

        if (! empty($dados['alternativa_id'])) {
            $service->salvarPauta($documento, (int) $dados['pauta_id'], [
                'alternativa_id' => (int) $dados['alternativa_id'],
                'observacao' => $dados['observacao'] ?? null,
                'professor_id' => $dados['professor_id'] ?? null,
                'respondido_em' => $dados['respondido_em'] ?? now(),
            ]);
        }

        return $documento->fresh();
    }

    private function criarInformacaoDocumento(array $dados): AvaliacaoAlunoDocumento
    {
        $aluno = Aluno::query()->findOrFail($dados['aluno_id']);
        $service = app(AvaliacaoAlunoDocumentoService::class);
        $documento = $service->obterOuCriar((int) $dados['avaliacao_id'], $aluno, somentePrincipal: false);
        $service->salvarInfoComplementar(
            $documento,
            (int) $dados['componente_curricular_id'],
            $dados['informacoes_complementares'] ?? null,
            $dados['professor_id'] ?? null,
        );

        return $documento->fresh();
    }

    private function assertDocumentoNoAluno(int $avaliacaoId, int $alunoId, int $turmaId, int $pautaId, int $alternativaId, ?string $observacao = null): void
    {
        $documento = AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('aluno_id', $alunoId)
            ->where('turma_id', $turmaId)
            ->first();

        $this->assertNotNull($documento);

        $resposta = $documento->respostaDaPauta($pautaId);
        $this->assertNotNull($resposta);
        $this->assertSame($alternativaId, (int) ($resposta['alternativa_id'] ?? 0));

        if ($observacao !== null) {
            $this->assertSame($observacao, $resposta['observacao'] ?? null);
        }
    }

    private function assertHistoricoMovimentacao(int $avaliacaoId, int $alunoOrigemId, int $alunoDestinoId, string $tipo): void
    {
        $this->assertDatabaseHas('avaliacao_aluno_documentos_historico', [
            'avaliacao_id' => $avaliacaoId,
            'aluno_origem_id' => $alunoOrigemId,
            'aluno_destino_id' => $alunoDestinoId,
            'movimentacao_tipo' => $tipo,
        ]);
    }
}
