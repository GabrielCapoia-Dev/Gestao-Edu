<?php

namespace Tests\Feature\Avaliacoes;

use App\Exceptions\AvaliacaoRespostaConcorrenteException;
use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;
use App\Services\Avaliacoes\AvaliacaoDashboardOnDemandQueryService;
use App\Services\Avaliacoes\AvaliacaoDocumentoReader;
use App\Services\Avaliacoes\AvaliacaoRespostaStore;
use App\Services\Avaliacoes\AvaliacaoSnapshotService;
use App\Services\Avaliacoes\AvaliacaoTurmaCicloService;
use App\Services\Avaliacoes\ParecerResponsaveisResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AvaliacaoPersistenciaRelacionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_autosave_grava_linha_pequena_e_detecta_versao_concorrente(): void
    {
        $cenario = $this->criarCenario();
        $store = app(AvaliacaoRespostaStore::class);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $versao = $store->salvarPauta(
            $cenario['avaliacao']->id,
            $cenario['turma']->id,
            $cenario['aluno'],
            $cenario['pauta']->id,
            [
                'alternativa_id' => $cenario['alternativa']->id,
                'componente_curricular_id' => $cenario['componente']->id,
                'respondido_em' => now(),
            ],
        );

        $this->assertSame(1, $versao);
        // Inclui o lock pontual da mesma resposta para serializar conflitos reais.
        $this->assertLessThanOrEqual(5, count($queries), implode(PHP_EOL, $queries));
        $this->assertDatabaseHas('avaliacao_respostas_operacionais', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'turma_avaliativa_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'pauta_id' => $cenario['pauta']->id,
            'alternativa_id' => $cenario['alternativa']->id,
            'version' => 1,
        ]);
        $this->assertDatabaseCount('avaliacao_aluno_documentos', 0);

        $store->salvarPauta(
            $cenario['avaliacao']->id,
            $cenario['turma']->id,
            $cenario['aluno'],
            $cenario['pauta']->id,
            ['alternativa_id' => $cenario['alternativa']->id, 'observacao' => 'Atualizada'],
            1,
            ['alternativa_id' => $cenario['alternativa']->id, 'observacao' => null],
        );

        $alternativaConcorrente = Alternativa::query()->create([
            'tipo_avaliacao_id' => $cenario['tipo']->id,
            'nome' => 'Em desenvolvimento',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $store->salvarPauta(
            $cenario['avaliacao']->id,
            $cenario['turma']->id,
            $cenario['aluno'],
            $cenario['pauta']->id,
            ['alternativa_id' => $alternativaConcorrente->id],
            1,
            ['alternativa_id' => $cenario['alternativa']->id],
        );
        $this->assertDatabaseHas('avaliacao_respostas_operacionais', [
            'alternativa_id' => $alternativaConcorrente->id,
            'observacao' => 'Atualizada',
            'version' => 3,
        ]);

        $this->expectException(AvaliacaoRespostaConcorrenteException::class);
        $store->salvarPauta(
            $cenario['avaliacao']->id,
            $cenario['turma']->id,
            $cenario['aluno'],
            $cenario['pauta']->id,
            ['alternativa_id' => $cenario['alternativa']->id, 'observacao' => 'Sobrescrita'],
            1,
            ['alternativa_id' => $cenario['alternativa']->id, 'observacao' => null],
        );
    }

    public function test_dashboard_relacional_nao_expande_json(): void
    {
        $cenario = $this->criarCenario();
        app(AvaliacaoRespostaStore::class)->salvarPauta(
            $cenario['avaliacao']->id,
            $cenario['turma']->id,
            $cenario['aluno'],
            $cenario['pauta']->id,
            ['alternativa_id' => $cenario['alternativa']->id, 'componente_curricular_id' => $cenario['componente']->id],
        );

        $sql = app(AvaliacaoDashboardOnDemandQueryService::class)
            ->respostas([$cenario['avaliacao']->id], true)
            ->toSql();

        $this->assertStringNotContainsStringIgnoringCase('json_table', $sql);
        $this->assertStringNotContainsStringIgnoringCase('json_each', $sql);
        $this->assertStringNotContainsString('avaliacao_aluno_documentos', $sql);
        $this->assertStringContainsString('avaliacao_respostas_operacionais', $sql);
        $this->assertSame(1, app(AvaliacaoDashboardOnDemandQueryService::class)
            ->respostas([$cenario['avaliacao']->id], true)->count());
    }

    public function test_conclusao_publica_snapshot_remove_operacional_e_reabertura_reidrata(): void
    {
        $cenario = $this->criarCenario();
        $ator = User::factory()->create();
        app(AvaliacaoRespostaStore::class)->salvarPauta(
            $cenario['avaliacao']->id,
            $cenario['turma']->id,
            $cenario['aluno'],
            $cenario['pauta']->id,
            ['alternativa_id' => $cenario['alternativa']->id, 'componente_curricular_id' => $cenario['componente']->id],
        );

        $resolver = Mockery::mock(ParecerResponsaveisResolver::class);
        $resolver->shouldReceive('resolver')->once()->andReturn($this->responsaveis($cenario['turma']));
        $this->app->instance(ParecerResponsaveisResolver::class, $resolver);

        $ciclo = AvaliacaoTurmaCiclo::query()->firstOrFail();
        $evento = app(AvaliacaoSnapshotService::class)->concluir($ciclo, $ator);

        $this->assertNotNull($evento->publicado_em);
        $this->assertDatabaseCount('avaliacao_respostas_operacionais', 0);
        $this->assertDatabaseCount('avaliacao_turma_tokens_escrita', 0);
        $this->assertDatabaseHas('avaliacao_aluno_snapshots', [
            'evento_id' => $evento->id,
            'aluno_id' => $cenario['aluno']->id,
            'total_respostas' => 1,
        ]);
        $this->assertSame(AvaliacaoTurmaCiclo::STATUS_CONCLUIDA, $ciclo->fresh()->status);
        $this->assertSame('snapshot', app(AvaliacaoDocumentoReader::class)
            ->ler($cenario['avaliacao']->id, $cenario['aluno'], $cenario['turma'])->origem);

        app(AvaliacaoSnapshotService::class)->reabrir($ciclo->fresh(), $ator, 'Correção pedagógica autorizada.');

        $this->assertDatabaseHas('avaliacao_respostas_operacionais', [
            'ciclo_id' => $ciclo->id,
            'aluno_id' => $cenario['aluno']->id,
            'pauta_id' => $cenario['pauta']->id,
            'alternativa_id' => $cenario['alternativa']->id,
        ]);
        $this->assertDatabaseHas('avaliacao_aluno_snapshots', ['evento_id' => $evento->id]);
        $this->assertSame(AvaliacaoTurmaCiclo::STATUS_REABERTA, $ciclo->fresh()->status);
        $this->assertSame(Avaliacao::STATUS_ATIVA, $cenario['avaliacao']->fresh()->status);
    }

    /** @return array<string, mixed> */
    private function criarCenario(bool $sincronizarCiclo = true): array
    {
        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer relacional', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Período relacional', 'status' => true]);
        $serie = Serie::query()->create(['codigo' => 'SER-REL', 'nome' => 'Série relacional']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-REL', 'nome' => 'Componente relacional']);
        $escola = Escola::query()->create(['codigo' => 'ESC-REL', 'nome' => 'Escola relacional', 'email' => 'relacional@teste.local']);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-REL', 'nome' => 'A', 'turno' => 'manha',
            'id_serie' => $serie->id, 'id_escola' => $escola->id,
        ]);
        $aluno = Aluno::query()->create([
            'nome' => 'Aluno relacional', 'cgm' => 'CGM-REL', 'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id, 'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id, 'texto' => 'Pauta relacional', 'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id, 'status' => true,
        ]);
        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id, 'nome' => 'Atende', 'tem_observacao' => false, 'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);
        $avaliacao = Avaliacao::query()->create([
            'tipo_avaliacao_id' => $tipo->id, 'periodo_avaliacao_id' => $periodo->id,
            'nome' => 'Avaliação relacional', 'data_inicio' => now()->subDay(), 'data_fim' => now()->addDay(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->sync([$pauta->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);

        if ($sincronizarCiclo) {
            app(AvaliacaoTurmaCicloService::class)->sincronizarAvaliacao($avaliacao);
        }

        return compact('tipo', 'periodo', 'serie', 'componente', 'escola', 'turma', 'aluno', 'pauta', 'alternativa', 'avaliacao');
    }

    private function responsaveis(Turma $turma): array
    {
        return [
            'v' => 1,
            'capturado_em' => now()->toIso8601String(),
            'escola' => ['id' => (int) $turma->id_escola, 'nome' => 'Escola relacional'],
            'turma' => ['id' => (int) $turma->id, 'nome' => (string) $turma->nome, 'turno' => (string) $turma->turno],
            'serie' => ['id' => (int) $turma->id_serie, 'nome' => 'Série relacional'],
            'diretor' => ['pessoa_id' => 1, 'nome' => 'Diretor', 'portaria' => '1/2026'],
            'coordenador' => ['pessoa_id' => 2, 'nome' => 'Coordenador', 'portaria' => '2/2026'],
        ];
    }
}
