<?php

namespace App\Support\Dashboard\Calendar;

use App\Models\Enums\DashboardPrioridade;
use Carbon\CarbonImmutable;

final readonly class CalendarEventData
{
    public function __construct(
        public string $id,
        public string $source,
        public string $reference,
        public string $titulo,
        public ?string $resumo,
        public CarbonImmutable $inicio,
        public CarbonImmutable $fim,
        public bool $diaInteiro,
        public string $categoria,
        public string $categoriaLabel,
        public ?string $assunto,
        public ?string $status,
        public ?string $statusLabel,
        public DashboardPrioridade $prioridade,
        public ?float $progresso,
        public string $cor,
        public ?int $escolaId,
        public ?string $escola,
        public ?int $setorId,
        public ?string $setor,
        public string $origem,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public ?int $transporteEstimado = null,
        public ?string $local = null,
        /** @var list<string> */
        public array $transporteAlocacoes = [],
        public ?string $corDestaque = null,
    ) {}

    public function precisaTransporte(): bool
    {
        return $this->transporteEstimado !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'reference' => $this->reference,
            'titulo' => $this->titulo,
            'resumo' => $this->resumo,
            'inicio' => $this->inicio->toIso8601String(),
            'fim' => $this->fim->toIso8601String(),
            'dia_inteiro' => $this->diaInteiro,
            'categoria' => $this->categoria,
            'categoria_label' => $this->categoriaLabel,
            'assunto' => $this->assunto,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'prioridade' => $this->prioridade->value,
            'prioridade_label' => $this->prioridade->label(),
            'progresso' => $this->progresso,
            'cor' => $this->cor,
            'escola_id' => $this->escolaId,
            'escola' => $this->escola,
            'setor_id' => $this->setorId,
            'setor' => $this->setor,
            'origem' => $this->origem,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'transporte_estimado' => $this->transporteEstimado,
            'local' => $this->local,
            'transporte_alocacoes' => $this->transporteAlocacoes,
            'cor_destaque' => $this->corDestaque,
        ];
    }
}
