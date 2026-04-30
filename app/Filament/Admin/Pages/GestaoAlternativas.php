<?php

namespace App\Filament\Admin\Pages;

use App\Models\Alternativa;
use App\Models\TipoAvaliacao;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class GestaoAlternativas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationParentItem = 'Avaliações';

    protected string $view = 'filament.pages.gestao-alternativas';

    protected static ?string $title = 'Gestão de Alternativas';

    protected static ?string $navigationLabel = 'Alternativas';

    protected static ?string $slug = 'avaliacoes-alternativas';

    protected static ?int $navigationSort = 24;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::QueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

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

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo('Listar Alternativas') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Nova alternativa')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->visible(fn (): bool => Auth::user()?->hasPermissionTo('Criar Alternativas') ?? false)
                ->action(fn () => $this->abrirModalCriacao()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Alternativa::query()
                    ->with(['tipo:id,nome'])
                    ->withCount(['pautas'])
            )
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('tipo.nome')
                    ->label('Tipo')
                    ->sortable()
                    ->placeholder('Sem tipo'),

                IconColumn::make('tem_observacao')
                    ->label('Exige observação?')
                    ->boolean()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('observacao')
                    ->label('Observação padrão')
                    ->limit(80)
                    ->placeholder('Sem observação')
                    ->toggleable(),

                IconColumn::make('status')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('pautas_count')
                    ->label('Qtd. Pautas')
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
                    ->options(fn (): array => TipoAvaliacao::query()
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->toArray()
                    ),

                TernaryFilter::make('status')
                    ->label('Status')
                    ->trueLabel('Ativas')
                    ->falseLabel('Inativas')
                    ->native(false),

                TernaryFilter::make('tem_observacao')
                    ->label('Exige observação')
                    ->trueLabel('Sim')
                    ->falseLabel('Não')
                    ->native(false),
            ])
            ->actions([
                Action::make('editar')
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare)
                    ->visible(fn (): bool => Auth::user()?->hasPermissionTo('Editar Alternativas') ?? false)
                    ->action(fn (Alternativa $record) => $this->abrirModalEdicao($record->getKey())),

                Action::make('excluir')
                    ->label('Excluir')
                    ->icon(Heroicon::Trash)
                    ->color('danger')
                    ->visible(fn (): bool => Auth::user()?->hasPermissionTo('Excluir Alternativas') ?? false)
                    ->requiresConfirmation()
                    ->action(fn (Alternativa $record) => $this->excluirAlternativa($record->getKey())),
            ])
            ->groupedBulkActions([
                BulkAction::make('definirTipo')
                    ->label('Definir tipo')
                    ->icon(Heroicon::Tag)
                    ->visible(fn (): bool => Auth::user()?->hasPermissionTo('Editar Alternativas') ?? false)
                    ->form([
                        Select::make('tipo_avaliacao_id')
                            ->label('Tipo')
                            ->options(fn (): array => TipoAvaliacao::query()
                                ->where('status', true)
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                                ->toArray()
                            )
                            ->searchable()
                            ->preload(),
                        TextInput::make('novo_tipo_nome')
                            ->label('Ou criar novo tipo')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, $records): void {
                        $ids = collect($records)->map(fn (Alternativa $record): int => (int) $record->getKey())->all();

                        $novoTipoNome = Str::of((string) ($data['novo_tipo_nome'] ?? ''))->trim()->toString();
                        $tipoAvaliacaoId = (int) ($data['tipo_avaliacao_id'] ?? 0);

                        if ($novoTipoNome !== '') {
                            $tipoAvaliacaoId = (int) TipoAvaliacao::query()
                                ->firstOrCreate(
                                    ['nome' => $novoTipoNome],
                                    ['status' => true]
                                )
                                ->getKey();
                        }

                        if ($tipoAvaliacaoId <= 0) {
                            throw ValidationException::withMessages([
                                'tipo_avaliacao_id' => 'Selecione um tipo existente ou informe um novo tipo.',
                            ]);
                        }

                        Alternativa::query()
                            ->whereKey($ids)
                            ->update([
                                'tipo_avaliacao_id' => $tipoAvaliacaoId,
                                'updated_at' => now(),
                            ]);

                        Notification::make()
                            ->title('Tipo aplicado com sucesso.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('updated_at', 'desc');
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

        $this->alternativaIdEditando = (int) $alternativa->getKey();
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
                ->getKey();
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
            $alternativa = new Alternativa;
        }

        $temObservacao = (bool) ($validated['form']['tem_observacao'] ?? false);
        $observacao = $temObservacao && filled($validated['form']['observacao'] ?? null)
            ? trim((string) $validated['form']['observacao'])
            : null;

        $alternativa->fill([
            'tipo_avaliacao_id' => $tipoAvaliacaoId,
            'nome' => trim((string) $validated['form']['nome']),
            'tem_observacao' => $temObservacao,
            'observacao' => $observacao,
            'status' => (bool) ($validated['form']['status'] ?? true),
        ]);
        $alternativa->save();

        $this->modalAberto = false;

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
