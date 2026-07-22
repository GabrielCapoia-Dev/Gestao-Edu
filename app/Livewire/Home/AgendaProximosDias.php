<?php

namespace App\Livewire\Home;

use App\Filament\Admin\Resources\EventosCalendario\EventoCalendarioCreateAction;
use App\Filament\Admin\Pages\GerenciarEventos;
use App\Models\Enums\ListaPermissoes;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\ProfilePreviewService;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;

class AgendaProximosDias extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public int $quantidadeDias = 5;

    public string $escopoAgenda = 'pessoal';

    public ?string $erro = null;

    /** @var list<string> */
    public array $diasExpandidos = [];

    public function mount(): void
    {
        $this->quantidadeDias = $this->normalizarQuantidadeDias(
            (int) config('dashboard.calendar.default_days', 5),
        );
    }

    public function updatedQuantidadeDias(int|string $days): void
    {
        $this->quantidadeDias = $this->normalizarQuantidadeDias((int) $days);
        $this->diasExpandidos = [];
    }

    public function definirEscopo(string $escopo): void
    {
        abort_unless(in_array($escopo, ['pessoal', 'rede'], true), 422);

        if ($escopo === 'rede') {
            $user = app(ProfilePreviewService::class)->effectiveUser();
            abort_unless($user && $this->podeVisualizarRede($user), 403);
        }

        $this->escopoAgenda = $escopo;
        $this->diasExpandidos = [];
    }

    public function alternarDia(string $date): void
    {
        $context = $this->makeContext();

        if (! $context) {
            return;
        }

        $day = CarbonImmutable::parse($date)->startOfDay();

        if ($day->lt($context->inicio->startOfDay()) || $day->gt($context->fim->startOfDay())) {
            abort(404);
        }

        $this->diasExpandidos = in_array($date, $this->diasExpandidos, true)
            ? array_values(array_diff($this->diasExpandidos, [$date]))
            : [...$this->diasExpandidos, $date];
    }

    public function recarregar(): void
    {
        $this->erro = null;
    }

    public function novoEventoAction(): CreateAction
    {
        return EventoCalendarioCreateAction::make(
            'novoEvento',
            app(ProfilePreviewService::class)->effectiveUser(),
        );
    }

    public function render(): View
    {
        $context = $this->makeContext();
        $result = null;

        if ($context) {
            try {
                $result = app(CalendarEventAggregator::class)->aggregate($context);
            } catch (\Throwable $exception) {
                report($exception);
                $this->erro = 'Não foi possível carregar a agenda agora. Tente novamente.';
            }
        }

        $days = [];

        if ($context && $result) {
            for ($date = $context->inicio->startOfDay(); $date->lte($context->fim); $date = $date->addDay()) {
                $dayStart = $date->startOfDay();
                $dayEnd = $date->endOfDay();
                $allEvents = array_values(array_filter(
                    $result->events,
                    static fn ($event): bool => $event->inicio->lte($dayEnd) && $event->fim->gte($dayStart),
                ));
                $dateKey = $date->toDateString();
                $expanded = in_array($dateKey, $this->diasExpandidos, true);
                $days[] = [
                    'date' => $date,
                    'events' => $expanded ? $allEvents : array_slice($allEvents, 0, 3),
                    'remaining' => $expanded ? 0 : max(0, count($allEvents) - 3),
                    'expanded' => $expanded,
                ];
            }
        }

        return view('livewire.home.agenda-proximos-dias', [
            'days' => $days,
            'sourceErrors' => $result?->errors ?? [],
            'truncated' => $result?->truncated ?? false,
            'periodOptions' => $this->periodOptions(),
            'manageUrl' => $this->manageUrl($context),
            'podeVisualizarRede' => $context
                ? $this->podeVisualizarRede($context->user)
                : false,
            'podeCriarEvento' => $context
                ? Gate::forUser($context->user)->allows('create', EventoCalendario::class)
                : false,
        ]);
    }

    public function placeholder(): View
    {
        return view('livewire.home.agenda-proximos-dias-placeholder');
    }

    private function makeContext(): ?CalendarQueryContext
    {
        $this->erro = null;
        $user = app(ProfilePreviewService::class)->effectiveUser();

        if (! $user) {
            $this->erro = 'Não foi possível identificar o usuário autenticado.';

            return null;
        }

        try {
            $timezone = (string) config('dashboard.calendar.timezone', config('app.timezone'));
            $inicio = CarbonImmutable::today($timezone);
            $quantidadeDias = $this->normalizarQuantidadeDias($this->quantidadeDias);
            $userContext = app(DashboardUserContextFactory::class)->make($user);

            return new CalendarQueryContext(
                user: $user,
                userContext: $userContext,
                inicio: $inicio->startOfDay(),
                fim: $inicio->addDays($quantidadeDias - 1)->endOfDay(),
                redeCompleta: $this->escopoAgenda === 'rede',
            );
        } catch (\Throwable $exception) {
            $this->erro = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Informe um período válido para consultar a agenda.';

            return null;
        }
    }

    /** @return list<int> */
    private function periodOptions(): array
    {
        $maxDays = max(1, (int) config('dashboard.calendar.max_days', 30));
        $options = array_values(array_unique(array_filter(
            array_map('intval', (array) config('dashboard.calendar.period_options', [5, 10, 15, 20, 25, 30])),
            static fn (int $days): bool => $days > 0 && $days <= $maxDays,
        )));
        sort($options);

        return $options !== [] ? $options : [min(5, $maxDays)];
    }

    private function normalizarQuantidadeDias(int $days): int
    {
        $options = $this->periodOptions();

        return in_array($days, $options, true) ? $days : $options[0];
    }

    private function manageUrl(?CalendarQueryContext $context): ?string
    {
        if (! $context || ! Gate::forUser($context->user)->allows('viewAny', EventoCalendario::class)) {
            return null;
        }

        try {
            return GerenciarEventos::getUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    private function podeVisualizarRede(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::VisualizarAgendaDeTodaARede->label());
    }
}
