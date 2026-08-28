<?php

namespace Tests\Feature\Avaliacoes;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;
use App\Services\Avaliacoes\AvaliacaoDocumentoReader;
use App\Services\Avaliacoes\AvaliacaoRespostaStore;
use App\Services\Avaliacoes\AvaliacaoTurmaCicloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvaliacaoPersistenciaLazyTest extends TestCase
{
    use RefreshDatabase;

    public function test_primeira_leitura_da_turma_converte_json_uma_unica_vez(): void
    {
        $cenario = $this->criarCenario();
        $documento = $this->criarDocumentoLegado($cenario, $cenario['alternativa']);

        $store = app(AvaliacaoRespostaStore::class);
        $respostas = $store->respostasDaAvaliacaoParaAlunos(
            (int) $cenario['avaliacao']->id,
            [(int) $cenario['aluno']->id],
        );

        $this->assertSame(
            (int) $cenario['alternativa']->id,
            (int) $respostas->get((int) $cenario['aluno']->id)?->get((int) $cenario['pauta']->id)['alternativa_id'],
        );
        $this->assertDatabaseHas('avaliacao_respostas_operacionais', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'aluno_id' => $cenario['aluno']->id,
            'pauta_id' => $cenario['pauta']->id,
            'alternativa_id' => $cenario['alternativa']->id,
        ]);
        $this->assertDatabaseHas('avaliacao_turma_ciclos', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'turma_avaliativa_id' => $cenario['turma']->id,
            'legado_documentos_migrados' => 1,
        ]);
        $this->assertNotNull(
            $cenario['avaliacao']->fresh()->turmas()->first()?->id,
        );

        $store->respostasDaAvaliacaoParaAlunos(
            (int) $cenario['avaliacao']->id,
            [(int) $cenario['aluno']->id],
        );

        $this->assertDatabaseCount('avaliacao_respostas_operacionais', 1);
        $this->assertDatabaseHas('avaliacao_aluno_documentos', ['id' => $documento->id]);
    }

    public function test_exportacao_de_ciclo_ainda_nao_inicializado_continua_lendo_json_legado(): void
    {
        $cenario = $this->criarCenario();
        $documento = $this->criarDocumentoLegado($cenario, $cenario['alternativa']);

        $reader = app(AvaliacaoDocumentoReader::class);
        $dados = $reader->ler(
            (int) $cenario['avaliacao']->id,
            $cenario['aluno'],
            $cenario['turma'],
            false,
        );

        $this->assertSame('json_legado', $dados->origem);
        $this->assertSame(
            (int) $cenario['alternativa']->id,
            (int) $dados->pautas()[(string) $cenario['pauta']->id]['alternativa_id'],
        );
        $this->assertDatabaseHas('avaliacao_aluno_documentos', ['id' => $documento->id]);
        $this->assertNull(
            \App\Models\AvaliacaoTurmaCiclo::query()->firstOrFail()->operacional_inicializado_em,
        );
    }

    public function test_depois_da_conversao_autosave_altera_somente_relacional(): void
    {
        $cenario = $this->criarCenario();
        $documento = $this->criarDocumentoLegado($cenario, $cenario['alternativa']);
        $store = app(AvaliacaoRespostaStore::class);

        $store->respostasDaAvaliacaoParaAlunos(
            (int) $cenario['avaliacao']->id,
            [(int) $cenario['aluno']->id],
        );

        $novaAlternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $cenario['tipo']->id,
            'nome' => 'Outra resposta',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $cenario['pauta']->alternativas()->attach($novaAlternativa->id);

        $store->salvarPauta(
            (int) $cenario['avaliacao']->id,
            (int) $cenario['turma']->id,
            $cenario['aluno'],
            (int) $cenario['pauta']->id,
            [
                'alternativa_id' => (int) $novaAlternativa->id,
                'componente_curricular_id' => (int) $cenario['componente']->id,
            ],
            1,
            ['alternativa_id' => (int) $cenario['alternativa']->id],
        );

        $this->assertDatabaseHas('avaliacao_respostas_operacionais', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'aluno_id' => $cenario['aluno']->id,
            'pauta_id' => $cenario['pauta']->id,
            'alternativa_id' => $novaAlternativa->id,
        ]);

        $documento->refresh();
        $this->assertSame(
            (int) $cenario['alternativa']->id,
            (int) $documento->respostaDaPauta((int) $cenario['pauta']->id)['alternativa_id'],
        );
    }

    /** @return array<string,mixed> */
    private function criarCenario(): array
    {
        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer lazy', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Período lazy', 'status' => true]);
        $serie = Serie::query()->create(['codigo' => 'SER-LAZY', 'nome' => 'Série lazy']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-LAZY', 'nome' => 'Componente lazy']);
        $escola = Escola::query()->create([
            'codigo' => 'ESC-LAZY',
            'nome' => 'Escola lazy',
            'email' => 'lazy@teste.local',
        ]);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-LAZY',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $aluno = Aluno::query()->create([
            'nome' => 'Aluno lazy',
            'cgm' => 'CGM-LAZY',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta lazy',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = Avaliacao::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'nome' => 'Avaliação lazy',
            'data_inicio' => now()->subDay(),
            'data_fim' => now()->addDay(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->sync([$pauta->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);

        app(AvaliacaoTurmaCicloService::class)->sincronizarAvaliacao($avaliacao);

        return compact(
            'tipo',
            'periodo',
            'serie',
            'componente',
            'escola',
            'turma',
            'aluno',
            'pauta',
            'alternativa',
            'avaliacao',
        );
    }

    private function criarDocumentoLegado(array $cenario, Alternativa $alternativa): \App\Models\AvaliacaoAlunoDocumento
    {
        $service = app(AvaliacaoAlunoDocumentoService::class);
        $documento = $service->obterOuCriar(
            (int) $cenario['avaliacao']->id,
            $cenario['aluno'],
            false,
        );
        $service->salvarPauta($documento, (int) $cenario['pauta']->id, [
            'alternativa_id' => (int) $alternativa->id,
            'componente_curricular_id' => (int) $cenario['componente']->id,
        ]);

        return $documento->fresh();
    }
}
