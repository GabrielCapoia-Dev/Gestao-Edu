<?php

namespace App\Support\Avaliacoes;

final readonly class AvaliacaoDashboardProgressData
{
    public function __construct(
        public string $consolidacaoStatus,
        public ?float $percentual,
    ) {}

    public function emAtualizacao(): bool
    {
        return $this->consolidacaoStatus !== 'consolidado';
    }
}
