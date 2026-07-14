<?php

namespace App\Filament\Admin\Pages;

use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use UnitEnum;

class GestaoAvaliacoes extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.pages.gestao-avaliacoes';

    protected static ?string $title = 'Gestão de Avaliações';

    protected static ?string $navigationLabel = 'Avaliações';

    protected static ?string $slug = 'avaliacoes-gestao';

    protected static ?int $navigationSort = 22;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public bool $modalAberto = false;

    public ?int $avaliacaoIdEditando = null;

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => "Avaliações",
            'description' => 'Gerencie as avaliações, adicione novas e mantenha um registro atualizado das informações.',
        ]);
    }

    public array $form = [
        'nome' => '',
        'tipo_avaliacao_id' => null,
        'periodo_avaliacao_id' => null,
        'novo_periodo_nome' => '',
        'data_inicio' => '',
        'data_fim' => '',
        'data_inicio_preenchimento' => '',
        'data_fim_preenchimento' => '',
        'status' => Avaliacao::STATUS_ATIVA,
        'series_ids' => [],
        'componentes_ids' => [],
        'escolas_ids' => [],
        'pautas_override_habilitado' => [],
        'alternativas_override' => [],
    ];

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Avaliacao::class);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pautas')
                ->label('Pautas')
                ->icon(Heroicon::ClipboardDocumentList)
                ->color('gray')
                ->url('/admin/avaliacoes-pautas')
                ->visible(fn(): bool => Gate::allows('viewAny', Pauta::class)),

            Action::make('alternativas')
                ->label('Alternativas')
                ->icon(Heroicon::QueueList)
                ->color('gray')
                ->url('/admin/avaliacoes-alternativas')
                ->visible(fn(): bool => Gate::allows('viewAny', Alternativa::class)),

            Action::make('create')
                ->label('Nova avaliação')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->visible(fn(): bool => Gate::allows('create', Avaliacao::class))
                ->action(fn() => $this->abrirModalCriacao()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Avaliacao::query()
                    ->withCount(['pautas'])
                    ->with([
                        'tipo:id,nome',
                        'periodo:id,nome',
                    ])
            )
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('nome')
                    ->label('Avaliação')
                    ->searchable()
                    ->sortable()
                    ->description(function (Avaliacao $record): string {
                        $inicio = $record->data_inicio?->format('d/m/Y') ?? '';
                        $fim = $record->data_fim?->format('d/m/Y') ?? '';

                        return trim("{$inicio} até {$fim}");
                    })
                    ->wrap(),

                TextColumn::make('tipo.nome')
                    ->label('Tipo')
                    ->sortable()
                    ->placeholder('Sem tipo'),

                TextColumn::make('periodo.nome')
                    ->label('Período')
                    ->sortable()
                    ->placeholder('Sem período'),

                TextColumn::make('pautas_count')
                    ->label('Pautas')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        Avaliacao::STATUS_ATIVA => 'success',
                        Avaliacao::STATUS_INATIVA => 'gray',
                        Avaliacao::STATUS_ENCERRADA => 'warning',
                        Avaliacao::STATUS_CANCELADA => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => Avaliacao::statusOptions()[$state] ?? $state),

                TextColumn::make('updated_at')
                    ->label('Atualizada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Avaliacao::statusOptions()),

                SelectFilter::make('tipo_avaliacao_id')
                    ->label('Tipo')
                    ->options(fn(): array => TipoAvaliacao::query()->orderBy('nome')->pluck('nome', 'id')->toArray()),

                SelectFilter::make('periodo_avaliacao_id')
                    ->label('Período')
                    ->options(fn(): array => PeriodoAvaliacao::query()->orderBy('nome')->pluck('nome', 'id')->toArray()),
            ])
            ->actions([
                Action::make('editar')
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare)
                    ->visible(fn(Avaliacao $record): bool => Gate::allows('update', $record))
                    ->action(fn(Avaliacao $record) => $this->abrirModalEdicao($record->getKey())),

                Action::make('acompanhar')
                    ->label('Acompanhar')
                    ->icon(Heroicon::ChartBar)
                    ->color('gray')
                    ->url(fn(Avaliacao $record): string => route('filament.admin.pages.dashboard-avaliacoes', [
                        'avaliacao' => $record->getKey(),
                    ]))
                    ->visible(fn(): bool => Gate::allows('follow', Avaliacao::class)),

                Action::make('excluir')
                    ->label('Excluir')
                    ->icon(Heroicon::Trash)
                    ->color('danger')
                    ->visible(fn(Avaliacao $record): bool => Gate::allows('delete', $record))
                    ->requiresConfirmation()
                    ->action(fn(Avaliacao $record) => $this->excluirAvaliacao($record->getKey())),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    protected function getForms(): array
    {
        return [
            'escopoForm',
            'alternativasOverrideForm',
        ];
    }

    public function escopoForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('series_ids')
                    ->label('Séries')
                    ->helperText('Selecione uma ou mais séries.')
                    ->options(fn(): array => $this->seriesOptions)
                    ->multiple()
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn() => $this->updatedFormSeriesIds()),
                Select::make('componentes_ids')
                    ->label('Componentes')
                    ->helperText('Após selecionar as séries, escolha os componentes.')
                    ->options(fn(): array => $this->componentesOptions)
                    ->multiple()
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn() => $this->updatedFormComponentesIds()),
                Select::make('escolas_ids')
                    ->label('Escolas')
                    ->helperText('Selecione escolas elegíveis ou marque "Todas as escolas elegíveis".')
                    ->options(fn(): array => $this->escolasOptions)
                    ->multiple()
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn() => $this->updatedFormEscolasIds()),
            ])
            ->columns(3)
            ->statePath('form');
    }

    public function alternativasOverrideForm(Schema $schema): Schema
    {
        $components = $this->pautasCarregadas
            ->map(fn(Pauta $pauta): Select => Select::make("alternativas_override.{$pauta->id}")
                ->key($this->alternativasOverrideComponentKey((int) $pauta->id))
                ->label('Alternativas de override')
                ->helperText('Selecione uma ou mais alternativas para esta pauta apenas nesta avaliação.')
                ->options(fn(): array => $this->alternativasAtivasOptions)
                ->multiple()
                ->native(false)
                ->searchable()
                ->preload()
                ->live())
            ->values()
            ->all();

        return $schema
            ->components($components)
            ->statePath('form');
    }

    public function alternativasOverrideComponentKey(int $pautaId): string
    {
        return "override_{$pautaId}";
    }

    public function updatedFormTipoAvaliacaoId(): void
    {
        $this->sincronizarOverridesPautasComFiltros();
        $this->limparCacheAlternativasOverrideForm();
    }

    public function updatedFormSeriesIds(): void
    {
        $this->form['series_ids'] = collect($this->form['series_ids'] ?? [])
            ->filter()
            ->map(fn($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $this->sincronizarComponentesSelecionadosComFiltros();
        $this->sincronizarEscolasSelecionadasComFiltros();
        $this->sincronizarOverridesPautasComFiltros();
        $this->limparCacheAlternativasOverrideForm();
    }

    public function updatedFormComponentesIds(): void
    {
        $this->form['componentes_ids'] = collect($this->form['componentes_ids'] ?? [])
            ->filter()
            ->map(fn($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $this->sincronizarEscolasSelecionadasComFiltros();
        $this->sincronizarOverridesPautasComFiltros();
        $this->limparCacheAlternativasOverrideForm();
    }

    public function updatedFormEscolasIds(): void
    {
        $this->sincronizarEscolasSelecionadasComFiltros();
    }

    public function getStatusOptionsProperty(): array
    {
        return Avaliacao::statusOptions();
    }

    public function getTiposOptionsProperty(): array
    {
        return TipoAvaliacao::query()
            ->where('status', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getPeriodosOptionsProperty(): array
    {
        return PeriodoAvaliacao::query()
            ->where('status', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getSeriesOptionsProperty(): array
    {
        return Serie::query()
            ->whereHas('turmas')
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getComponentesOptionsProperty(): array
    {
        $seriesIds = collect($this->form['series_ids'] ?? [])
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($seriesIds === []) {
            return [];
        }

        return ComponenteCurricular::query()
            ->select('componentes_curriculares.id', 'componentes_curriculares.nome')
            ->join('serie_componente_curricular as scc', 'scc.componente_curricular_id', '=', 'componentes_curriculares.id')
            ->whereIn('scc.serie_id', $seriesIds)
            ->orderBy('componentes_curriculares.nome')
            ->distinct()
            ->pluck('componentes_curriculares.nome', 'componentes_curriculares.id')
            ->toArray();
    }

    public function getEscolasOptionsProperty(): array
    {
        $escolasElegiveis = $this->buscarEscolasElegiveis(
            collect($this->form['series_ids'] ?? [])->map(fn($id) => (int) $id)->all(),
            collect($this->form['componentes_ids'] ?? [])->map(fn($id) => (int) $id)->all()
        );

        if ($escolasElegiveis->isEmpty()) {
            return [];
        }

        return ['todas' => 'Todas as escolas elegíveis'] + $escolasElegiveis
            ->pluck('nome', 'id')
            ->mapWithKeys(fn($nome, $id): array => [(string) $id => $nome])
            ->all();
    }

    public function getPautasCarregadasProperty(): Collection
    {
        $tipoAvaliacaoId = (int) ($this->form['tipo_avaliacao_id'] ?? 0);
        $seriesIds = collect($this->form['series_ids'] ?? [])->map(fn($id) => (int) $id)->filter()->values()->all();
        $componentesIds = collect($this->form['componentes_ids'] ?? [])->map(fn($id) => (int) $id)->filter()->values()->all();

        if ($tipoAvaliacaoId <= 0 || $seriesIds === [] || $componentesIds === []) {
            return collect();
        }

        return $this->buscarPautasParaEscopo($tipoAvaliacaoId, $seriesIds, $componentesIds);
    }

    public function getAlternativasAtivasOptionsProperty(): array
    {
        return Alternativa::query()
            ->with('tipo:id,nome')
            ->where('status', true)
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(function (Alternativa $alternativa): array {
                $label = $alternativa->nome;

                if ($alternativa->tipo?->nome) {
                    $label .= ' | ' . $alternativa->tipo->nome;
                }

                if ($alternativa->tem_observacao) {
                    $label .= ' | exige observação';
                }

                return [$alternativa->id => $label];
            })
            ->all();
    }

    public function abrirModalCriacao(): void
    {
        Gate::authorize('create', Avaliacao::class);

        $this->resetForm();
        $this->avaliacaoIdEditando = null;
        $this->limparCacheAlternativasOverrideForm();
        $this->modalAberto = true;
        $this->resetValidation();
    }

    public function abrirModalEdicao(int $avaliacaoId): void
    {

        $avaliacao = Avaliacao::query()
            ->with([
                'series:id,nome',
                'componentes:id,nome',
                'escolas:id,nome',
            ])
            ->find($avaliacaoId);

        if (! $avaliacao) {
            Notification::make()
                ->title('Avaliação não encontrada.')
                ->warning()
                ->send();

            return;
        }

        Gate::authorize('update', $avaliacao);

        $this->avaliacaoIdEditando = $avaliacao->id;
        $this->form = [
            'nome' => (string) $avaliacao->nome,
            'tipo_avaliacao_id' => $avaliacao->tipo_avaliacao_id,
            'periodo_avaliacao_id' => $avaliacao->periodo_avaliacao_id,
            'novo_periodo_nome' => '',
            'data_inicio' => optional($avaliacao->data_inicio)->format('Y-m-d') ?? '',
            'data_fim' => optional($avaliacao->data_fim)->format('Y-m-d') ?? '',
            'data_inicio_preenchimento' => optional($avaliacao->data_inicio_preenchimento)->format('Y-m-d') ?? '',
            'data_fim_preenchimento' => optional($avaliacao->data_fim_preenchimento)->format('Y-m-d') ?? '',
            'status' => (string) $avaliacao->status,
            'series_ids' => $avaliacao->series->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
            'componentes_ids' => $avaliacao->componentes->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
            'escolas_ids' => $avaliacao->escolas->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
            'pautas_override_habilitado' => [],
            'alternativas_override' => [],
        ];

        $this->sincronizarComponentesSelecionadosComFiltros();
        $this->sincronizarEscolasSelecionadasComFiltros();
        $this->sincronizarOverridesPautasComFiltros();
        $this->preencherOverridesExistentes($avaliacao->id);
        $this->limparCacheAlternativasOverrideForm();

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

        if ($isEdicao) {
            Gate::authorize('update', Avaliacao::query()->findOrFail($this->avaliacaoIdEditando));
        } else {
            Gate::authorize('create', Avaliacao::class);
        }

        $statusOptions = array_keys(Avaliacao::statusOptions());

        $validated = $this->validate([
            'form.nome' => ['required', 'string', 'max:255'],
            'form.tipo_avaliacao_id' => ['required', 'integer', 'exists:tipos_avaliacao,id'],
            'form.periodo_avaliacao_id' => ['nullable', 'integer', 'exists:periodos_avaliacao,id'],
            'form.novo_periodo_nome' => ['nullable', 'string', 'max:255'],
            'form.data_inicio' => ['required', 'date'],
            'form.data_fim' => ['required', 'date', 'after_or_equal:form.data_inicio'],
            'form.data_inicio_preenchimento' => ['required', 'date'],
            'form.data_fim_preenchimento' => ['required', 'date', 'after_or_equal:form.data_inicio_preenchimento'],
            'form.status' => ['required', 'in:' . implode(',', $statusOptions)],
            'form.series_ids' => ['required', 'array', 'min:1'],
            'form.series_ids.*' => ['integer', 'exists:series,id'],
            'form.componentes_ids' => ['required', 'array', 'min:1'],
            'form.componentes_ids.*' => ['integer', 'exists:componentes_curriculares,id'],
            'form.escolas_ids' => ['required', 'array', 'min:1'],
            'form.escolas_ids.*' => ['required'],
        ]);

        $novoPeriodoNome = Str::of((string) ($validated['form']['novo_periodo_nome'] ?? ''))->trim()->toString();
        $periodoAvaliacaoId = (int) ($validated['form']['periodo_avaliacao_id'] ?? 0);

        if ($novoPeriodoNome !== '') {
            $periodoAvaliacaoId = (int) PeriodoAvaliacao::query()
                ->firstOrCreate(
                    ['nome' => $novoPeriodoNome],
                    ['status' => true]
                )
                ->id;
        }

        if ($periodoAvaliacaoId <= 0) {
            $this->addError('form.periodo_avaliacao_id', 'Selecione um período existente ou informe um novo.');

            return;
        }

        $tipoAvaliacaoId = (int) $validated['form']['tipo_avaliacao_id'];
        $seriesIds = collect($validated['form']['series_ids'])->map(fn($id) => (int) $id)->unique()->values()->all();
        $componentesIds = collect($validated['form']['componentes_ids'])->map(fn($id) => (int) $id)->unique()->values()->all();

        $escolasElegiveis = $this->buscarEscolasElegiveis($seriesIds, $componentesIds);

        if ($escolasElegiveis->isEmpty()) {
            $this->addError('form.escolas_ids', 'Nenhuma escola elegível foi encontrada para o escopo selecionado.');

            return;
        }

        $escolasIds = $this->resolverEscolasIdsSelecionadas(
            (array) ($validated['form']['escolas_ids'] ?? []),
            $escolasElegiveis->pluck('id')->map(fn($id) => (int) $id)->all()
        );

        if ($escolasIds === []) {
            $this->addError('form.escolas_ids', 'Selecione ao menos uma escola elegível.');

            return;
        }

        $pautasCarregadas = $this->buscarPautasParaEscopo($tipoAvaliacaoId, $seriesIds, $componentesIds);

        if ($pautasCarregadas->isEmpty()) {
            $this->addError('form.componentes_ids', 'Nenhuma pauta ativa foi encontrada para o tipo, séries e componentes selecionados.');

            return;
        }

        $turmasIds = $this->buscarTurmasIdsPorEscopo($seriesIds, $componentesIds, $escolasIds);

        if ($turmasIds === []) {
            $this->addError('form.escolas_ids', 'Nenhuma turma foi encontrada para o escopo selecionado.');

            return;
        }

        $alternativasTipoIds = Alternativa::query()
            ->where('status', true)
            ->where('tipo_avaliacao_id', $tipoAvaliacaoId)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

        $alternativasAtivasIds = Alternativa::query()
            ->where('status', true)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

        $overridesPayload = [];
        $agora = now();

        foreach ($pautasCarregadas as $pauta) {
            $pautaId = (int) $pauta->id;
            $overrideHabilitado = (bool) (($this->form['pautas_override_habilitado'][$pautaId] ?? false));

            if (! $overrideHabilitado) {
                if ($alternativasTipoIds === []) {
                    $this->addError(
                        'form.pautas_override_habilitado.' . $pautaId,
                        'O tipo selecionado não possui alternativas ativas. Defina um override nesta pauta.'
                    );

                    return;
                }

                continue;
            }

            $alternativasOverrideIds = collect($this->form['alternativas_override'][$pautaId] ?? [])
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($alternativasOverrideIds === []) {
                $this->addError(
                    'form.alternativas_override.' . $pautaId,
                    'Selecione pelo menos uma alternativa para o override desta pauta.'
                );

                return;
            }

            $alternativasValidas = collect($alternativasOverrideIds)
                ->intersect($alternativasAtivasIds)
                ->values()
                ->all();

            if (count($alternativasValidas) !== count($alternativasOverrideIds)) {
                $this->addError(
                    'form.alternativas_override.' . $pautaId,
                    'O override contém alternativas inválidas ou inativas.'
                );

                return;
            }

            foreach ($alternativasValidas as $alternativaId) {
                $overridesPayload[] = [
                    'avaliacao_id' => 0,
                    'pauta_id' => $pautaId,
                    'alternativa_id' => (int) $alternativaId,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            }
        }

        DB::transaction(function () use (
            $isEdicao,
            $validated,
            $tipoAvaliacaoId,
            $periodoAvaliacaoId,
            $pautasCarregadas,
            $turmasIds,
            $seriesIds,
            $componentesIds,
            $escolasIds,
            $overridesPayload
        ): void {
            if ($isEdicao) {
                $avaliacao = Avaliacao::query()->find($this->avaliacaoIdEditando);

                if (! $avaliacao) {
                    throw new \RuntimeException('Avaliação não encontrada para edição.');
                }
            } else {
                $avaliacao = new Avaliacao;
            }

            $avaliacao->fill([
                'nome' => trim((string) $validated['form']['nome']),
                'tipo_avaliacao_id' => $tipoAvaliacaoId,
                'periodo_avaliacao_id' => $periodoAvaliacaoId,
                'data_inicio' => $validated['form']['data_inicio'],
                'data_fim' => $validated['form']['data_fim'],
                'data_inicio_preenchimento' => $validated['form']['data_inicio_preenchimento'],
                'data_fim_preenchimento' => $validated['form']['data_fim_preenchimento'],
                'status' => $validated['form']['status'],
            ]);
            $avaliacao->save();

            $avaliacao->pautas()->sync($pautasCarregadas->pluck('id')->map(fn($id) => (int) $id)->all());
            $avaliacao->turmas()->sync($turmasIds);
            $avaliacao->series()->sync($seriesIds);
            $avaliacao->componentes()->sync($componentesIds);
            $avaliacao->escolas()->sync($escolasIds);

            DB::table('avaliacao_pauta_alternativa')
                ->where('avaliacao_id', (int) $avaliacao->id)
                ->delete();

            if ($overridesPayload !== []) {
                $payload = collect($overridesPayload)->map(function (array $row) use ($avaliacao): array {
                    $row['avaliacao_id'] = (int) $avaliacao->id;

                    return $row;
                })->all();

                DB::table('avaliacao_pauta_alternativa')->insert($payload);
            }
        });

        $this->fecharModal();

        Notification::make()
            ->title($isEdicao ? 'Avaliação atualizada com sucesso.' : 'Avaliação criada com sucesso.')
            ->success()
            ->send();
    }

    public function excluirAvaliacao(int $avaliacaoId): void
    {

        $avaliacao = Avaliacao::query()->find($avaliacaoId);

        if (! $avaliacao) {
            Notification::make()
                ->title('Avaliação não encontrada.')
                ->warning()
                ->send();

            return;
        }

        Gate::authorize('delete', $avaliacao);

        $avaliacao->delete();

        Notification::make()
            ->title('Avaliação excluída com sucesso.')
            ->success()
            ->send();
    }

    private function preencherOverridesExistentes(int $avaliacaoId): void
    {
        $pautasIds = $this->pautasCarregadas
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

        if ($pautasIds === []) {
            return;
        }

        $overrides = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', $avaliacaoId)
            ->whereIn('pauta_id', $pautasIds)
            ->get(['pauta_id', 'alternativa_id'])
            ->groupBy('pauta_id');

        foreach ($overrides as $pautaId => $rows) {
            $id = (int) $pautaId;
            $this->form['pautas_override_habilitado'][$id] = true;
            $this->form['alternativas_override'][$id] = collect($rows)
                ->pluck('alternativa_id')
                ->map(fn($alternativaId) => (int) $alternativaId)
                ->unique()
                ->values()
                ->all();
        }
    }

    private function limparCacheAlternativasOverrideForm(): void
    {
        unset($this->cachedSchemas['alternativasOverrideForm']);
    }

    private function resetForm(): void
    {
        $this->form = [
            'nome' => '',
            'tipo_avaliacao_id' => null,
            'periodo_avaliacao_id' => null,
            'novo_periodo_nome' => '',
            'data_inicio' => now()->toDateString(),
            'data_fim' => now()->toDateString(),
            'data_inicio_preenchimento' => now()->toDateString(),
            'data_fim_preenchimento' => now()->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
            'series_ids' => [],
            'componentes_ids' => [],
            'escolas_ids' => [],
            'pautas_override_habilitado' => [],
            'alternativas_override' => [],
        ];
    }

    private function buscarPautasParaEscopo(int $tipoAvaliacaoId, array $seriesIds, array $componentesIds): Collection
    {
        return Pauta::query()
            ->with([
                'serie:id,nome',
                'componente:id,nome',
            ])
            ->where('status', true)
            ->where('tipo_avaliacao_id', $tipoAvaliacaoId)
            ->whereIn('serie_id', $seriesIds)
            ->whereIn('componente_curricular_id', $componentesIds)
            ->orderBy('texto')
            ->get();
    }

    private function buscarEscolasElegiveis(array $seriesIds, array $componentesIds): Collection
    {
        $seriesIds = collect($seriesIds)->filter()->map(fn($id) => (int) $id)->unique()->values()->all();
        $componentesIds = collect($componentesIds)->filter()->map(fn($id) => (int) $id)->unique()->values()->all();

        if ($seriesIds === [] || $componentesIds === []) {
            return collect();
        }

        $escolasIds = Turma::query()
            ->whereIn('id_serie', $seriesIds)
            ->distinct()
            ->pluck('id_escola')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

        if ($escolasIds === []) {
            return collect();
        }

        return Escola::query()
            ->where('ativo', true)
            ->whereIn('id', $escolasIds)
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    private function buscarTurmasIdsPorEscopo(array $seriesIds, array $componentesIds, array $escolasIds): array
    {
        if ($seriesIds === [] || $componentesIds === [] || $escolasIds === []) {
            return [];
        }

        return Turma::query()
            ->whereIn('id_serie', $seriesIds)
            ->whereIn('id_escola', $escolasIds)
            ->distinct()
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }

    private function resolverEscolasIdsSelecionadas(array $escolasSelecionadas, array $escolasElegiveisIds): array
    {
        $escolasSelecionadas = collect($escolasSelecionadas)
            ->filter()
            ->map(fn($value) => (string) $value)
            ->unique()
            ->values()
            ->all();

        if ($escolasSelecionadas === []) {
            return [];
        }

        if (in_array('todas', $escolasSelecionadas, true)) {
            return $escolasElegiveisIds;
        }

        return collect($escolasSelecionadas)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->intersect($escolasElegiveisIds)
            ->unique()
            ->values()
            ->all();
    }

    private function sincronizarComponentesSelecionadosComFiltros(): void
    {
        $componentesDisponiveis = collect(array_keys($this->componentesOptions))
            ->map(fn($id) => (string) $id)
            ->values()
            ->all();

        $this->form['componentes_ids'] = collect($this->form['componentes_ids'] ?? [])
            ->filter()
            ->map(fn($id) => (string) $id)
            ->intersect($componentesDisponiveis)
            ->unique()
            ->values()
            ->all();
    }

    private function sincronizarEscolasSelecionadasComFiltros(): void
    {
        $escolasDisponiveis = collect(array_keys($this->escolasOptions))
            ->map(fn($id) => (string) $id)
            ->values()
            ->all();

        $escolasSelecionadas = collect($this->form['escolas_ids'] ?? [])
            ->filter()
            ->map(fn($value) => (string) $value)
            ->intersect($escolasDisponiveis)
            ->unique()
            ->values();

        if ($escolasSelecionadas->contains('todas')) {
            $this->form['escolas_ids'] = ['todas'];

            return;
        }

        $this->form['escolas_ids'] = $escolasSelecionadas->all();
    }

    private function sincronizarOverridesPautasComFiltros(): void
    {
        $pautasIds = $this->pautasCarregadas
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

        $overridesAtivos = collect($this->form['pautas_override_habilitado'] ?? [])
            ->filter(fn($_value, $key) => in_array((int) $key, $pautasIds, true))
            ->map(fn($value) => (bool) $value)
            ->all();

        $alternativasOverrides = collect($this->form['alternativas_override'] ?? [])
            ->filter(fn($_value, $key) => in_array((int) $key, $pautasIds, true))
            ->map(function ($ids): array {
                return collect($ids ?? [])
                    ->filter()
                    ->map(fn($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            })
            ->all();

        foreach ($pautasIds as $pautaId) {
            if (! array_key_exists($pautaId, $overridesAtivos)) {
                $overridesAtivos[$pautaId] = false;
            }

            if (! array_key_exists($pautaId, $alternativasOverrides)) {
                $alternativasOverrides[$pautaId] = [];
            }
        }

        $this->form['pautas_override_habilitado'] = $overridesAtivos;
        $this->form['alternativas_override'] = $alternativasOverrides;
    }

    public function getTitle(): string
    {
        return 'Gestão de Avaliações';
    }

    public function getHeading(): string
    {
        return 'Gestão de Avaliações';
    }

    public function getSubheading(): string
    {
        return 'Monte avaliações por tipo, período e escopo pedagógico.';
    }
}
