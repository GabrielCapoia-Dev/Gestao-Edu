<?php

namespace Database\Seeders;

use App\Models\Alternativa;
use App\Models\ComponenteCurricular;
use App\Models\Pauta;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use Illuminate\Database\Seeder;

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

        $tiposSondagemIds = $this->obterTiposSondagemIds();
        $componentesPortuguesIds = $this->obterComponentesPortuguesIds();
        $seriesDoTerceiroAoQuintoIds = $this->obterSeriesDoTerceiroAoQuintoAnoIds();

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
            $this->command->line('Pautas com alternativas sincronizadas: ' . $pautasComVinculos . '.');
            $this->command->line('Pautas sem tipo de avaliacao (limpas): ' . $pautasSemTipo . '.');
            $this->command->line('Pautas bloqueadas pela regra de Sondagem: ' . $pautasSondagemBloqueadas . '.');
            $this->command->line('Total de vinculos pauta-alternativa sincronizados: ' . $totalVinculos . '.');
        }
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
