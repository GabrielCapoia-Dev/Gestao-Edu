<?php

namespace App\Support\Dashboard\Calendar;

final readonly class CalendarAggregationResult
{
    /**
     * @param list<CalendarEventData> $events
     * @param array<string, string> $errors
     */
    public function __construct(
        public array $events,
        public array $errors,
        public bool $truncated,
    ) {}
}
