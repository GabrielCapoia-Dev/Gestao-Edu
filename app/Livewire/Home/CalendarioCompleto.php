<?php

namespace App\Livewire\Home;

use App\Models\Enums\ListaPermissoes;
use App\Models\User;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\ProfilePreviewService;
use App\Support\Dashboard\Calendar\CalendarAggregationResult;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CalendarioCompleto extends Component
{
    public string $visualizacao = 'mes';

    public string $referencia;

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

    public function exportarCalendario(): StreamedResponse
    {
        $user = $this->usuarioAutorizado();
        $referencia = $this->referenciaValida();
        [$inicio, $fim] = $this->intervalo($referencia);
        $result = $this->carregarEventos($user, $inicio, $fim);
        $filename = 'calendario-rede-'.$this->identificadorPeriodo($inicio, $fim).'.csv';
        $rows = array_map(fn (CalendarEventData $event): array => [
            $event->inicio->format('d/m/Y'),
            $event->diaInteiro ? '' : $event->inicio->format('H:i'),
            $event->fim->format('d/m/Y'),
            $event->diaInteiro ? '' : $event->fim->format('H:i'),
            $event->diaInteiro ? 'Sim' : 'Não',
            $this->valorCsv($event->categoriaLabel),
            $this->valorCsv($event->titulo),
            $this->valorCsv($event->resumo),
            $this->valorCsv($event->escola),
            $this->valorCsv($event->local),
            $this->valorCsv($event->setor),
            $this->valorCsv($event->statusLabel),
            $this->valorCsv($event->solicitante),
            $this->valorCsv($event->origem),
        ], $result->events);

        return response()->streamDownload(function () use ($rows): void {
            echo "\xEF\xBB\xBF";

            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'Data inicial',
                'Hora inicial',
                'Data final',
                'Hora final',
                'Dia inteiro',
                'Categoria',
                'Evento',
                'Resumo',
                'Escola',
                'Local',
                'Setor',
                'Status',
                'Solicitante',
                'Origem',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($output, $row, ';');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function render(): View
    {
        $user = $this->usuarioAutorizado();
        $referencia = $this->referenciaValida();
        [$inicio, $fim] = $this->intervalo($referencia);
        $days = [];
        $months = [];
        $result = null;
        $this->erro = null;

        try {
            $result = $this->carregarEventos($user, $inicio, $fim);

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
    ): CalendarAggregationResult {
        $aggregator = app(CalendarEventAggregator::class);
        $userContext = app(DashboardUserContextFactory::class)->make($user);
        $events = [];
        $errors = [];
        $truncated = false;

        for ($cursor = $inicio->startOfMonth(); $cursor->lte($fim); $cursor = $cursor->addMonth()) {
            $chunkInicio = $cursor->max($inicio)->startOfDay();
            $chunkFim = $cursor->endOfMonth()->min($fim)->endOfDay();
            $result = $aggregator->aggregate(new CalendarQueryContext(
                user: $user,
                userContext: $userContext,
                inicio: $chunkInicio,
                fim: $chunkFim,
                redeCompleta: true,
                manutencaoSomenteEscolasUsuario: true,
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

    private function identificadorPeriodo(CarbonImmutable $inicio, CarbonImmutable $fim): string
    {
        return match ($this->visualizacao) {
            'ano' => 'ano-'.$inicio->format('Y'),
            'semana' => 'semana-'.$inicio->format('Y-m-d').'-a-'.$fim->format('Y-m-d'),
            default => 'mes-'.$inicio->format('Y-m'),
        };
    }

    private function valorCsv(?string $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'".$value : $value;
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
