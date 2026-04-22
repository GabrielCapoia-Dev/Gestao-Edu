<?php

namespace App\Filament\Admin\Pages;

use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class GestaoAvaliacoes extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.gestao-avaliacoes';

    protected static ?string $title = 'Gestão de Avaliações';

    protected static ?string $navigationLabel = 'Avaliações';

    protected static ?string $slug = 'avaliacoes-gestao';

    protected static ?int $navigationSort = 22;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public string $busca = '';

    public string $filtroStatus = 'todas';

    public int $porPagina = 10;

    public bool $modalAberto = false;

    public ?int $avaliacaoIdEditando = null;

    public array $form = [
        'nome' => '',
        'data_inicio' => '',
        'data_fim' => '',
        'status' => Avaliacao::STATUS_ATIVA,
        'escola_id' => 'todas',
        'componentes_ids' => [],
        'pautas_ids' => [],
    ];

    protected $queryString = [
        'busca' => ['except' => ''],
        'filtroStatus' => ['except' => 'todas'],
    ];

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo('Listar Avaliações') ?? false;
    }

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPorPagina(): void
    {
        $this->resetPage();
    }

    public function updatedFormEscolaId(): void
    {
        $componentesDisponiveis = collect(array_keys($this->componentesOptions))
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->form['componentes_ids'] = collect($this->form['componentes_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->intersect($componentesDisponiveis)
            ->values()
            ->all();

        $this->sincronizarPautasSelecionadasComFiltros();
    }

    public function updatedFormComponentesIds(): void
    {
        $this->form['componentes_ids'] = collect($this->form['componentes_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->sincronizarPautasSelecionadasComFiltros();
    }

    public function getAvaliacoesProperty(): LengthAwarePaginator
    {
        $query = Avaliacao::query()
            ->withCount(['pautas'])
            ->with([
                'pautas:id,texto,componente_curricular_id',
                'pautas.componente:id,nome',
            ]);

        if (filled($this->busca)) {
            $busca = trim($this->busca);
            $query->where(function ($subQuery) use ($busca) {
                $subQuery->where('nome', 'like', "%{$busca}%")
                    ->orWhereHas('pautas', fn ($pautaQuery) => $pautaQuery->where('texto', 'like', "%{$busca}%"))
                    ->orWhereHas('turmas', fn ($turmaQuery) => $turmaQuery->where('nome', 'like', "%{$busca}%"));
            });
        }

        if ($this->filtroStatus !== 'todas') {
            $query->where('status', $this->filtroStatus);
        }

        return $query
            ->orderByDesc('updated_at')
            ->paginate($this->porPagina);
    }

    public function getStatusOptionsProperty(): array
    {
        return Avaliacao::statusOptions();
    }

    public function getEscolasOptionsProperty(): array
    {
        return ['todas' => 'Todas as escolas'] + Escola::query()
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->mapWithKeys(fn ($nome, $id): array => [(string) $id => $nome])
            ->all();
    }

    public function getComponentesOptionsProperty(): array
    {
        $escolaId = $this->resolverEscolaIdSelecionada($this->form['escola_id'] ?? 'todas');

        return ComponenteCurricular::query()
            ->select('componentes_curriculares.id', 'componentes_curriculares.nome')
            ->join('turma_componente_professor as tcp', 'tcp.componente_curricular_id', '=', 'componentes_curriculares.id')
            ->join('turmas as t', 't.id', '=', 'tcp.turma_id')
            ->when($escolaId, fn ($query, $id) => $query->where('t.id_escola', $id))
            ->orderBy('componentes_curriculares.nome')
            ->distinct()
            ->pluck('componentes_curriculares.nome', 'componentes_curriculares.id')
            ->toArray();
    }

    public function getPautasOptionsProperty(): array
    {
        $componentesIds = collect($this->form['componentes_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return Pauta::query()
            ->with(['componente:id,nome'])
            ->withCount('alternativas')
            ->where('status', true)
            ->when(
                $componentesIds !== [],
                fn ($query) => $query->where(function ($subQuery) use ($componentesIds): void {
                    $subQuery->whereNull('componente_curricular_id')
                        ->orWhereIn('componente_curricular_id', $componentesIds);
                })
            )
            ->orderBy('texto')
            ->get()
            ->mapWithKeys(function (Pauta $pauta): array {
                $label = $pauta->texto;

                if ($pauta->componente?->nome) {
                    $label .= ' | ' . $pauta->componente->nome;
                }

                $label .= ' | ' . $pauta->alternativas_count . ' alternativas';

                return [$pauta->id => $label];
            })
            ->toArray();
    }

    public function abrirModalCriacao(): void
    {
        if (! (Auth::user()?->hasPermissionTo('Criar Avaliações') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para criar avaliações.')
                ->warning()
                ->send();

            return;
        }

        $this->resetForm();
        $this->avaliacaoIdEditando = null;
        $this->modalAberto = true;
        $this->resetValidation();
    }

    public function abrirModalEdicao(int $avaliacaoId): void
    {
        if (! (Auth::user()?->hasPermissionTo('Editar Avaliações') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para editar avaliações.')
                ->warning()
                ->send();

            return;
        }

        $avaliacao = Avaliacao::query()
            ->with(['pautas:id,componente_curricular_id', 'turmas:id,id_escola'])
            ->find($avaliacaoId);

        if (! $avaliacao) {
            Notification::make()
                ->title('Avaliação não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $escolasIds = $avaliacao->turmas
            ->pluck('id_escola')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $componentesIds = $avaliacao->pautas
            ->pluck('componente_curricular_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($componentesIds === [] && $avaliacao->turmas->isNotEmpty()) {
            $componentesIds = DB::table('turma_componente_professor')
                ->whereIn('turma_id', $avaliacao->turmas->pluck('id')->all())
                ->distinct()
                ->pluck('componente_curricular_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $this->avaliacaoIdEditando = $avaliacao->id;
        $this->form = [
            'nome' => (string) $avaliacao->nome,
            'data_inicio' => optional($avaliacao->data_inicio)->format('Y-m-d') ?? '',
            'data_fim' => optional($avaliacao->data_fim)->format('Y-m-d') ?? '',
            'status' => (string) $avaliacao->status,
            'escola_id' => count($escolasIds) === 1 ? (string) $escolasIds[0] : 'todas',
            'componentes_ids' => $componentesIds,
            'pautas_ids' => $avaliacao->pautas->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ];
        $this->sincronizarPautasSelecionadasComFiltros();
        $this->modalAberto = true;
        $this->resetValidation();
    }

    public function fecharModal(): void
    {
        $this->modalAberto = false;
    }

    public function salvarAvaliacao(): void
    {
        $isEdicao = filled($this->avaliacaoIdEditando);

        if ($isEdicao && ! (Auth::user()?->hasPermissionTo('Editar Avaliações') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para editar avaliações.')
                ->warning()
                ->send();

            return;
        }

        if (! $isEdicao && ! (Auth::user()?->hasPermissionTo('Criar Avaliações') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para criar avaliações.')
                ->warning()
                ->send();

            return;
        }

        $statusOptions = array_keys(Avaliacao::statusOptions());

        $validated = $this->validate([
            'form.nome' => ['required', 'string', 'max:255'],
            'form.data_inicio' => ['required', 'date'],
            'form.data_fim' => ['required', 'date', 'after_or_equal:form.data_inicio'],
            'form.status' => ['required', 'in:' . implode(',', $statusOptions)],
            'form.escola_id' => ['required'],
            'form.componentes_ids' => ['required', 'array', 'min:1'],
            'form.componentes_ids.*' => ['integer', 'exists:componentes_curriculares,id'],
            'form.pautas_ids' => ['required', 'array', 'min:1'],
            'form.pautas_ids.*' => ['integer', 'exists:pautas,id'],
        ]);

        $escolaSelecionada = (string) ($validated['form']['escola_id'] ?? 'todas');
        $escolaId = $this->resolverEscolaIdSelecionada($escolaSelecionada);

        if ($escolaSelecionada !== 'todas' && (! $escolaId || ! Escola::query()->whereKey($escolaId)->exists())) {
            $this->addError('form.escola_id', 'Selecione uma escola válida ou mantenha a opção "Todas as escolas".');

            return;
        }

        $componentesIds = collect($validated['form']['componentes_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $pautasIds = collect($validated['form']['pautas_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $turmasIds = $this->buscarTurmasIdsPorComponentes($componentesIds->all(), $escolaId);

        if ($turmasIds === []) {
            $this->addError('form.componentes_ids', 'Nenhuma turma foi encontrada para os componentes e escola selecionados.');

            return;
        }

        $pautasIncompativeis = Pauta::query()
            ->whereIn('id', $pautasIds->all())
            ->whereNotNull('componente_curricular_id')
            ->whereNotIn('componente_curricular_id', $componentesIds->all())
            ->count();

        if ($pautasIncompativeis > 0) {
            $this->addError('form.pautas_ids', 'As pautas selecionadas devem pertencer aos componentes escolhidos ou ser pautas gerais.');

            return;
        }

        $pautasSemAlternativas = Pauta::query()
            ->whereIn('id', $pautasIds->all())
            ->doesntHave('alternativas')
            ->count();

        if ($pautasSemAlternativas > 0) {
            $this->addError('form.pautas_ids', 'Todas as pautas vinculadas precisam ter alternativas cadastradas.');

            return;
        }

        DB::transaction(function () use ($isEdicao, $validated, $turmasIds): void {
            if ($isEdicao) {
                $avaliacao = Avaliacao::query()->find($this->avaliacaoIdEditando);

                if (! $avaliacao) {
                    throw new \RuntimeException('Avaliação não encontrada para edição.');
                }
            } else {
                $avaliacao = new Avaliacao();
            }

            $avaliacao->fill([
                'nome' => trim((string) $validated['form']['nome']),
                'data_inicio' => $validated['form']['data_inicio'],
                'data_fim' => $validated['form']['data_fim'],
                'status' => $validated['form']['status'],
            ]);
            $avaliacao->save();

            $avaliacao->pautas()->sync($validated['form']['pautas_ids']);
            $avaliacao->turmas()->sync($turmasIds);
        });

        $this->fecharModal();

        Notification::make()
            ->title($isEdicao ? 'Avaliação atualizada com sucesso.' : 'Avaliação criada com sucesso.')
            ->success()
            ->send();
    }

    public function excluirAvaliacao(int $avaliacaoId): void
    {
        if (! (Auth::user()?->hasPermissionTo('Excluir Avaliações') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para excluir avaliações.')
                ->warning()
                ->send();

            return;
        }

        $avaliacao = Avaliacao::query()->find($avaliacaoId);

        if (! $avaliacao) {
            Notification::make()
                ->title('Avaliação não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $avaliacao->delete();

        Notification::make()
            ->title('Avaliação excluída com sucesso.')
            ->success()
            ->send();
    }

    private function resetForm(): void
    {
        $this->form = [
            'nome' => '',
            'data_inicio' => now()->toDateString(),
            'data_fim' => now()->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
            'escola_id' => 'todas',
            'componentes_ids' => [],
            'pautas_ids' => [],
        ];
    }

    private function resolverEscolaIdSelecionada(mixed $escolaSelecionada): ?int
    {
        if ($escolaSelecionada === 'todas' || blank($escolaSelecionada)) {
            return null;
        }

        $id = (int) $escolaSelecionada;

        return $id > 0 ? $id : null;
    }

    private function buscarTurmasIdsPorComponentes(array $componentesIds, ?int $escolaId): array
    {
        if ($componentesIds === []) {
            return [];
        }

        return DB::table('turma_componente_professor as tcp')
            ->join('turmas as t', 't.id', '=', 'tcp.turma_id')
            ->whereIn('tcp.componente_curricular_id', $componentesIds)
            ->when($escolaId, fn ($query, $id) => $query->where('t.id_escola', $id))
            ->distinct()
            ->pluck('t.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function sincronizarPautasSelecionadasComFiltros(): void
    {
        $pautasDisponiveis = collect(array_keys($this->pautasOptions))
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->form['pautas_ids'] = collect($this->form['pautas_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->intersect($pautasDisponiveis)
            ->values()
            ->all();
    }
}
