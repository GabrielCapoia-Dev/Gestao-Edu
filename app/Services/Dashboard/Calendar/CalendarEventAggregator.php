<?php

namespace App\Services\Dashboard\Calendar;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Support\Dashboard\Calendar\CalendarAggregationResult;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarEventDetailData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalendarEventAggregator
{
    /** @param iterable<CalendarEventSource> $sources */
    public function __construct(private readonly iterable $sources) {}

    public function aggregate(CalendarQueryContext $context): CalendarAggregationResult
    {
        $events = [];
        $errors = [];

        foreach ($this->sources as $source) {
            try {
                if ($context->somenteReservasVeiculos && $source->key() !== 'reservas_veiculos') {
                    continue;
                }

                if (! $source->supports($context)) {
                    continue;
                }

                foreach ($source->events($context) as $event) {
                    if ($this->matchesFilters($event, $context)) {
                        $events[$event->id] = $event;
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('Fonte do calendário indisponível.', [
                    'source' => $source->key(),
                    'user_id' => $context->user->getKey(),
                    'exception' => $exception,
                ]);

                $errors[$source->key()] = 'Não foi possível carregar esta origem agora.';
            }
        }

        $events = array_values($events);
        usort($events, static fn (CalendarEventData $left, CalendarEventData $right): int => [
            $left->inicio->getTimestamp(),
            -$left->prioridade->peso(),
            $left->categoria,
            mb_strtolower($left->titulo),
            $left->id,
        ] <=> [
            $right->inicio->getTimestamp(),
            -$right->prioridade->peso(),
            $right->categoria,
            mb_strtolower($right->titulo),
            $right->id,
        ]);

        $limit = max(1, (int) config('dashboard.calendar.max_events', 500));
        $truncated = count($events) > $limit;

        return new CalendarAggregationResult(
            events: array_slice($events, 0, $limit),
            errors: $errors,
            truncated: $truncated,
        );
    }

    public function detail(
        CalendarQueryContext $context,
        string $sourceKey,
        string $reference,
    ): ?CalendarEventDetailData {
        foreach ($this->sources as $source) {
            try {
                if ($source->key() !== $sourceKey || ! $source->supports($context)) {
                    continue;
                }

                return $source->detail($context, $reference);
            } catch (Throwable $exception) {
                Log::warning('Detalhe da fonte do calendário indisponível.', [
                    'source' => $sourceKey,
                    'reference' => $reference,
                    'user_id' => $context->user->getKey(),
                    'exception' => $exception,
                ]);

                return null;
            }
        }

        return null;
    }

    private function matchesFilters(CalendarEventData $event, CalendarQueryContext $context): bool
    {
        if ($context->somenteNaoEncerrados
            && $event->fim->lt(now((string) config('dashboard.calendar.timezone', config('app.timezone'))))) {
            return false;
        }

        if ($context->categorias !== [] && ! in_array($event->categoria, $context->categorias, true)) {
            return false;
        }

        if ($context->status !== [] && ! in_array($event->status, $context->status, true)) {
            return false;
        }

        if ($context->prioridades !== [] && ! in_array($event->prioridade->value, $context->prioridades, true)) {
            return false;
        }

        if (filled($context->assunto) && ! str_contains(
            mb_strtolower((string) $event->assunto),
            mb_strtolower(trim((string) $context->assunto)),
        )) {
            return false;
        }

        return true;
    }
}
