<?php

namespace App\Support\Avaliacoes;

class AvaliacaoPerformanceContext
{
    /** @var array<string, mixed> */
    private array $dados = [];

    /** @param array<string, mixed> $dados */
    public function add(array $dados): void
    {
        $this->dados = array_replace($this->dados, array_filter($dados, fn ($valor) => $valor !== null));
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->dados;
    }
}
