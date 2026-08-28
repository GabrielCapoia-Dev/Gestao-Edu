<?php

namespace App\Jobs;

use App\Services\Avaliacoes\AvaliacaoDocumentoCompatibilidadeProjector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProjetarAvaliacaoDocumentoCompatibilidadeJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 60;

    public function __construct(public int $avaliacaoId, public int $alunoId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return "avaliacao-documento:{$this->avaliacaoId}:{$this->alunoId}";
    }

    public function handle(AvaliacaoDocumentoCompatibilidadeProjector $projector): void
    {
        $projector->projetar($this->avaliacaoId, $this->alunoId);
    }
}
