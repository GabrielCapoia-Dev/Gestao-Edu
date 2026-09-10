<?php

namespace App\Services\Avaliacoes;

use App\Models\Avaliacao;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoRespostaOperacional;
use App\Models\AvaliacaoSnapshotEvento;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AvaliacaoEstruturaService
{
    /**
     * @param  array<int, int>  $pautaIds
     * @param  array<int, int>  $turmaIds
     * @param  array<int, int>  $serieIds
     * @param  array<int, int>  $componenteIds
     * @param  array<int, array{pauta_id:int,alternativa_id:int}>  $alternativasOverride
     */
    public function validarAlteracao(
        Avaliacao $avaliacao,
        array $pautaIds,
        array $turmaIds,
        array $serieIds,
        array $componenteIds,
        array $alternativasOverride = [],
        ?bool $possuiDados = null,
    ): void {
        if (! ($possuiDados ?? $this->possuiDados($avaliacao))) {
            return;
        }

        $comparacoes = [
            'pautas' => [$avaliacao->pautas()->pluck('pautas.id')->all(), $pautaIds],
            'turmas' => [$avaliacao->turmas()->pluck('turmas.id')->all(), $turmaIds],
            'séries' => [$avaliacao->series()->pluck('series.id')->all(), $serieIds],
            'componentes' => [$avaliacao->componentes()->pluck('componentes_curriculares.id')->all(), $componenteIds],
        ];

        foreach ($comparacoes as $rotulo => [$atual, $novo]) {
            sort($atual);
            sort($novo);
            if ($atual !== $novo) {
                throw new RuntimeException("Não é possível alterar {$rotulo} depois da primeira resposta ou snapshot. Crie uma nova avaliação para mudar a estrutura.");
            }
        }

        $atuais = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->get(['pauta_id', 'alternativa_id'])
            ->map(fn ($item): string => ((int) $item->pauta_id).':'.((int) $item->alternativa_id))
            ->sort()
            ->values()
            ->all();
        $novas = collect($alternativasOverride)
            ->map(fn (array $item): string => ((int) $item['pauta_id']).':'.((int) $item['alternativa_id']))
            ->sort()
            ->values()
            ->all();

        $removidas = array_values(array_diff($atuais, $novas));

        if ($removidas !== []) {
            throw new RuntimeException('Não é possível remover alternativas da avaliação depois da primeira resposta ou snapshot. Você ainda pode adicionar novas alternativas.');
        }
    }

    public function possuiDados(Avaliacao $avaliacao): bool
    {
        return AvaliacaoRespostaOperacional::query()->where('avaliacao_id', (int) $avaliacao->id)->exists()
            || AvaliacaoInformacaoOperacional::query()->where('avaliacao_id', (int) $avaliacao->id)->exists()
            || $avaliacao->documentosAluno()->where(function ($query): void {
                $query->where('total_pautas_respondidas', '>', 0)
                    ->orWhere('total_infos_complementares', '>', 0);
            })->exists()
            || AvaliacaoSnapshotEvento::query()->where('avaliacao_id', (int) $avaliacao->id)->exists();
    }
}
