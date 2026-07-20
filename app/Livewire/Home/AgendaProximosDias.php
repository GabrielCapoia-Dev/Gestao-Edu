<?php

namespace App\Livewire\Home;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\Setor;
use App\Filament\Admin\Resources\EventosCalendario\EventoCalendarioResource;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\Dashboard\SetorPathLabelService;
use App\Services\ProfilePreviewService;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;

class AgendaProximosDias extends Component
{
    public string $dataInicial = '';

    public string $dataFinal = '';

    public int $quantidadeDias = 7;

    public string $categoria = '';

    public string $status = '';

    public string $prioridade = '';

    public string $escolaId = '';

    public string $setorId = '';

    public string $buscaEscola = '';

    public string $buscaSetor = '';

    public string $assunto = '';

    /** @var array<string, mixed>|null */
    public ?array $eventoAberto = null;

    public ?string $erro = null;

    /** @var list<string> */
    public array $diasExpandidos = [];

    public function mount(): void
    {
        $today = CarbonImmutable::today(config('dashboard.calendar.timezone'));
        $this->quantidadeDias = min(
            max(1, (int) config('dashboard.calendar.default_days', 7)),
            max(1, (int) config('dashboard.calendar.max_days', 31)),
        );
        $this->dataInicial = $today->toDateString();
        $this->dataFinal = $today->addDays($this->quantidadeDias - 1)->toDateString();
    }

    public function updatedQuantidadeDias(int|string $days): void
    {
        $days = max(1, min((int) $days, (int) config('dashboard.calendar.max_days', 31)));
        $this->quantidadeDias = $days;

        try {
            $this->dataFinal = CarbonImmutable::parse($this->dataInicial)
                ->addDays($days - 1)
                ->toDateString();
        } catch (\Throwable) {
            // A validação amigável é exibida no próximo render.
        }
    }

    public function updatedDataInicial(): void
    {
        try {
            $inicio = CarbonImmutable::parse($this->dataInicial);
            $this->dataFinal = $inicio->addDays($this->quantidadeDias - 1)->toDateString();
        } catch (\Throwable) {
            // A validação amigável é exibida no próximo render.
        }
    }

    public function abrirEvento(string $source, string $reference): void
    {
        $context = $this->makeContext();

        if (! $context) {
            return;
        }

        $detail = app(CalendarEventAggregator::class)->detail($context, $source, $reference);
        abort_unless($detail, 404);

        $this->eventoAberto = $detail->toArray();
        $this->dispatch('open-modal', id: 'agenda-event-detail');
    }

