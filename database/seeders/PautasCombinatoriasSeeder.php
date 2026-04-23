<?php

namespace Database\Seeders;

use App\Models\Alternativa;
use App\Models\ComponenteCurricular;
use App\Models\Pauta;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class PautasCombinatoriasSeeder extends Seeder
{
    public function run(): void
    {
        $alternativasPorTipo = Alternativa::query()
            ->where('status', true)
            ->whereNotNull('tipo_avaliacao_id')
            ->orderBy('id')
            ->get(['id', 'tipo_avaliacao_id'])
            ->groupBy('tipo_avaliacao_id')
            ->map(fn ($itens): array => $itens
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all());

        if ($alternativasPorTipo->isEmpty()) {
            if ($this->command) {
                $this->command->warn('PautasCombinatoriasSeeder: nenhuma alternativa ativa com tipo encontrada.');
            }

            return;
        }

        $tiposDisponiveis = TipoAvaliacao::query()
            ->where('status', true)
            ->whereIn('id', $alternativasPorTipo->keys()->all())
            ->orderBy('id')
            ->get(['id', 'nome']);

        if ($tiposDisponiveis->isEmpty()) {
            if ($this->command) {
                $this->command->warn('PautasCombinatoriasSeeder: nenhum tipo ativo com alternativas encontrado.');
            }

            return;
        }

        $componentes = ComponenteCurricular::query()
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $series = Serie::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo']);

        if ($componentes->isEmpty() || $series->isEmpty()) {
            if ($this->command) {
                $this->command->warn('PautasCombinatoriasSeeder: componentes ou series indisponiveis para gerar pautas.');
            }

            return;
        }

        $tiposSondagemIds = $this->obterTiposSondagemIds();
        $componentesPortuguesIds = $this->obterComponentesPortuguesIds();
        $seriesDoTerceiroAoQuintoIds = $this->obterSeriesDoTerceiroAoQuintoAnoIds();
        $seriesPadraoIds = $series
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $seriesPorComponente = $this->obterSeriesPorComponente($seriesPadraoIds);

        $resultadoCriacao = $this->criarPautasBase(
            tiposDisponiveis: $tiposDisponiveis,
            componentes: $componentes,
            seriesPorComponente: $seriesPorComponente,
            seriesPadraoIds: $seriesPadraoIds,
            tiposSondagemIds: $tiposSondagemIds,
            componentesPortuguesIds: $componentesPortuguesIds,
            seriesDoTerceiroAoQuintoIds: $seriesDoTerceiroAoQuintoIds
        );

        $pautas = Pauta::query()
            ->orderBy('id')
            ->get(['id', 'tipo_avaliacao_id', 'componente_curricular_id', 'serie_id']);

        if ($pautas->isEmpty()) {
            if ($this->command) {
                $this->command->warn('PautasCombinatoriasSeeder: nenhuma pauta encontrada para sincronizar.');
            }

            return;
        }

        $pautasComVinculos = 0;
        $pautasSemTipo = 0;
        $pautasSondagemBloqueadas = 0;
        $totalVinculos = 0;

        foreach ($pautas as $pauta) {
            $tipoAvaliacaoId = $pauta->tipo_avaliacao_id ? (int) $pauta->tipo_avaliacao_id : null;

            if (is_null($tipoAvaliacaoId)) {
                $pauta->alternativas()->sync([]);
                $pautasSemTipo++;
                continue;
            }

            $alternativasIds = $alternativasPorTipo->get($tipoAvaliacaoId, []);

            if ($alternativasIds === []) {
                $pauta->alternativas()->sync([]);
                continue;
            }

            if (in_array($tipoAvaliacaoId, $tiposSondagemIds, true)) {
                $componenteId = $pauta->componente_curricular_id ? (int) $pauta->componente_curricular_id : null;
                $serieId = $pauta->serie_id ? (int) $pauta->serie_id : null;

                $ehComponentePortugues = ! is_null($componenteId)
                    && in_array($componenteId, $componentesPortuguesIds, true);
                $ehSerieDoTerceiroAoQuinto = ! is_null($serieId)
                    && in_array($serieId, $seriesDoTerceiroAoQuintoIds, true);

                if (! $ehComponentePortugues || ! $ehSerieDoTerceiroAoQuinto) {
                    $alternativasIds = [];
                    $pautasSondagemBloqueadas++;
                }
            }

            $pauta->alternativas()->sync($alternativasIds);

            if ($alternativasIds !== []) {
                $pautasComVinculos++;
                $totalVinculos += count($alternativasIds);
            }
        }

        if ($this->command) {
            $this->command->info('PautasCombinatoriasSeeder executado com sucesso.');
            $this->command->line('Pautas processadas: ' . $pautas->count() . '.');
            $this->command->line('Pautas criadas: ' . $resultadoCriacao['criadas'] . '.');
            $this->command->line('Pautas atualizadas/reactivadas: ' . $resultadoCriacao['atualizadas'] . '.');
            $this->command->line('Pautas com alternativas sincronizadas: ' . $pautasComVinculos . '.');
            $this->command->line('Pautas sem tipo de avaliacao (limpas): ' . $pautasSemTipo . '.');
            $this->command->line('Pautas bloqueadas pela regra de Sondagem: ' . $pautasSondagemBloqueadas . '.');
            $this->command->line('Total de vinculos pauta-alternativa sincronizados: ' . $totalVinculos . '.');
        }
    }

    /**
     * @param array<int, array<int>> $seriesPorComponente
     * @param array<int> $seriesPadraoIds
     * @param array<int> $tiposSondagemIds
     * @param array<int> $componentesPortuguesIds
     * @param array<int> $seriesDoTerceiroAoQuintoIds
     * @return array{criadas: int, atualizadas: int}
     */
    private function criarPautasBase(
        Collection $tiposDisponiveis,
        Collection $componentes,
        array $seriesPorComponente,
        array $seriesPadraoIds,
        array $tiposSondagemIds,
        array $componentesPortuguesIds,
        array $seriesDoTerceiroAoQuintoIds
    ): array {
        $criadas = 0;
        $atualizadas = 0;

        $templatesComponente = [
            'Dominio dos objetivos centrais de %s.',
            'Aplicacao pratica dos conteudos de %s.',
            'Evolucao nas habilidades de %s ao longo do periodo.',
        ];
        $templatesGeraisSerie = [
            'Participacao e engajamento do aluno durante as aulas.',
            'Autonomia, organizacao e cumprimento de combinados pedagogicos.',
        ];
        $templatesGeraisTipo = [
            'Acompanhamento global da aprendizagem no periodo letivo.',
        ];
        $templatesSondagem = [
            'Sondagem diagnostica de leitura e compreensao textual.',
            'Sondagem de escrita, ortografia e producao textual.',
            'Sondagem de fluencia e interpretacao em lingua portuguesa.',
        ];

        foreach ($tiposDisponiveis as $tipo) {
            $tipoId = (int) $tipo->id;
            $tipoNome = trim((string) $tipo->nome);
            $ehSondagem = $this->ehTipoSondagem($tipoId, $tiposSondagemIds);

            if ($ehSondagem) {
                if ($componentesPortuguesIds === [] || $seriesDoTerceiroAoQuintoIds === []) {
                    continue;
                }

                $componentesPortugues = $componentes
                    ->filter(fn (ComponenteCurricular $componente): bool => in_array((int) $componente->id, $componentesPortuguesIds, true))
                    ->values();

                foreach ($componentesPortugues as $componente) {
                    $componenteId = (int) $componente->id;
                    $seriesPermitidas = array_values(array_intersect(
                        $seriesPorComponente[$componenteId] ?? $seriesPadraoIds,
                        $seriesDoTerceiroAoQuintoIds
                    ));

                    if ($seriesPermitidas === []) {
                        $seriesPermitidas = $seriesDoTerceiroAoQuintoIds;
                    }

                    foreach ($seriesPermitidas as $serieId) {
                        foreach ($templatesSondagem as $template) {
                            $texto = sprintf('[Seed Pauta] %s - %s', $tipoNome, $template);
                            $foiCriada = $this->registrarPauta(
                                tipoAvaliacaoId: $tipoId,
                                texto: $texto,
                                componenteCurricularId: $componenteId,
                                serieId: (int) $serieId
                            );

                            if ($foiCriada) {
                                $criadas++;
                            } else {
                                $atualizadas++;
                            }
                        }
                    }
                }

                continue;
            }

            foreach ($componentes as $componente) {
                $componenteId = (int) $componente->id;
                $componenteNome = trim((string) $componente->nome);
                $seriesDoComponente = $seriesPorComponente[$componenteId] ?? $seriesPadraoIds;

                foreach ($seriesDoComponente as $serieId) {
                    foreach ($templatesComponente as $template) {
                        $texto = sprintf('[Seed Pauta] %s - %s', $tipoNome, sprintf($template, $componenteNome));
                        $foiCriada = $this->registrarPauta(
                            tipoAvaliacaoId: $tipoId,
                            texto: $texto,
                            componenteCurricularId: $componenteId,
                            serieId: (int) $serieId
                        );

                        if ($foiCriada) {
                            $criadas++;
                        } else {
                            $atualizadas++;
                        }
                    }
                }
            }

            foreach ($seriesPadraoIds as $serieId) {
                foreach ($templatesGeraisSerie as $template) {
                    $texto = sprintf('[Seed Pauta] %s - %s', $tipoNome, $template);
                    $foiCriada = $this->registrarPauta(
                        tipoAvaliacaoId: $tipoId,
                        texto: $texto,
                        componenteCurricularId: null,
                        serieId: (int) $serieId
                    );

                    if ($foiCriada) {
                        $criadas++;
                    } else {
                        $atualizadas++;
                    }
                }
            }

            foreach ($templatesGeraisTipo as $template) {
                $texto = sprintf('[Seed Pauta] %s - %s', $tipoNome, $template);
                $foiCriada = $this->registrarPauta(
                    tipoAvaliacaoId: $tipoId,
                    texto: $texto,
                    componenteCurricularId: null,
                    serieId: null
                );

                if ($foiCriada) {
                    $criadas++;
                } else {
                    $atualizadas++;
                }
            }
        }

        return [
            'criadas' => $criadas,
            'atualizadas' => $atualizadas,
        ];
    }

    private function registrarPauta(
        int $tipoAvaliacaoId,
        string $texto,
        ?int $componenteCurricularId,
        ?int $serieId
    ): bool {
        $pauta = Pauta::query()->updateOrCreate(
            [
                'tipo_avaliacao_id' => $tipoAvaliacaoId,
                'texto' => trim($texto),
                'componente_curricular_id' => $componenteCurricularId,
                'serie_id' => $serieId,
            ],
            ['status' => true]
        );

        if (! $pauta->status) {
            $pauta->status = true;
            $pauta->save();
        }

        return (bool) $pauta->wasRecentlyCreated;
    }

    /**
     * @param array<int> $seriesPadraoIds
     * @return array<int, array<int>>
     */
    private function obterSeriesPorComponente(array $seriesPadraoIds): array
    {
        $seriesPorComponente = [];

        $turmas = Turma::query()
            ->whereNotNull('id_serie')
            ->with(['componentes:id'])
            ->get(['id', 'id_serie']);

        foreach ($turmas as $turma) {
            $serieId = (int) $turma->id_serie;

            foreach ($turma->componentes as $componente) {
                $componenteId = (int) $componente->id;
                $seriesPorComponente[$componenteId] = $seriesPorComponente[$componenteId] ?? [];
                $seriesPorComponente[$componenteId][] = $serieId;
            }
        }

        foreach ($seriesPorComponente as $componenteId => $seriesIds) {
            $ids = collect($seriesIds)
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $seriesPorComponente[$componenteId] = $ids !== [] ? $ids : $seriesPadraoIds;
        }

        return $seriesPorComponente;
    }

    /**
     * @param array<int> $tiposSondagemIds
     */
    private function ehTipoSondagem(int $tipoAvaliacaoId, array $tiposSondagemIds): bool
    {
        return $tipoAvaliacaoId > 0 && in_array($tipoAvaliacaoId, $tiposSondagemIds, true);
    }

    /**
     * @return array<int>
     */
    private function obterTiposSondagemIds(): array
    {
        return TipoAvaliacao::query()
            ->where('status', true)
            ->get(['id', 'nome'])
            ->filter(fn (TipoAvaliacao $tipo): bool => str_contains(
                $this->normalizarTexto((string) $tipo->nome),
                'sondagem'
            ))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int>
     */
    private function obterComponentesPortuguesIds(): array
    {
        return ComponenteCurricular::query()
            ->get(['id', 'nome'])
            ->filter(function (ComponenteCurricular $componente): bool {
                $nomeNormalizado = $this->normalizarTexto((string) $componente->nome);

                return str_contains($nomeNormalizado, 'portugues')
                    || str_contains($nomeNormalizado, 'lingua port')
                    || (bool) preg_match('/\bport\b/', $nomeNormalizado);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int>
     */
    private function obterSeriesDoTerceiroAoQuintoAnoIds(): array
    {
        return Serie::query()
            ->get(['id', 'nome', 'codigo'])
            ->filter(function (Serie $serie): bool {
                $ano = $this->extrairAnoDaSerie((string) $serie->nome, (string) ($serie->codigo ?? ''));

                return ! is_null($ano) && $ano >= 3 && $ano <= 5;
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function extrairAnoDaSerie(string $nome, string $codigo): ?int
    {
        $nomeNormalizado = $this->normalizarTexto($nome);
        $codigoNormalizado = $this->normalizarTexto($codigo);

        if (preg_match('/\b([1-9]|1[0-2])\s*o?\s*ano\b/', $nomeNormalizado, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/\bs([1-9]|1[0-2])a\b/', $codigoNormalizado, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/\b([1-9]|1[0-2])\b/', $nomeNormalizado, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function normalizarTexto(string $valor): string
    {
        $texto = trim($valor);

        if ($texto === '') {
            return '';
        }

        $textoAscii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $textoNormalizado = $textoAscii !== false ? $textoAscii : $texto;
        $textoNormalizado = strtolower($textoNormalizado);
        $textoNormalizado = preg_replace('/[^a-z0-9]+/', ' ', $textoNormalizado) ?? $textoNormalizado;

        return trim($textoNormalizado);
    }
}
