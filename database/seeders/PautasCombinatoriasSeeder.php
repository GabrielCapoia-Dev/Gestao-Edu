<?php

namespace Database\Seeders;

use App\Models\Alternativa;
use App\Models\ComponenteCurricular;
use App\Models\Pauta;
use Illuminate\Database\Seeder;

class PautasCombinatoriasSeeder extends Seeder
{
    public function run(): void
    {
        $alternativasIds = Alternativa::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($alternativasIds === []) {
            if ($this->command) {
                $this->command->warn('PautasCombinatoriasSeeder: nenhuma alternativa encontrada. Seed interrompido.');
            }

            return;
        }

        $componentes = ComponenteCurricular::query()
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $escopos = collect([
            [
                'id' => null,
                'nome' => 'Geral',
            ],
        ])->concat(
            $componentes->map(fn (ComponenteCurricular $componente): array => [
                'id' => (int) $componente->id,
                'nome' => (string) $componente->nome,
            ])
        );

        $modelosDePauta = [
            'Desempenho na execucao das atividades propostas.',
            'Participacao do aluno durante as aulas.',
            'Autonomia para resolver tarefas e desafios.',
            'Comunicacao de ideias e argumentacao.',
        ];

        $statuses = [true, false];
        $criadas = 0;
        $atualizadas = 0;

        $escopos->each(function (array $escopo) use (
            $alternativasIds,
            $modelosDePauta,
            $statuses,
            &$criadas,
            &$atualizadas
        ): void {
            foreach ($statuses as $status) {
                foreach ($modelosDePauta as $indice => $modelo) {
                    $statusLabel = $status ? 'Ativa' : 'Inativa';
                    $texto = sprintf(
                        '[Seed Vinculos] %s | %s | Modelo %d | %s',
                        $escopo['nome'],
                        $statusLabel,
                        $indice + 1,
                        $modelo
                    );

                    $pauta = Pauta::query()->updateOrCreate(
                        [
                            'texto' => $texto,
                            'componente_curricular_id' => $escopo['id'],
                        ],
                        [
                            'status' => $status,
                        ]
                    );

                    $pauta->alternativas()->sync($alternativasIds);

                    if ($pauta->wasRecentlyCreated) {
                        $criadas++;
                    } else {
                        $atualizadas++;
                    }
                }
            }
        });

        if ($this->command) {
            $totalPautasProcessadas = $criadas + $atualizadas;
            $totalEscopos = $escopos->count();
            $totalVinculos = $totalPautasProcessadas * count($alternativasIds);

            $this->command->info('PautasCombinatoriasSeeder executado com sucesso.');
            $this->command->line('Escopos considerados: ' . $totalEscopos . ' (geral + componentes).');
            $this->command->line('Pautas criadas: ' . $criadas . '.');
            $this->command->line('Pautas atualizadas: ' . $atualizadas . '.');
            $this->command->line('Alternativas vinculadas por pauta: ' . count($alternativasIds) . '.');
            $this->command->line('Total de vinculos pauta-alternativa sincronizados: ' . $totalVinculos . '.');
        }
    }
}
