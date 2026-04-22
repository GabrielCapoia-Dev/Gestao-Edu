<?php

namespace App\Filament\Admin\Pages;

use App\Models\Alternativa;
use App\Models\ComponenteCurricular;
use App\Models\Pauta;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class GestaoPautas extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pages.gestao-pautas';

    protected static ?string $title = 'Gestão de Pautas';

    protected static ?string $navigationLabel = 'Pautas';

    protected static ?string $slug = 'avaliacoes-pautas';

    protected static ?int $navigationSort = 23;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public string $busca = '';

    public string $filtroStatus = 'todas';

    public ?int $filtroComponente = null;

    public int $porPagina = 10;

    public bool $modalAberto = false;

    public ?int $pautaIdEditando = null;

    public bool $selecionarPagina = false;

    /** @var array<int, int> */
    public array $selecionadas = [];

    public array $acaoMassa = [
        'tipo_avaliacao_id' => null,
        'serie_id' => null,
        'componente_curricular_id' => null,
    ];

    public array $form = [
        'tipo_avaliacao_id' => null,
        'texto' => '',
        'serie_id' => null,
        'componente_curricular_id' => null,
        'status' => true,
        'alternativas_ids' => [],
    ];

    public array $novasAlternativas = [];

    protected $queryString = [
        'busca' => ['except' => ''],
        'filtroStatus' => ['except' => 'todas'],
        'filtroComponente' => ['except' => null],
    ];

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo('Listar Pautas') ?? false;
    }

    protected function getForms(): array
    {
        return [
            'alternativasExistentesForm',
        ];
    }

    public function alternativasExistentesForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('alternativas_ids')
                    ->label('Alternativas existentes')
                    ->helperText('Selecione uma ou mais alternativas ja cadastradas para vincular nesta pauta.')
                    ->options(fn (): array => $this->alternativasSelectOptions)
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live(),
            ])
            ->statePath('form');
    }

    public function updatedBusca(): void
    {
        $this->resetPage();
        $this->limparSelecaoMassa();
    }

    public function updatedFiltroStatus(): void
    {
        $this->resetPage();
        $this->limparSelecaoMassa();
    }

    public function updatedFiltroComponente(): void
    {
        $this->resetPage();
        $this->limparSelecaoMassa();
    }

    public function updatedPorPagina(): void
    {
        $this->resetPage();
        $this->limparSelecaoMassa();
    }

    public function updatedSelecionarPagina(bool $value): void
    {
        if (! $value) {
            $this->selecionadas = [];

            return;
        }

        $this->selecionadas = $this->pautas
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function updatedSelecionadas(): void
    {
        $idsPagina = $this->pautas
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $selecionadasNaPagina = collect($this->selecionadas)
            ->map(fn ($id): int => (int) $id)
            ->intersect($idsPagina)
            ->count();

        $this->selecionarPagina = count($idsPagina) > 0 && $selecionadasNaPagina === count($idsPagina);
    }

    public function getPautasProperty(): LengthAwarePaginator
    {
        $query = Pauta::query()
            ->with([
                'tipo:id,nome',
                'serie:id,nome',
                'componente:id,nome',
            ])
            ->withCount(['alternativas', 'avaliacoes']);

        if (filled($this->busca)) {
            $busca = trim($this->busca);

            $query->where(function ($subQuery) use ($busca) {
                $subQuery->where('texto', 'like', "%{$busca}%")
                    ->orWhereHas('componente', fn ($componentQuery) => $componentQuery->where('nome', 'like', "%{$busca}%"));
            });
        }

        if ($this->filtroStatus === 'ativas') {
            $query->where('status', true);
        }

        if ($this->filtroStatus === 'inativas') {
            $query->where('status', false);
        }

        if (filled($this->filtroComponente)) {
            $query->where('componente_curricular_id', (int) $this->filtroComponente);
        }

        return $query
            ->orderByDesc('updated_at')
            ->paginate($this->porPagina);
    }

    public function getComponentesOptionsProperty(): array
    {
        return ComponenteCurricular::query()
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getSeriesOptionsProperty(): array
    {
        return Serie::query()
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getTiposOptionsProperty(): array
    {
        return TipoAvaliacao::query()
            ->where('status', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getAlternativasOptionsProperty(): Collection
    {
        $tipoAvaliacaoId = (int) ($this->form['tipo_avaliacao_id'] ?? 0);

        return Alternativa::query()
            ->when($tipoAvaliacaoId > 0, fn ($query) => $query->where('tipo_avaliacao_id', $tipoAvaliacaoId))
            ->orderBy('nome')
            ->get(['id', 'nome', 'tem_observacao', 'status']);
    }

    public function getAlternativasSelectOptionsProperty(): array
    {
        return $this->alternativasOptions
            ->mapWithKeys(function (Alternativa $alternativa): array {
                $sufixos = [];

                if ($alternativa->tem_observacao) {
                    $sufixos[] = 'exige observacao';
                }

                if (! $alternativa->status) {
                    $sufixos[] = 'inativa';
                }

                $label = $alternativa->nome;

                if ($sufixos !== []) {
                    $label .= ' (' . implode(', ', $sufixos) . ')';
                }

                return [$alternativa->id => $label];
            })
            ->all();
    }

    public function abrirModalCriacao(): void
    {
        if (! (Auth::user()?->hasPermissionTo('Criar Pautas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para criar pautas.')
                ->warning()
                ->send();

            return;
        }

        $this->resetForm();
        $this->pautaIdEditando = null;
        $this->modalAberto = true;
        $this->resetValidation();
    }

    public function abrirModalEdicao(int $pautaId): void
    {
        if (! (Auth::user()?->hasPermissionTo('Editar Pautas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para editar pautas.')
                ->warning()
                ->send();

            return;
        }

        $pauta = Pauta::query()
            ->with('alternativas:id')
            ->find($pautaId);

        if (! $pauta) {
            Notification::make()
                ->title('Pauta não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $this->pautaIdEditando = $pauta->id;
        $this->form = [
            'tipo_avaliacao_id' => $pauta->tipo_avaliacao_id,
            'texto' => (string) $pauta->texto,
            'serie_id' => $pauta->serie_id,
            'componente_curricular_id' => $pauta->componente_curricular_id,
            'status' => (bool) $pauta->status,
            'alternativas_ids' => $pauta->alternativas->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ];
        $this->novasAlternativas = [];
        $this->modalAberto = true;
        $this->resetValidation();
    }

    public function adicionarNovaAlternativa(): void
    {
        $this->novasAlternativas[] = [
            'nome' => '',
            'tem_observacao' => false,
            'observacao' => '',
            'status' => true,
        ];
    }

    public function removerNovaAlternativa(int $index): void
    {
        if (! isset($this->novasAlternativas[$index])) {
            return;
        }

        unset($this->novasAlternativas[$index]);
        $this->novasAlternativas = array_values($this->novasAlternativas);
    }

    public function fecharModal(): void
    {
        $this->modalAberto = false;
    }

    public function salvarPauta(): void
    {
        $isEdicao = filled($this->pautaIdEditando);

        if ($isEdicao && ! (Auth::user()?->hasPermissionTo('Editar Pautas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para editar pautas.')
                ->warning()
                ->send();

            return;
        }

        if (! $isEdicao && ! (Auth::user()?->hasPermissionTo('Criar Pautas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para criar pautas.')
                ->warning()
                ->send();

            return;
        }

        $validated = $this->validate([
            'form.tipo_avaliacao_id' => ['required', 'integer', 'exists:tipos_avaliacao,id'],
            'form.texto' => ['required', 'string', 'max:2000'],
            'form.serie_id' => ['required', 'integer', 'exists:series,id'],
            'form.componente_curricular_id' => ['nullable', 'exists:componentes_curriculares,id'],
            'form.status' => ['required', 'boolean'],
            'form.alternativas_ids' => ['array'],
            'form.alternativas_ids.*' => ['integer', 'exists:alternativas,id'],
            'novasAlternativas' => ['array'],
            'novasAlternativas.*.nome' => ['nullable', 'string', 'max:255'],
            'novasAlternativas.*.tem_observacao' => ['required', 'boolean'],
            'novasAlternativas.*.observacao' => ['nullable', 'string', 'max:1000'],
            'novasAlternativas.*.status' => ['required', 'boolean'],
        ]);

        $alternativasSelecionadas = collect($validated['form']['alternativas_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $novasAlternativasComNome = collect($validated['novasAlternativas'] ?? [])
            ->map(function (array $item): array {
                $temObservacao = (bool) ($item['tem_observacao'] ?? false);

                return [
                    'nome' => trim((string) ($item['nome'] ?? '')),
                    'tem_observacao' => $temObservacao,
                    'observacao' => $temObservacao && filled($item['observacao'] ?? null)
                        ? trim((string) $item['observacao'])
                        : null,
                    'status' => (bool) ($item['status'] ?? true),
                ];
            })
            ->filter(fn (array $item): bool => $item['nome'] !== '')
            ->values();

        $alternativasIncompativeisComTipo = Alternativa::query()
            ->whereIn('id', $alternativasSelecionadas->all())
            ->where('tipo_avaliacao_id', '!=', (int) $validated['form']['tipo_avaliacao_id'])
            ->count();

        if ($alternativasIncompativeisComTipo > 0) {
            $this->addError('form.alternativas_ids', 'Selecione apenas alternativas do mesmo tipo da pauta.');

            return;
        }

        DB::transaction(function () use ($isEdicao, $validated, $alternativasSelecionadas, $novasAlternativasComNome): void {
            if ($isEdicao) {
                $pauta = Pauta::query()->find($this->pautaIdEditando);

                if (! $pauta) {
                    throw new \RuntimeException('Pauta não encontrada para edição.');
                }
            } else {
                $pauta = new Pauta();
            }

            $pauta->fill([
                'tipo_avaliacao_id' => (int) $validated['form']['tipo_avaliacao_id'],
                'texto' => trim((string) $validated['form']['texto']),
                'serie_id' => (int) $validated['form']['serie_id'],
                'componente_curricular_id' => $validated['form']['componente_curricular_id'] ?: null,
                'status' => (bool) $validated['form']['status'],
            ]);
            $pauta->save();

            $novosIds = $novasAlternativasComNome
                ->map(function (array $item) use ($validated): int {
                    return Alternativa::query()->create([
                        ...$item,
                        'tipo_avaliacao_id' => (int) $validated['form']['tipo_avaliacao_id'],
                    ])->id;
                });

            $idsFinal = $alternativasSelecionadas
                ->merge($novosIds)
                ->unique()
                ->values()
                ->all();

            $pauta->alternativas()->sync($idsFinal);
        });

        $this->fecharModal();

        Notification::make()
            ->title($isEdicao ? 'Pauta atualizada com sucesso.' : 'Pauta criada com sucesso.')
            ->success()
            ->send();
    }

    public function excluirPauta(int $pautaId): void
    {
        if (! (Auth::user()?->hasPermissionTo('Excluir Pautas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para excluir pautas.')
                ->warning()
                ->send();

            return;
        }

        $pauta = Pauta::query()->find($pautaId);

        if (! $pauta) {
            Notification::make()
                ->title('Pauta não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $pauta->delete();

        Notification::make()
            ->title('Pauta excluída com sucesso.')
            ->success()
            ->send();
    }

    public function aplicarCamposEmMassa(): void
    {
        if (! (Auth::user()?->hasPermissionTo('Editar Pautas') ?? false)) {
            Notification::make()
                ->title('Você não tem permissão para editar pautas.')
                ->warning()
                ->send();

            return;
        }

        $ids = collect($this->selecionadas)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            Notification::make()
                ->title('Selecione ao menos uma pauta.')
                ->warning()
                ->send();

            return;
        }

        $validated = $this->validate([
            'acaoMassa.tipo_avaliacao_id' => ['nullable', 'integer', 'exists:tipos_avaliacao,id'],
            'acaoMassa.serie_id' => ['nullable', 'integer', 'exists:series,id'],
            'acaoMassa.componente_curricular_id' => ['nullable', 'integer', 'exists:componentes_curriculares,id'],
        ]);

        $updates = collect([
            'tipo_avaliacao_id' => $validated['acaoMassa']['tipo_avaliacao_id'] ?? null,
            'serie_id' => $validated['acaoMassa']['serie_id'] ?? null,
            'componente_curricular_id' => $validated['acaoMassa']['componente_curricular_id'] ?? null,
        ])->filter(fn ($value) => filled($value))->all();

        if ($updates === []) {
            $this->addError('acaoMassa.tipo_avaliacao_id', 'Informe ao menos um campo para aplicar em massa.');

            return;
        }

        $updates['updated_at'] = now();

        $quantidadeAtualizada = Pauta::query()
            ->whereIn('id', $ids->all())
            ->update($updates);

        $this->acaoMassa = [
            'tipo_avaliacao_id' => null,
            'serie_id' => null,
            'componente_curricular_id' => null,
        ];
        $this->limparSelecaoMassa();

        Notification::make()
            ->title("Campos aplicados em {$quantidadeAtualizada} pauta(s).")
            ->success()
            ->send();
    }

    private function resetForm(): void
    {
        $this->form = [
            'tipo_avaliacao_id' => null,
            'texto' => '',
            'serie_id' => null,
            'componente_curricular_id' => null,
            'status' => true,
            'alternativas_ids' => [],
        ];
        $this->novasAlternativas = [];
    }

    private function limparSelecaoMassa(): void
    {
        $this->selecionarPagina = false;
        $this->selecionadas = [];
    }
    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }
}
