<?php

namespace App\Livewire\Home;

use App\Filament\Admin\Pages\Actions\EventoCalendarioCreateAction;
use App\Models\Enums\ListaPermissoes;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\ProfilePreviewService;
use App\Support\Dashboard\Calendar\CalendarAggregationResult;
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

    private const ESCOPOS = ['pessoal', 'rede', 'veiculos', 'transporte', 'manutencao', 'pedagogico'];

    private const CATEGORIAS_MANUTENCAO = ['manutencao'];

    private const CATEGORIAS_PEDAGOGICAS = ['avaliacao', 'pedagogico'];

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
        abort_unless(in_array($escopo, self::ESCOPOS, true), 422);

        if ($escopo === 'rede') {
            $user = app(ProfilePreviewService::class)->effectiveUser();
            abort_unless($user && $this->podeVisualizarRede($user), 403);
        }

        if ($escopo === 'veiculos') {
            $user = app(ProfilePreviewService::class)->effectiveUser();
            abort_unless($user && $this->podeVisualizarVeiculos($user), 403);
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
        $context = $this->makeContext($this->escopoAgenda);
        $result = null;
        $tabsAgenda = [];

        if ($context) {
            try {
                $aggregator = app(CalendarEventAggregator::class);
                $resultadoPessoal = $aggregator->aggregate($this->contextoObrigatorio('pessoal'));
                $resultados = [
                    'pessoal' => $resultadoPessoal,
                    'transporte' => $this->filtrarResultadoPor(
                        $resultadoPessoal,
                        static fn ($evento): bool => $evento->precisaTransporte(),
                    ),
                    'manutencao' => $this->filtrarResultado($resultadoPessoal, self::CATEGORIAS_MANUTENCAO),
                    'pedagogico' => $this->filtrarResultado($resultadoPessoal, self::CATEGORIAS_PEDAGOGICAS),
                ];

                if ($this->podeVisualizarRede($context->user)) {
                    $resultados['rede'] = $this->filtrarResultadoPor(
                        $aggregator->aggregate($this->contextoObrigatorio('rede')),
                        static fn ($evento): bool => ! in_array($evento->categoria, self::CATEGORIAS_MANUTENCAO, true),
                    );
                }

                if ($this->podeVisualizarVeiculos($context->user)) {
                    $resultados['veiculos'] = $aggregator->aggregate($this->contextoObrigatorio('veiculos'));
                }

                $tabsAgenda = $this->montarAbas($context->user, $resultados);
                $escopoAgendaAtivo = collect($tabsAgenda)->contains(
                    fn (array $aba): bool => $aba['key'] === $this->escopoAgenda,
                )
                    ? $this->escopoAgenda
                    : ($tabsAgenda[0]['key'] ?? 'pessoal');
                $result = $resultados[$escopoAgendaAtivo] ?? $resultadoPessoal;
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
                    'events' => $allEvents,
                    'hasOverflow' => count($allEvents) > 3,
                    'expanded' => $expanded,
                ];
            }
        }

        return view('livewire.home.agenda-proximos-dias', [
            'days' => $days,
            'sourceErrors' => $result?->errors ?? [],
            'truncated' => $result?->truncated ?? false,
            'periodOptions' => $this->periodOptions(),
            'tabsAgenda' => $tabsAgenda,
            'escopoAgendaAtivo' => $escopoAgendaAtivo ?? $this->escopoAgenda,
            'podeCriarEvento' => $context
                ? Gate::forUser($context->user)->allows('create', EventoCalendario::class)
                : false,
            'podeVisualizarVeiculos' => $context
                ? $this->podeVisualizarVeiculos($context->user)
                : false,
        ]);
    }

    public function placeholder(): View
    {
        return view('livewire.home.agenda-proximos-dias-placeholder');
    }

    private function makeContext(?string $escopo = null): ?CalendarQueryContext
    {
        $this->erro = null;
        $user = app(ProfilePreviewService::class)->effectiveUser();
        $escopo ??= $this->escopoAgenda;

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
                redeCompleta: $escopo === 'rede',
                somenteReservasVeiculos: $escopo === 'veiculos',
                somenteNaoEncerrados: true,
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

    private function podeVisualizarRede(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::VisualizarAgendaDeTodaARede->label());
    }

    private function podeVisualizarVeiculos(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ListarReservasVeiculos->label());
    }

    private function contextoObrigatorio(string $escopo): CalendarQueryContext
    {
        return $this->makeContext($escopo)
            ?? throw new InvalidArgumentException('Não foi possível montar o contexto da agenda.');
    }

    /**
     * @param  list<string>  $categorias
     */
    private function filtrarResultado(
        CalendarAggregationResult $resultado,
        array $categorias,
    ): CalendarAggregationResult {
        return $this->filtrarResultadoPor(
            $resultado,
            static fn ($evento): bool => in_array($evento->categoria, $categorias, true),
        );
    }

    private function filtrarResultadoPor(
        CalendarAggregationResult $resultado,
        callable $filtro,
    ): CalendarAggregationResult {
        return new CalendarAggregationResult(
            events: array_values(array_filter($resultado->events, $filtro)),
            errors: $resultado->errors,
            truncated: $resultado->truncated,
        );
    }

    /**
     * @param  array<string, CalendarAggregationResult>  $resultados
     * @return list<array{key: string, label: string, count: int}>
     */
    private function montarAbas(User $user, array $resultados): array
    {
        $abas = [
            ['key' => 'pessoal', 'label' => 'Para mim'],
        ];

        if ($this->podeVisualizarRede($user)) {
            $abas[] = ['key' => 'rede', 'label' => 'Para a rede'];
        }

        if ($this->podeVisualizarVeiculos($user)) {
            $abas[] = ['key' => 'veiculos', 'label' => 'Veículos'];
        }

        $abas[] = ['key' => 'transporte', 'label' => 'Transporte'];
        $abas[] = ['key' => 'manutencao', 'label' => 'Manutenção'];
        $abas[] = ['key' => 'pedagogico', 'label' => 'Pedagógico'];

        return array_values(array_filter(array_map(
            static fn (array $aba): array => [
                ...$aba,
                'count' => count($resultados[$aba['key']]?->events ?? []),
            ],
            $abas,
        ), static fn (array $aba): bool => $aba['count'] > 0));
    }
}
