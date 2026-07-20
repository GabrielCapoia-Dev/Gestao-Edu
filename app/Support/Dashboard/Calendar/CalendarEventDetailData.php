<?php

namespace App\Support\Dashboard\Calendar;

final readonly class CalendarEventDetailData
{
    /** @param array<string, string|int|float|null> $metadata */
    public function __construct(
        public CalendarEventData $event,
        public ?string $descricao,
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...$this->event->toArray(),
            'descricao' => $this->descricao,
            'metadata' => $this->metadata,
        ];
    }
}
