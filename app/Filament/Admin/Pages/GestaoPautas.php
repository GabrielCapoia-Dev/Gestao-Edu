<?php

namespace App\Filament\Admin\Pages;

use App\Models\Alternativa;
use App\Models\ComponenteCurricular;
use App\Models\Pauta;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class GestaoPautas extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationParentItem = 'Avaliações';

    protected string $view = 'filament.pages.gestao-pautas';

    protected static ?string $title = 'Gestão de Pautas';

    protected static ?string $navigationLabel = 'Pautas';

    protected static ?string $slug = 'avaliacoes-pautas';

    protected static ?int $navigationSort = 23;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    public bool $modalAberto = false;

    public ?int $pautaIdEditando = null;

    public string $modalAbaPautas = 'configuracao';

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.resources.turmas.pages.manage-turmas-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => "Pautas",
            'description' => 'Gerencie as pautas de avaliação, configure critérios de avaliação e mantenha um registro detalhado para cada uma.',
        ]);
    }

    public array $form = [
        'tipo_avaliacao_id' => null,
        'textos' => [
            ['texto' => ''],
        ],
        'serie_id' => null,
        'componente_curricular_id' => null,
        'status' => true,
        'alternativas_ids' => [],
    ];

    public array $novasAlternativas = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo('Listar Pautas') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Nova pauta')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->visible(fn(): bool => Auth::user()?->hasPermissionTo('Criar Pautas') ?? false)
                ->action(fn() => $this->abrirModalCriacao()),
        ];
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
                    ->helperText('Selecione uma ou mais alternativas já cadastradas para vincular nesta pauta.')
                    ->options(fn(): array => $this->alternativasSelectOptions)
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live(),
            ])
            ->statePath('form');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Pauta::query()
                    ->with([
                        'tipo:id,nome',
                        'serie:id,nome',
                        'componente:id,nome',
                    ])
                    ->withCount(['alternativas', 'avaliacoes'])
            )
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('texto')
                    ->label('Pauta')
                    ->limit(80)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('tipo.nome')
                    ->label('Tipo')
                    ->sortable()
                    ->placeholder('Sem tipo'),

                TextColumn::make('serie.nome')
                    ->label('Série')
                    ->sortable()
                    ->placeholder('Sem série'),

                TextColumn::make('componente.nome')
                    ->label('Componente')
                    ->sortable()
                    ->placeholder('Geral'),

                TextColumn::make('alternativas_count')
                    ->label('Alternativas')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('avaliacoes_count')
                    ->label('Avaliações')
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('status')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label('Atualizada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo_avaliacao_id')
                    ->label('Tipo')
                    ->options(
                        fn(): array => TipoAvaliacao::query()
                            ->where('status', true)
                            ->orderBy('nome')
                            ->pluck('nome', 'id')
                            ->toArray()
                    ),

                SelectFilter::make('serie_id')
                    ->label('Série')
                    ->options(
                        fn(): array => Serie::query()
                            ->orderBy('nome')
                            ->pluck('nome', 'id')
                            ->toArray()
                    ),

                SelectFilter::make('componente_curricular_id')
                    ->label('Componente')
                    ->options(
                        fn(): array => ComponenteCurricular::query()
                            ->orderBy('nome')
                            ->pluck('nome', 'id')
                            ->toArray()
                    ),

                TernaryFilter::make('status')
                    ->label('Status')
                    ->trueLabel('Ativas')
                    ->falseLabel('Inativas')
                    ->native(false),
            ])
            ->recordActions([
                Action::make('editar')
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare)
                    ->visible(fn(): bool => Auth::user()?->hasPermissionTo('Editar Pautas') ?? false)
                    ->action(fn(Pauta $record) => $this->abrirModalEdicao($record->getKey())),

                Action::make('excluir')
                    ->label('Excluir')
                    ->icon(Heroicon::Trash)
                    ->color('danger')
                    ->visible(fn(): bool => Auth::user()?->hasPermissionTo('Excluir Pautas') ?? false)
                    ->requiresConfirmation()
                    ->action(fn(Pauta $record) => $this->excluirPauta($record->getKey())),
            ])
            ->groupedBulkActions([
                BulkAction::make('aplicarCampos')
                    ->label('Aplicar campos')
                    ->icon(Heroicon::AdjustmentsHorizontal)
                    ->visible(fn(): bool => Auth::user()?->hasPermissionTo('Editar Pautas') ?? false)
                    ->form([
                        Select::make('tipo_avaliacao_id')
                            ->label('Tipo')
                            ->options(fn(): array => $this->tiposOptions)
                            ->searchable()
                            ->preload(),
                        Select::make('serie_id')
                            ->label('Série')
                            ->options(fn(): array => $this->seriesOptions)
                            ->searchable()
                            ->preload(),
                        Select::make('componente_curricular_id')
                            ->label('Componente')
                            ->options(fn(): array => $this->componentesOptions)
                            ->searchable()
                            ->preload(),
                    ])
                    ->action(function (array $data, $records): void {
                        $ids = collect($records)->map(fn(Pauta $record): int => (int) $record->getKey())->values();

                        $updates = collect([
                            'tipo_avaliacao_id' => $data['tipo_avaliacao_id'] ?? null,
                            'serie_id' => $data['serie_id'] ?? null,
                            'componente_curricular_id' => $data['componente_curricular_id'] ?? null,
                        ])->filter(fn($value): bool => filled($value))->all();

                        if ($updates === []) {
                            throw ValidationException::withMessages([
                                'tipo_avaliacao_id' => 'Informe ao menos um campo para aplicar em massa.',
                            ]);
                        }

                        $updates['updated_at'] = now();

                        $quantidadeAtualizada = Pauta::query()
                            ->whereIn('id', $ids->all())
                            ->update($updates);

                        Notification::make()
                            ->title("Campos aplicados em {$quantidadeAtualizada} pauta(s).")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('updated_at', 'desc');
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
            ->when($tipoAvaliacaoId > 0, fn($query) => $query->where('tipo_avaliacao_id', $tipoAvaliacaoId))
            ->orderBy('nome')
            ->get(['id', 'nome', 'tem_observacao', 'vai_no_documento', 'status']);
    }

    public function getAlternativasSelectOptionsProperty(): array
    {
        return $this->alternativasOptions
            ->mapWithKeys(function (Alternativa $alternativa): array {
                $sufixos = [];

                if ($alternativa->tem_observacao) {
                    $sufixos[] = 'exige observação';
                }

                if (! $alternativa->vai_no_documento) {
                    $sufixos[] = 'fora do documento';
                }

                if (! $alternativa->status) {
                    $sufixos[] = 'inativa';
                }

                $label = $alternativa->nome;

                if ($sufixos !== []) {
                    $label .= ' (' . implode(', ', $sufixos) . ')';
                }

                return [(int) $alternativa->id => $label];
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
        $this->modalAbaPautas = 'configuracao';
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

        $this->pautaIdEditando = (int) $pauta->getKey();
        $this->form = [
            'tipo_avaliacao_id' => $pauta->tipo_avaliacao_id,
            'textos' => [
                ['texto' => (string) $pauta->texto],
            ],
            'serie_id' => $pauta->serie_id,
            'componente_curricular_id' => $pauta->componente_curricular_id,
            'status' => (bool) $pauta->status,
            'alternativas_ids' => $pauta->alternativas->pluck('id')->map(fn($id) => (int) $id)->all(),
        ];
        $this->novasAlternativas = [];
        $this->modalAbaPautas = 'configuracao';
        $this->modalAberto = true;
        $this->resetValidation();
    }

    public function abrirAbaPautas(string $aba): void
    {
        if (! in_array($aba, ['configuracao', 'textos'], true)) {
            return;
        }

        $this->modalAbaPautas = $aba;
    }

    public function avancarParaTextosPautas(): void
    {
        $this->modalAbaPautas = 'textos';
    }

    public function voltarParaConfiguracaoPautas(): void
    {
        $this->modalAbaPautas = 'configuracao';
    }

    public function adicionarTextoPauta(): void
    {
        if (filled($this->pautaIdEditando)) {
            return;
        }

        $this->form['textos'][] = ['texto' => ''];
    }

    public function removerTextoPauta(int $index): void
    {
        if (filled($this->pautaIdEditando) || ! isset($this->form['textos'][$index])) {
            return;
        }

        unset($this->form['textos'][$index]);
        $this->form['textos'] = array_values($this->form['textos']);

        if ($this->form['textos'] === []) {
            $this->form['textos'][] = ['texto' => ''];
        }
    }

    public function adicionarNovaAlternativa(): void
    {
        $this->novasAlternativas[] = [
            'nome' => '',
            'tem_observacao' => false,
            'observacao' => '',
            'vai_no_documento' => true,
            'descricao_documento' => '',
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

        try {
            $validated = $this->validate([
                'form.tipo_avaliacao_id' => ['required', 'integer', 'exists:tipos_avaliacao,id'],
                'form.textos' => ['required', 'array', 'min:1'],
                'form.textos.*.texto' => ['nullable', 'string', 'max:2000'],
                'form.serie_id' => ['required', 'integer', 'exists:series,id'],
                'form.componente_curricular_id' => ['nullable', 'exists:componentes_curriculares,id'],
                'form.status' => ['required', 'boolean'],
                'form.alternativas_ids' => ['array'],
                'form.alternativas_ids.*' => ['integer', 'exists:alternativas,id'],
                'novasAlternativas' => ['array'],
                'novasAlternativas.*.nome' => ['nullable', 'string', 'max:255'],
                'novasAlternativas.*.tem_observacao' => ['required', 'boolean'],
                'novasAlternativas.*.observacao' => ['nullable', 'string', 'max:1000'],
                'novasAlternativas.*.vai_no_documento' => ['required', 'boolean'],
                'novasAlternativas.*.descricao_documento' => ['nullable', 'string', 'max:1000'],
                'novasAlternativas.*.status' => ['required', 'boolean'],
            ]);
        } catch (ValidationException $exception) {
            $this->modalAbaPautas = collect($exception->validator->errors()->keys())->contains(
                fn(string $key): bool => str_starts_with($key, 'form.textos')
            )
                ? 'textos'
                : 'configuracao';

            throw $exception;
        }

        $textosPautas = collect($validated['form']['textos'] ?? [])
            ->map(fn(array $item): string => trim((string) ($item['texto'] ?? '')))
            ->filter(fn(string $texto): bool => $texto !== '')
            ->values();

        if ($textosPautas->isEmpty()) {
            $this->modalAbaPautas = 'textos';
            $this->addError('form.textos.0.texto', 'Informe ao menos um texto de pauta.');

            return;
        }

        if ($isEdicao) {
            $textosPautas = $textosPautas->take(1)->values();
        }

        $validated['form']['texto'] = $textosPautas->first();

        $alternativasSelecionadas = collect($validated['form']['alternativas_ids'] ?? [])
            ->filter()
            ->map(fn($id) => (int) $id)
            ->values();

        $novasAlternativasComNome = collect($validated['novasAlternativas'] ?? [])
            ->map(function (array $item): array {
                $temObservacao = (bool) ($item['tem_observacao'] ?? false);
                $vaiNoDocumento = (bool) ($item['vai_no_documento'] ?? true);

                return [
                    'nome' => trim((string) ($item['nome'] ?? '')),
                    'tem_observacao' => $temObservacao,
                    'observacao' => $temObservacao && filled($item['observacao'] ?? null)
                        ? trim((string) $item['observacao'])
                        : null,
                    'vai_no_documento' => $vaiNoDocumento,
                    'descricao_documento' => $vaiNoDocumento && filled($item['descricao_documento'] ?? null)
                        ? trim((string) $item['descricao_documento'])
                        : null,
                    'status' => (bool) ($item['status'] ?? true),
                ];
            })
            ->filter(fn(array $item): bool => $item['nome'] !== '')
            ->values();

        $alternativasIncompativeisComTipo = Alternativa::query()
            ->whereIn('id', $alternativasSelecionadas->all())
            ->where('tipo_avaliacao_id', '!=', (int) $validated['form']['tipo_avaliacao_id'])
            ->count();

        if ($alternativasIncompativeisComTipo > 0) {
            $this->modalAbaPautas = 'configuracao';
            $this->addError('form.alternativas_ids', 'Selecione apenas alternativas do mesmo tipo da pauta.');

            return;
        }

        $pautasAfetadas = DB::transaction(function () use ($isEdicao, $validated, $textosPautas, $alternativasSelecionadas, $novasAlternativasComNome): int {
            if ($isEdicao) {
                $pauta = Pauta::query()->find($this->pautaIdEditando);

                if (! $pauta) {
                    throw new \RuntimeException('Pauta não encontrada para edição.');
                }
            } else {
                $pauta = new Pauta;
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
                    return (int) Alternativa::query()->create([
                        ...$item,
                        'tipo_avaliacao_id' => (int) $validated['form']['tipo_avaliacao_id'],
                    ])->getKey();
                });

            $idsFinal = $alternativasSelecionadas
                ->merge($novosIds)
                ->unique()
                ->values()
                ->all();

            $pauta->alternativas()->sync($idsFinal);

            if ($isEdicao) {
                return 1;
            }

            foreach ($textosPautas->skip(1) as $texto) {
                $pauta = Pauta::query()->create([
                    'tipo_avaliacao_id' => (int) $validated['form']['tipo_avaliacao_id'],
                    'texto' => $texto,
                    'serie_id' => (int) $validated['form']['serie_id'],
                    'componente_curricular_id' => $validated['form']['componente_curricular_id'] ?: null,
                    'status' => (bool) $validated['form']['status'],
                ]);

                $pauta->alternativas()->sync($idsFinal);
            }

            return $textosPautas->count();
        });

        $this->fecharModal();

        Notification::make()
            ->title($isEdicao ? 'Pauta atualizada com sucesso.' : "{$pautasAfetadas} pauta(s) criada(s) com sucesso.")
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

    private function resetForm(): void
    {
        $this->form = [
            'tipo_avaliacao_id' => null,
            'textos' => [
                ['texto' => ''],
            ],
            'serie_id' => null,
            'componente_curricular_id' => null,
            'status' => true,
            'alternativas_ids' => [],
        ];
        $this->novasAlternativas = [];
    }
}
