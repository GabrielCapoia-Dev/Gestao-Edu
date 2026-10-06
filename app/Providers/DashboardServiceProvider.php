<?php

namespace App\Providers;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Services\Dashboard\Calendar\Sources\AvaliacaoCalendarEventSource;
use App\Services\Dashboard\Calendar\Sources\ManualCalendarEventSource;
use App\Services\Dashboard\Calendar\Sources\PedidoManutencaoCalendarEventSource;
use App\Services\Dashboard\Calendar\Sources\ReservaVeiculoCalendarEventSource;
use App\Services\Dashboard\DashboardUserContextFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(DashboardUserContextFactory::class);
        $this->app->scoped(AvaliacaoCalendarEventSource::class);

        $this->app->tag([
            ManualCalendarEventSource::class,
            AvaliacaoCalendarEventSource::class,
            PedidoManutencaoCalendarEventSource::class,
            ReservaVeiculoCalendarEventSource::class,
        ], CalendarEventSource::class);

        $this->app->scoped(
            CalendarEventAggregator::class,
            fn (Application $app): CalendarEventAggregator => new CalendarEventAggregator(
                $app->tagged(CalendarEventSource::class),
            ),
        );
    }
}
