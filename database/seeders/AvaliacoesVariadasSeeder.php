<?php

namespace Database\Seeders;

use App\Models\Avaliacao;
use App\Models\Pauta;
use App\Models\Turma;
use Illuminate\Database\Seeder;

class AvaliacoesVariadasSeeder extends Seeder
{
    public function run(): void
    {
        $referencia = now()->startOfDay();

        $avaliacoes = [
            [
                'nome' => '[Seed Avaliacoes] Diagnostica Bimestre 1',
                'data_inicio' => $referencia->copy()->subDays(20),
                'data_fim' => $referencia->copy()->addDays(12),
                'status' => Avaliacao::STATUS_ATIVA,
            ],
            [
                'nome' => '[Seed Avaliacoes] Formativa Intermediaria',
                'data_inicio' => $referencia->copy()->subDays(8),
                'data_fim' => $referencia->copy()->addDays(20),
                'status' => Avaliacao::STATUS_ATIVA,
            ],
            [
                'nome' => '[Seed Avaliacoes] Sondagem de Entrada',
                'data_inicio' => $referencia->copy()->subDays(45),
                'data_fim' => $referencia->copy()->subDays(30),
                'status' => Avaliacao::STATUS_ENCERRADA,
            ],
            [
                'nome' => '[Seed Avaliacoes] Consolidacao Trimestral',
                'data_inicio' => $referencia->copy()->subDays(90),
                'data_fim' => $referencia->copy()->subDays(60),
                'status' => Avaliacao::STATUS_ENCERRADA,
            ],
            [
                'nome' => '[Seed Avaliacoes] Recuperacao Parcial',
                'data_inicio' => $referencia->copy()->addDays(10),
                'data_fim' => $referencia->copy()->addDays(25),
                'status' => Avaliacao::STATUS_INATIVA,
            ],
            [
                'nome' => '[Seed Avaliacoes] Simulado de Competencias',
                'data_inicio' => $referencia->copy()->addDays(35),
                'data_fim' => $referencia->copy()->addDays(50),
                'status' => Avaliacao::STATUS_INATIVA,
            ],
            [
                'nome' => '[Seed Avaliacoes] Avaliacao Extraordinaria',
                'data_inicio' => $referencia->copy()->subDays(15),
                'data_fim' => $referencia->copy()->addDays(5),
                'status' => Avaliacao::STATUS_CANCELADA,
            ],
            [
                'nome' => '[Seed Avaliacoes] Projeto Integrador',
                'data_inicio' => $referencia->copy()->addDays(55),
                'data_fim' => $referencia->copy()->addDays(75),
                'status' => Avaliacao::STATUS_CANCELADA,
            ],
            [
                'nome' => '[Seed Avaliacoes] Diagnostica Bimestre 2',
                'data_inicio' => $referencia->copy()->addDays(3),
                'data_fim' => $referencia->copy()->addDays(30),
                'status' => Avaliacao::STATUS_ATIVA,
            ],
            [
                'nome' => '[Seed Avaliacoes] Fechamento Semestral',
                'data_inicio' => $referencia->copy()->subDays(180),
                'data_fim' => $referencia->copy()->subDays(150),
                'status' => Avaliacao::STATUS_ENCERRADA,
            ],
        ];

        $turmasIds = Turma::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $pautasIds = Pauta::query()
            ->where('status', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $criadas = 0;
        $atualizadas = 0;

        foreach ($avaliacoes as $indice => $dados) {
            $avaliacao = Avaliacao::query()->updateOrCreate(
                ['nome' => $dados['nome']],
                [
                    'data_inicio' => $dados['data_inicio']->toDateString(),
                    'data_fim' => $dados['data_fim']->toDateString(),
                    'status' => $dados['status'],
                ]
            );

            $pautasParaVincular = $this->recorteCircularIds($pautasIds, $indice, min(5, count($pautasIds)));
            $turmasParaVincular = $this->recorteCircularIds($turmasIds, $indice, min(3, count($turmasIds)));

            if ($pautasParaVincular !== []) {
                $avaliacao->pautas()->sync($pautasParaVincular);
            }

            if ($turmasParaVincular !== []) {
                $avaliacao->turmas()->sync($turmasParaVincular);
            }

            if ($avaliacao->wasRecentlyCreated) {
                $criadas++;
            } else {
                $atualizadas++;
            }
        }

        if ($this->command) {
            $this->command->info('AvaliacoesVariadasSeeder executado com sucesso.');
            $this->command->line('Avaliacoes criadas: ' . $criadas . '.');
            $this->command->line('Avaliacoes atualizadas: ' . $atualizadas . '.');
            $this->command->line('Total processado: ' . ($criadas + $atualizadas) . '.');
        }
    }

    private function recorteCircularIds(array $ids, int $indiceBase, int $tamanho): array
    {
        if ($ids === [] || $tamanho <= 0) {
            return [];
        }

        $total = count($ids);

        if ($tamanho >= $total) {
            return $ids;
        }

        $inicio = $indiceBase % $total;
        $selecionados = [];

        for ($i = 0; $i < $tamanho; $i++) {
            $selecionados[] = $ids[($inicio + $i) % $total];
        }

        return array_values(array_unique($selecionados));
    }
}

