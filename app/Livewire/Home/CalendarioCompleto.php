<?php

namespace App\Livewire\Home;

use App\Models\Enums\ListaPermissoes;
use App\Models\User;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\ProfilePreviewService;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

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
        abort_unless(in_array($visualizacao, ['mes', 'semana'], true), 422);

        $this->visualizacao = $visualizacao;
    }

    public function navegar(int $direcao): void
    {
        abort_unless(in_array($direcao, [-1, 1], true), 422);

        $referencia = CarbonImmutable::parse($this->referencia, $this->timezone());
        $this->referencia = ($this->visualizacao === 'semana'
            ? $referencia->addWeeks($direcao)
            : $referencia->addMonthsNoOverflow($direcao))->toDateString();
    }

    public function irParaHoje(): void
    {
        $this->referencia = CarbonImmutable::today($this->timezone())->toDateString();
    }

    public function render(): View
    {
        $user = $this->usuarioAutorizado();
        $referencia = CarbonImmutable::parse($this->referencia, $this->timezone());
        [$inicio, $fim] = $this->intervalo($referencia);
        $days = [];
        $result = null;
        $this->erro = null;

        try {
            $context = new CalendarQueryContext(
                user: $user,
                userContext: app(DashboardUserContextFactory::class)->make($user),
                inicio: $inicio,
                fim: $fim,
                redeCompleta: true,
                manutencaoSomenteEscolasUsuario: true,
            );
            $result = app(CalendarEventAggregator::class)->aggregate($context);

            for ($date = $inicio->startOfDay(); $date->lte($fim); $date = $date->addDay()) {
                $dayStart = $date->startOfDay();
                $dayEnd = $date->endOfDay();
                $days[] = [
                    'date' => $date,
                    'events' => array_values(array_filter(
                        $result->events,
                        static fn ($event): bool => $event->inicio->lte($dayEnd)
                            && $event->fim->gte($dayStart),
                    )),
                ];
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->erro = 'Não foi possível carregar o calendário agora.';
        }

        return view('livewire.home.calendario-completo', [
            'days' => $days,
            'offsetInicial' => $this->visualizacao === 'mes' ? $inicio->dayOfWeekIso - 1 : 0,
            'tituloPeriodo' => $this->tituloPeriodo($inicio, $fim),
            'sourceErrors' => $result?->errors ?? [],
            'truncated' => $result?->truncated ?? false,
        ]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function intervalo(CarbonImmutable $referencia): array
    {
        if ($this->visualizacao === 'semana') {
            return [$referencia->startOfWeek()->startOfDay(), $referencia->endOfWeek()->endOfDay()];
        }

        return [$referencia->startOfMonth()->startOfDay(), $referencia->endOfMonth()->endOfDay()];
    }

    private function tituloPeriodo(CarbonImmutable $inicio, CarbonImmutable $fim): string
    {
        if ($this->visualizacao === 'semana') {
            return $inicio->format('d/m/Y').' a '.$fim->format('d/m/Y');
        }

        return ucfirst($inicio->locale('pt_BR')->translatedFormat('F \d\e Y'));
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
