<?php

namespace App\Filament\Admin\Pages;

use App\Models\Alternativa;
use App\Models\TipoAvaliacao;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\WithPagination;
use UnitEnum;

class GestaoAlternativas extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.gestao-alternativas';

    protected static ?string $title = 'Gestão de Alternativas';

    protected static ?string $navigationLabel = 'Alternativas';

    protected static ?string $slug = 'avaliacoes-alternativas';

    protected static ?int $navigationSort = 24;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::QueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public string $busca = '';

    public string $filtroStatus = 'todas';

    public int $porPagina = 10;

    public bool $modalAberto = false;

    public ?int $alternativaIdEditando = null;

    public array $form = [
        'tipo_avaliacao_id' => null,
        'novo_tipo_nome' => '',
        'nome' => '',
        'tem_observacao' => false,
        'observacao' => '',
        'status' => true,
    ];

    protected $queryString = [
        'busca' => ['except' => ''],
        'filtroStatus' => ['except' => 'todas'],
    ];

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo('Listar Alternativas') ?? false;
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

    public function getAlternativasProperty(): LengthAwarePaginator
    {
        $query = Alternativa::query()
            ->with('tipo:id,nome')
            ->withCount('pautas');

        if (filled($this->busca)) {
            $busca = trim($this->busca);
            $query->where(function ($subQuery) use ($busca) {
                $subQuery->where('nome', 'like', "%{$busca}%")
                    ->orWhere('observacao', 'like', "%{$busca}%");
            });
        }

        if ($this->filtroStatus === 'ativas') {
            $query->where('status', true);
        }

        if ($this->filtroStatus === 'inativas') {
            $query->where('status', false);
        }

        return $query
            ->orderByDesc('updated_at')
            ->paginate($this->porPagina);
    }

    public function abrirModalCriacao(): void
    {
        if (! (Auth::user()?->hasPermissionTo('Criar Alternativas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para criar alternativas.')
                ->warning()
                ->send();

            return;
        }

        $this->alternativaIdEditando = null;
        $this->form = [
            'tipo_avaliacao_id' => null,
            'novo_tipo_nome' => '',
            'nome' => '',
            'tem_observacao' => false,
            'observacao' => '',
            'status' => true,
        ];
        $this->modalAberto = true;
        $this->resetValidation();
    }

    public function abrirModalEdicao(int $alternativaId): void
    {
        if (! (Auth::user()?->hasPermissionTo('Editar Alternativas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para editar alternativas.')
                ->warning()
                ->send();

            return;
        }

        $alternativa = Alternativa::query()->find($alternativaId);

        if (! $alternativa) {
            Notification::make()
                ->title('Alternativa não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $this->alternativaIdEditando = $alternativa->id;
        $this->form = [
            'tipo_avaliacao_id' => $alternativa->tipo_avaliacao_id,
            'novo_tipo_nome' => '',
            'nome' => (string) $alternativa->nome,
            'tem_observacao' => (bool) $alternativa->tem_observacao,
            'observacao' => (string) ($alternativa->observacao ?? ''),
            'status' => (bool) $alternativa->status,
        ];
        $this->modalAberto = true;
        $this->resetValidation();
    }

    public function fecharModal(): void
    {
        $this->modalAberto = false;
    }

    public function getTiposOptionsProperty(): array
    {
        return TipoAvaliacao::query()
            ->where('status', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function salvarAlternativa(): void
    {
        $isEdicao = filled($this->alternativaIdEditando);

        if ($isEdicao && ! (Auth::user()?->hasPermissionTo('Editar Alternativas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para editar alternativas.')
                ->warning()
                ->send();

            return;
        }

        if (! $isEdicao && ! (Auth::user()?->hasPermissionTo('Criar Alternativas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para criar alternativas.')
                ->warning()
                ->send();

            return;
        }

        $validated = $this->validate([
            'form.tipo_avaliacao_id' => ['nullable', 'integer', 'exists:tipos_avaliacao,id'],
            'form.novo_tipo_nome' => ['nullable', 'string', 'max:255'],
            'form.nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('alternativas', 'nome')->ignore($this->alternativaIdEditando),
            ],
            'form.tem_observacao' => ['required', 'boolean'],
            'form.observacao' => ['nullable', 'string', 'max:1000'],
            'form.status' => ['required', 'boolean'],
        ]);

        $novoTipoNome = Str::of((string) ($validated['form']['novo_tipo_nome'] ?? ''))->trim()->toString();
        $tipoAvaliacaoId = (int) ($validated['form']['tipo_avaliacao_id'] ?? 0);

        if ($novoTipoNome !== '') {
            $tipoAvaliacaoId = (int) TipoAvaliacao::query()
                ->firstOrCreate(
                    ['nome' => $novoTipoNome],
                    ['status' => true]
                )
                ->id;
        }

        if ($tipoAvaliacaoId <= 0) {
            $this->addError('form.tipo_avaliacao_id', 'Selecione um tipo existente ou informe um novo tipo.');

            return;
        }

        if ($isEdicao) {
            $alternativa = Alternativa::query()->find($this->alternativaIdEditando);

            if (! $alternativa) {
                Notification::make()
                    ->title('Alternativa não encontrada para edição.')
                    ->danger()
                    ->send();

                return;
            }
        } else {
            $alternativa = new Alternativa();
        }

        $alternativa->fill([
            'tipo_avaliacao_id' => $tipoAvaliacaoId,
            'nome' => trim((string) $validated['form']['nome']),
            'tem_observacao' => (bool) $validated['form']['tem_observacao'],
            'observacao' => ((bool) $validated['form']['tem_observacao']) && filled($validated['form']['observacao'] ?? null)
                ? trim((string) $validated['form']['observacao'])
                : null,
            'status' => (bool) $validated['form']['status'],
        ]);
        $alternativa->save();

        $this->fecharModal();

        Notification::make()
            ->title($isEdicao ? 'Alternativa atualizada com sucesso.' : 'Alternativa criada com sucesso.')
            ->success()
            ->send();
    }

    public function excluirAlternativa(int $alternativaId): void
    {
        if (! (Auth::user()?->hasPermissionTo('Excluir Alternativas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para excluir alternativas.')
                ->warning()
                ->send();

            return;
        }

        $alternativa = Alternativa::query()->find($alternativaId);

        if (! $alternativa) {
            Notification::make()
                ->title('Alternativa não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $alternativa->delete();

        Notification::make()
            ->title('Alternativa excluída com sucesso.')
            ->success()
            ->send();
    }
}
