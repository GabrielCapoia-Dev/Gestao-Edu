<?php

namespace App\Contracts\Dashboard;

use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarEventDetailData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;

interface CalendarEventSource
{
    public function key(): string;

    public function supports(CalendarQueryContext $context): bool;

    /** @return iterable<CalendarEventData> */
    public function events(CalendarQueryContext $context): iterable;

    public function detail(CalendarQueryContext $context, string $reference): ?CalendarEventDetailData;
}
