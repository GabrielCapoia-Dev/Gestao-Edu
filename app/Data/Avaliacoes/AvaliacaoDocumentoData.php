<?php

namespace App\Data\Avaliacoes;

final readonly class AvaliacaoDocumentoData
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $responsaveisSnapshot
     * @param array<string, mixed> $contexto
     */
    public function __construct(
        public array $payload,
        public array $responsaveisSnapshot,
        public array $contexto,
        public string $origem,
    ) {
    }

    /** @return array<string, mixed> */
    public function pautas(): array
    {
        return is_array($this->payload['pautas'] ?? null) ? $this->payload['pautas'] : [];
    }

    /** @return array<string, mixed> */
    public function informacoes(): array
    {
        return is_array($this->payload['informacoes_complementares'] ?? null)
            ? $this->payload['informacoes_complementares']
            : [];
    }
}
