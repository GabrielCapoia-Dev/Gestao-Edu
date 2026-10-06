<?php

namespace App\Livewire\Home;

use App\Models\Enums\ListaPermissoes;
use App\Models\User;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Services\Dashboard\Calendar\CalendarNetworkEventLoader;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\PedidoService;
use App\Services\ProfilePreviewService;
use App\Support\Dashboard\Calendar\CalendarAggregationResult;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CalendarioCompleto extends Component
{
    private const CATEGORIAS_MANUTENCAO = ['manutencao'];

    private const CATEGORIAS_PEDAGOGICAS = ['avaliacao', 'pedagogico'];

    public string $visualizacao = 'mes';

    public string $referencia;

    public string $escopoAgenda = 'rede';

    public ?string $erro = null;

    public function mount(): void
    {
        $this->referencia = CarbonImmutable::today($this->timezone())->toDateString();
        $this->usuarioAutorizado();
    }

    public function definirVisualizacao(string $visualizacao): void
    {
        abort_unless(in_array($visualizacao, ['ano', 'mes', 'semana'], true), 422);

        $this->visualizacao = $visualizacao;
    }

    public function definirEscopo(string $escopo): void
    {
        $user = $this->usuarioAutorizado();
        $abas = $this->montarAbas($user);

        abort_unless(collect($abas)->contains('key', $escopo), 403);

        $this->escopoAgenda = $escopo;
    }

    public function navegar(int $direcao): void
    {
        abort_unless(in_array($direcao, [-1, 1], true), 422);

        $referencia = $this->referenciaValida();
        $this->referencia = match ($this->visualizacao) {
            'ano' => $referencia->addYears($direcao)->toDateString(),
            'semana' => $referencia->addWeeks($direcao)->toDateString(),
            default => $referencia->addMonthsNoOverflow($direcao)->toDateString(),
        };
    }

    public function irParaHoje(): void
    {
        $this->referencia = CarbonImmutable::today($this->timezone())->toDateString();
    }

    public function abrirMes(string $referencia): void
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $referencia, $this->timezone());

        abort_unless($date !== false && $date->format('Y-m-d') === $referencia, 422);

        $this->referencia = $date->toDateString();
        $this->visualizacao = 'mes';
    }

    public function render(): View
    {
        $user = $this->usuarioAutorizado();
        $abas = $this->montarAbas($user);

        if (! collect($abas)->contains('key', $this->escopoAgenda)) {
            $this->escopoAgenda = 'rede';
        }

        $referencia = $this->referenciaValida();
        [$inicio, $fim] = $this->intervalo($referencia);
        $days = [];
        $months = [];
        $result = null;
        $this->erro = null;

        try {
            $result = $this->carregarEventos($user, $inicio, $fim, $this->escopoAgenda);

            if ($this->visualizacao === 'ano') {
                for ($month = $inicio->startOfMonth(); $month->lte($fim); $month = $month->addMonth()) {
                    $monthEnd = $month->endOfMonth();
                    $months[] = [
                        'date' => $month,
                        'offset' => $month->dayOfWeekIso - 1,
                        'days' => $this->montarDias($month, $monthEnd, $result->events),
                    ];
                }
            } else {
                $days = $this->montarDias($inicio, $fim, $result->events);
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->erro = 'Não foi possível carregar o calendário agora.';
        }

        return view('livewire.home.calendario-completo', [
            'days' => $days,
            'months' => $months,
            'offsetInicial' => $this->visualizacao === 'mes' ? $inicio->dayOfWeekIso - 1 : 0,
            'tituloPeriodo' => $this->tituloPeriodo($inicio, $fim),
            'sourceErrors' => $result?->errors ?? [],
            'truncated' => $result?->truncated ?? false,
            'abasAgenda' => $abas,
            'escopoAgenda' => $this->escopoAgenda,
        ]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function intervalo(CarbonImmutable $referencia): array
    {
        return match ($this->visualizacao) {
            'ano' => [$referencia->startOfYear()->startOfDay(), $referencia->endOfYear()->endOfDay()],
            'semana' => [$referencia->startOfWeek()->startOfDay(), $referencia->endOfWeek()->endOfDay()],
            default => [$referencia->startOfMonth()->startOfDay(), $referencia->endOfMonth()->endOfDay()],
        };
    }

    private function tituloPeriodo(CarbonImmutable $inicio, CarbonImmutable $fim): string
    {
        if ($this->visualizacao === 'ano') {
            return $inicio->format('Y');
        }

        if ($this->visualizacao === 'semana') {
            return $inicio->format('d/m/Y').' a '.$fim->format('d/m/Y');
        }

        return ucfirst($inicio->locale('pt_BR')->translatedFormat('F \d\e Y'));
    }

    private function carregarEventos(
        User $user,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        string $escopo,
    ): CalendarAggregationResult {
        if ($escopo === 'rede') {
            $resultadoRede = app(CalendarNetworkEventLoader::class)->load($user, $inicio, $fim);
            $resultadoPessoal = $this->agregarIntervalo($user, $inicio, $fim, 'pessoal');
            $idsPessoais = collect($resultadoPessoal->events)->pluck('id')->flip();

            return $this->filtrarResultadoPor(
                $resultadoRede,
                static fn (CalendarEventData $event): bool => ! in_array($event->categoria, self::CATEGORIAS_MANUTENCAO, true)
                    && ! $idsPessoais->has($event->id),
                $resultadoPessoal,
            );
        }

        if ($escopo === 'veiculos') {
            return $this->agregarIntervalo($user, $inicio, $fim, 'veiculos');
        }

        if ($escopo === 'manutencao'
            && app(PedidoService::class)->ehMembroDaManutencao($user)) {
            return $this->agregarIntervalo($user, $inicio, $fim, 'manutencao');
        }

        $resultadoPessoal = $this->agregarIntervalo($user, $inicio, $fim, 'pessoal');

        return match ($escopo) {
            'transporte' => $this->filtrarResultadoPor(
                $resultadoPessoal,
                static fn (CalendarEventData $event): bool => $event->precisaTransporte(),
            ),
            'manutencao' => $this->filtrarResultadoPor(
                $resultadoPessoal,
                static fn (CalendarEventData $event): bool => in_array($event->categoria, self::CATEGORIAS_MANUTENCAO, true),
            ),
            'pedagogico' => $this->filtrarResultadoPor(
                $resultadoPessoal,
                static fn (CalendarEventData $event): bool => in_array($event->categoria, self::CATEGORIAS_PEDAGOGICAS, true),
            ),
            default => $resultadoPessoal,
        };
    }

    private function agregarIntervalo(
        User $user,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        string $escopo,
    ): CalendarAggregationResult {
        $userContext = app(DashboardUserContextFactory::class)->make($user);
        $events = [];
        $errors = [];
        $truncated = false;
        $ehMembroDaManutencao = app(PedidoService::class)->ehMembroDaManutencao($user);

        for ($cursor = $inicio->startOfMonth(); $cursor->lte($fim); $cursor = $cursor->addMonth()) {
            $chunkInicio = $cursor->max($inicio)->startOfDay();
            $chunkFim = $cursor->endOfMonth()->min($fim)->endOfDay();
            $result = app(CalendarEventAggregator::class)->aggregate(new CalendarQueryContext(
                user: $user,
                userContext: $userContext,
                inicio: $chunkInicio,
                fim: $chunkFim,
                somenteReservasVeiculos: $escopo === 'veiculos',
                ignorarPedidosManutencao: $escopo === 'pessoal' && $ehMembroDaManutencao,
            ));

            foreach ($result->events as $event) {
                $events[$event->id] = $event;
            }

            $errors = [...$errors, ...$result->errors];
            $truncated = $truncated || $result->truncated;
        }

        return new CalendarAggregationResult(array_values($events), $errors, $truncated);
    }

    /** @param  callable(CalendarEventData): bool  $filtro */
    private function filtrarResultadoPor(
        CalendarAggregationResult $resultado,
        callable $filtro,
        ?CalendarAggregationResult $resultadoAdicional = null,
    ): CalendarAggregationResult {
        return new CalendarAggregationResult(
            events: array_values(array_filter($resultado->events, $filtro)),
            errors: [...$resultado->errors, ...($resultadoAdicional?->errors ?? [])],
            truncated: $resultado->truncated || ($resultadoAdicional?->truncated ?? false),
        );
    }

    /** @return list<array{key: string, label: string}> */
    private function montarAbas(User $user): array
    {
        $ehMembroDaManutencao = app(PedidoService::class)->ehMembroDaManutencao($user);
        $abas = $ehMembroDaManutencao
            ? [['key' => 'manutencao', 'label' => 'Manutenção'], ['key' => 'pessoal', 'label' => 'Para mim']]
            : [['key' => 'pessoal', 'label' => 'Para mim']];

        $abas[] = ['key' => 'rede', 'label' => 'Para a rede'];

        if ($user->hasPermissionTo(ListaPermissoes::ListarReservasVeiculos->label())) {
            $abas[] = ['key' => 'veiculos', 'label' => 'Veículos'];
        }

        $abas[] = ['key' => 'transporte', 'label' => 'Transporte'];

        if (! $ehMembroDaManutencao) {
            $abas[] = ['key' => 'manutencao', 'label' => 'Manutenção'];
        }

        $abas[] = ['key' => 'pedagogico', 'label' => 'Pedagógico'];

        return $abas;
    }

    /**
     * @param  list<CalendarEventData>  $events
     * @return list<array{date: CarbonImmutable, events: list<CalendarEventData>}>
     */
    private function montarDias(
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        array $events,
    ): array {
        $days = [];

        for ($date = $inicio->startOfDay(); $date->lte($fim); $date = $date->addDay()) {
            $dayStart = $date->startOfDay();
            $dayEnd = $date->endOfDay();
            $days[] = [
                'date' => $date,
                'events' => array_values(array_filter(
                    $events,
                    static fn (CalendarEventData $event): bool => $event->inicio->lte($dayEnd)
                        && $event->fim->gte($dayStart),
                )),
            ];
        }

        return $days;
    }

    private function referenciaValida(): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $this->referencia, $this->timezone());

        abort_unless($date !== false && $date->format('Y-m-d') === $this->referencia, 422);

        return $date;
    }

    private function usuarioAutorizado(): User
    {
        $user = app(ProfilePreviewService::class)->effectiveUser();

        abort_unless(
            $user?->hasPermissionTo(ListaPermissoes::VisualizarAgendaDeTodaARede->label()),
            403,
        );

        return $user;
    }

    private function timezone(): string
    {
        return (string) config('dashboard.calendar.timezone', config('app.timezone'));
    }
}
