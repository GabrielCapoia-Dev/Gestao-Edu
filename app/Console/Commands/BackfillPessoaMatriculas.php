<?php

namespace App\Console\Commands;

use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Services\PessoaConsolidacaoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPessoaMatriculas extends Command
{
    protected $signature = 'pessoas:backfill-matriculas {--dry-run : Apenas exibe o que seria alterado}';

    protected $description = 'Migra matrículas legadas do servidor para vínculos funcionais e liga perfis de professor';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $stats = [
            'grupos_consolidados' => 0,
            'pessoas_removidas' => 0,
            'vinculos_realocados' => 0,
            'professores_mesclados' => 0,
            'vinculos_atualizados' => 0,
            'vinculos_criados' => 0,
            'professores_vinculados' => 0,
        ];

        $consolidacao = app(PessoaConsolidacaoService::class)->consolidarDuplicatas($dryRun);
        $stats['grupos_consolidados'] = $consolidacao['grupos_processados'];
        $stats['pessoas_removidas'] = $consolidacao['pessoas_removidas'];
        $stats['vinculos_realocados'] = $consolidacao['vinculos_realocados'];
        $stats['professores_mesclados'] = $consolidacao['professores_mesclados'];

        Servidor::query()
            ->with(['servidorFuncoesAtivas.funcaoAdministrativa', 'professores'])
            ->orderBy('id')
            ->chunkById(100, function ($servidores) use ($dryRun, &$stats): void {
                foreach ($servidores as $servidor) {
                    $this->backfillServidor($servidor, $dryRun, $stats);
                }
            });

        $this->info('Backfill de pessoas/vínculos concluído.');
        $this->line("Grupos consolidados: {$stats['grupos_consolidados']}");
        $this->line("Pessoas removidas: {$stats['pessoas_removidas']}");
        $this->line("Vínculos realocados: {$stats['vinculos_realocados']}");
        $this->line("Professores mesclados: {$stats['professores_mesclados']}");
        $this->line("Vínculos criados: {$stats['vinculos_criados']}");
        $this->line("Vínculos atualizados: {$stats['vinculos_atualizados']}");
        $this->line("Professores ligados ao vínculo: {$stats['professores_vinculados']}");

        return Command::SUCCESS;
    }

    private function backfillServidor(Servidor $servidor, bool $dryRun, array &$stats): void
    {
        $funcaoProfessor = FuncaoAdministrativa::professorPadrao();
        $matriculaLegada = filled($servidor->matricula) ? (string) $servidor->matricula : null;

        if ($servidor->servidorFuncoesAtivas->isEmpty() && filled($matriculaLegada)) {
            if (! $dryRun) {
                ServidorFuncaoAdministrativa::query()->create([
                    'servidor_id' => $servidor->id,
                    'funcao_administrativa_id' => $funcaoProfessor?->id,
                    'matricula' => $matriculaLegada,
                    'id_escola' => $servidor->id_escola,
                    'setor_id' => $servidor->setor_id,
                    'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                    'origem' => 'backfill',
                ]);
            }

            $stats['vinculos_criados']++;

            return;
        }

        foreach ($servidor->servidorFuncoesAtivas as $vinculo) {
            $precisaMatricula = blank($vinculo->matricula) && filled($matriculaLegada);
            $precisaSetor = blank($vinculo->setor_id) && filled($servidor->setor_id);
            $precisaEscola = blank($vinculo->id_escola) && filled($servidor->id_escola);

            if (! $precisaMatricula && ! $precisaSetor && ! $precisaEscola) {
                continue;
            }

            if (! $dryRun) {
                $vinculo->update([
                    'matricula' => $precisaMatricula ? $matriculaLegada : $vinculo->matricula,
                    'setor_id' => $precisaSetor ? $servidor->setor_id : $vinculo->setor_id,
                    'id_escola' => $precisaEscola ? $servidor->id_escola : $vinculo->id_escola,
                ]);
            }

            $stats['vinculos_atualizados']++;
        }

        $servidor->load('servidorFuncoesAtivas.funcaoAdministrativa');

        foreach ($servidor->professores as $professor) {
            if (filled($professor->servidor_funcao_administrativa_id)) {
                continue;
            }

            $vinculo = $servidor->servidorFuncoesAtivas->first(function (ServidorFuncaoAdministrativa $item) use ($professor, $funcaoProfessor): bool {
                if ($funcaoProfessor && (int) $item->funcao_administrativa_id !== (int) $funcaoProfessor->id) {
                    return false;
                }

                if (filled($professor->id_escola) && filled($item->id_escola)) {
                    return (int) $item->id_escola === (int) $professor->id_escola;
                }

                return filled($item->matricula)
                    && filled($professor->matricula)
                    && (string) $item->matricula === (string) $professor->matricula;
            });

            if (! $vinculo) {
                continue;
            }

            if (! $dryRun) {
                DB::table('professores')
                    ->where('id', $professor->id)
                    ->update(['servidor_funcao_administrativa_id' => $vinculo->id]);
            }

            $stats['professores_vinculados']++;
        }
    }
}