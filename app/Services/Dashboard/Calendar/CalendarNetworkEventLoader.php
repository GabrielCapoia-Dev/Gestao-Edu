<?php

namespace App\Services\Dashboard\Calendar;

use App\Models\User;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Support\Dashboard\Calendar\CalendarAggregationResult;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;

class CalendarNetworkEventLoader
{
    public function __construct(
        private readonly CalendarEventAggregator $aggregator,
        private readonly DashboardUserContextFactory $userContextFactory,
    ) {}

    public function load(
        User $user,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        bool $somenteIndicadores = false,
    ): CalendarAggregationResult {
        $userContext = $this->userContextFactory->make($user);
        $events = [];
        $errors = [];
        $truncated = false;

        for ($cursor = $inicio->startOfMonth(); $cursor->lte($fim); $cursor = $cursor->addMonth()) {
            $chunkInicio = $cursor->max($inicio)->startOfDay();
            $chunkFim = $cursor->endOfMonth()->min($fim)->endOfDay();
            $result = $this->aggregator->aggregate(new CalendarQueryContext(
                user: $user,
                userContext: $userContext,
                inicio: $chunkInicio,
                fim: $chunkFim,
                redeCompleta: true,
                manutencaoSomenteEscolasUsuario: true,
                somenteIndicadores: $somenteIndicadores,
            ));

            foreach ($result->events as $event) {
                $events[$event->id] = $event;
            }

            $errors = [...$errors, ...$result->errors];
            $truncated = $truncated || $result->truncated;
        }

        $events = array_values($events);
        usort(
            $events,
            static fn (CalendarEventData $left, CalendarEventData $right): int => [
                $left->inicio->getTimestamp(),
                $left->titulo,
                $left->id,
            ] <=> [
                $right->inicio->getTimestamp(),
                $right->titulo,
                $right->id,
            ],
        );

        return new CalendarAggregationResult($events, $errors, $truncated);
    }
}
