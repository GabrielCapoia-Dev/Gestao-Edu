<?php

namespace App\Filament\Admin\Pages;

use App\Models\Avaliacao;
use App\Models\Pauta;
use App\Models\Turma;
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
        'pautas_ids' => [],
        'turmas_ids' => [],
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

    public function getAvaliacoesProperty(): LengthAwarePaginator
    {
        $query = Avaliacao::query()
            ->withCount(['pautas', 'turmas'])
            ->with([
                'pautas:id,texto',
                'turmas:id,nome',
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

    public function getPautasOptionsProperty(): array
    {
        return Pauta::query()
            ->with(['componente:id,nome'])
            ->withCount('alternativas')
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

    public function getTurmasOptionsProperty(): array
    {
        return Turma::query()
            ->with(['escola:id,nome', 'serie:id,nome'])
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(function (Turma $turma): array {
                $label = collect([
                    $turma->escola?->nome,
                    $turma->serie?->nome,
                    'Turma ' . $turma->nome,
                ])->filter()->join(' - ');

                return [$turma->id => $label];
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
            ->with(['pautas:id', 'turmas:id'])
            ->find($avaliacaoId);

        if (! $avaliacao) {
            Notification::make()
                ->title('Avaliação não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $this->avaliacaoIdEditando = $avaliacao->id;
        $this->form = [
            'nome' => (string) $avaliacao->nome,
            'data_inicio' => optional($avaliacao->data_inicio)->format('Y-m-d') ?? '',
            'data_fim' => optional($avaliacao->data_fim)->format('Y-m-d') ?? '',
            'status' => (string) $avaliacao->status,
            'pautas_ids' => $avaliacao->pautas->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'turmas_ids' => $avaliacao->turmas->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ];
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
            'form.pautas_ids' => ['required', 'array', 'min:1'],
            'form.pautas_ids.*' => ['integer', 'exists:pautas,id'],
            'form.turmas_ids' => ['required', 'array', 'min:1'],
            'form.turmas_ids.*' => ['integer', 'exists:turmas,id'],
        ]);

        $pautasIds = collect($validated['form']['pautas_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $pautasSemAlternativas = Pauta::query()
            ->whereIn('id', $pautasIds->all())
            ->doesntHave('alternativas')
            ->count();

        if ($pautasSemAlternativas > 0) {
            $this->addError('form.pautas_ids', 'Todas as pautas vinculadas precisam ter alternativas cadastradas.');

            return;
        }

        DB::transaction(function () use ($isEdicao, $validated): void {
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
            $avaliacao->turmas()->sync($validated['form']['turmas_ids']);
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
            'pautas_ids' => [],
            'turmas_ids' => [],
        ];
    }
}