    public function fecharEvento(): void
    {
        $this->eventoAberto = null;
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

    public function limparFiltros(): void
    {
        $this->categoria = '';
        $this->status = '';
        $this->prioridade = '';
        $this->escolaId = '';
        $this->setorId = '';
        $this->buscaEscola = '';
        $this->buscaSetor = '';
        $this->assunto = '';
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

        $schoolOptions = $this->schoolOptions($context);
        $sectorOptions = $this->sectorOptions($context);

        return view('livewire.home.agenda-proximos-dias', [
            'days' => $days,
            'sourceErrors' => $result?->errors ?? [],
            'truncated' => $result?->truncated ?? false,
            'categoryOptions' => EventoCalendarioCategoria::cases(),
            'priorityOptions' => DashboardPrioridade::cases(),
            'statusOptions' => $this->statusOptions($result?->events ?? []),
            'schoolOptions' => $schoolOptions,
            'sectorOptions' => $sectorOptions,
            'showSchoolFilter' => count($schoolOptions) > 1
                || filled($this->escolaId)
                || filled($this->buscaEscola),
            'showSectorFilter' => count($sectorOptions) > 1
                || filled($this->setorId)
                || filled($this->buscaSetor),
            'maxDays' => (int) config('dashboard.calendar.max_days', 31),
            'manageUrl' => $this->manageUrl($context),
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
            $escolaId = $this->filterId($this->escolaId, 'escola');
            $setorId = $this->filterId($this->setorId, 'setor');

            if ($escolaId && ! Escola::query()->whereKey($escolaId)->where('ativo', true)->exists()) {
                throw new InvalidArgumentException('A escola selecionada não existe ou está inativa.');
            }

            if ($setorId && ! Setor::query()->whereKey($setorId)->where('ativo', true)->exists()) {
                throw new InvalidArgumentException('O setor selecionado não existe ou está inativo.');
            }

            return new CalendarQueryContext(
                user: $user,
                userContext: app(DashboardUserContextFactory::class)->make($user),
                inicio: CarbonImmutable::parse($this->dataInicial, $timezone)->startOfDay(),
                fim: CarbonImmutable::parse($this->dataFinal, $timezone)->endOfDay(),
                categorias: filled($this->categoria) ? [$this->categoria] : [],
                status: filled($this->status) ? [$this->status] : [],
                prioridades: filled($this->prioridade) ? [$this->prioridade] : [],
                escolaId: $escolaId,
                setorId: $setorId,
                assunto: filled($this->assunto) ? trim($this->assunto) : null,
            );
        } catch (\Throwable $exception) {
            $this->erro = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Informe um período válido para consultar a agenda.';

            return null;
        }
    }

    /** @param iterable<mixed> $events @return array<string, string> */
    private function statusOptions(iterable $events): array
    {
        $options = [
            'agendado' => 'Agendado',
            'em_andamento' => 'Em andamento',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
        ];

        foreach ($events as $event) {
            $options[$event->status] = $event->statusLabel;
        }

        asort($options);

        return $options;
    }

    /** @return array<int, string> */
    private function schoolOptions(?CalendarQueryContext $context): array
    {
        if (! $context) {
            return [];
        }

        $query = Escola::query()->select(['id', 'nome'])->where('ativo', true);

        if (! $context->userContext->escopoGlobal) {
            $query->whereKey($context->userContext->escolaIds);
        }

        if (filled($this->buscaEscola)) {
            $query->where('nome', 'like', '%'.trim($this->buscaEscola).'%');
        }

        $options = $query->orderBy('nome')->limit(50)->pluck('nome', 'id')->all();

        return $this->includeSelectedSchool($options, $context);
    }

    /** @return array<int, string> */
    private function sectorOptions(?CalendarQueryContext $context): array
    {
        if (! $context) {
            return [];
        }

        $query = Setor::query()->select(['id', 'nome', 'path'])->where('ativo', true);

        if (! $context->userContext->escopoGlobal) {
            $query->whereKey($context->userContext->setorVisivelIds);
        }

        if (filled($this->buscaSetor)) {
            $query->where('nome', 'like', '%'.trim($this->buscaSetor).'%');
        }

        $setores = $query->orderedTree()->limit(50)->get();
        $options = app(SetorPathLabelService::class)->labels($setores);

        return $this->includeSelectedSector($options, $context);
    }

    /** @param array<int, string> $options @return array<int, string> */
    private function includeSelectedSchool(array $options, CalendarQueryContext $context): array
    {
        $selectedId = filled($this->escolaId) ? (int) $this->escolaId : null;

        if (! $selectedId || isset($options[$selectedId])) {
            return $options;
        }

        $query = Escola::query()->whereKey($selectedId)->where('ativo', true);

        if (! $context->userContext->escopoGlobal) {
            $query->whereKey($context->userContext->escolaIds);
        }

        $selected = $query->pluck('nome', 'id')->all();

        return $selected + $options;
    }

    /** @param array<int, string> $options @return array<int, string> */
    private function includeSelectedSector(array $options, CalendarQueryContext $context): array
    {
        $selectedId = filled($this->setorId) ? (int) $this->setorId : null;

        if (! $selectedId || isset($options[$selectedId])) {
            return $options;
        }

        $query = Setor::query()->whereKey($selectedId)->where('ativo', true);

        if (! $context->userContext->escopoGlobal) {
            $query->whereKey($context->userContext->setorVisivelIds);
        }

        $selected = app(SetorPathLabelService::class)->labels($query->get());

        return $selected + $options;
    }

    private function filterId(string $value, string $label): ?int
    {
        if (blank($value)) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            throw new InvalidArgumentException("O filtro de {$label} é inválido.");
        }

        return (int) $id;
    }

    private function manageUrl(?CalendarQueryContext $context): ?string
    {
        if (! $context || ! Gate::forUser($context->user)->allows('viewAny', EventoCalendario::class)) {
            return null;
        }

        try {
            return EventoCalendarioResource::getUrl('index');
        } catch (\Throwable) {
            return null;
        }
    }
}
