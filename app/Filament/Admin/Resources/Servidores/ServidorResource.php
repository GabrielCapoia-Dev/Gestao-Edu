<?php

namespace App\Filament\Admin\Resources\Servidores;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Services\ServidorService;
use App\Services\UserService;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ServidorResource extends Resource
{
    public const CARGO_PROFESSOR = 'professor';

    protected static ?string $model = Servidor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Briefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Acesso';

    protected static ?string $navigationLabel = 'Pessoas';

    protected static ?int $navigationSort = 1;

    protected static ?string $pluralModelLabel = 'Pessoas';

    protected static ?string $modelLabel = 'Pessoa';

    protected static ?string $slug = 'servidores';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identidade da pessoa')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('cpf')
                            ->label('CPF')
                            ->mask('999.999.999-99')
                            ->maxLength(14),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Professor::normalizarEmail($state) : null)
                            ->rule(function (): Closure {
                                return function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! Professor::emailInstitucionalValido((string) $value)) {
                                        $fail('Use somente e-mail institucional @edu.umuarama.pr.gov.br.');
                                    }
                                };
                            })
                            ->maxLength(255),

                        TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->mask('(99) 99999-9999')
                            ->maxLength(255),

                        Select::make('status')
                            ->label('Status')
                            ->options(Servidor::statusOptions())
                            ->default(Servidor::STATUS_ATIVO)
                            ->required(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),

                Section::make('Cargo')
                    ->schema([
                        Select::make('cargo')
                            ->label('Função / cargo')
                            ->options([
                                self::CARGO_PROFESSOR => 'Professor',
                            ])
                            ->default(self::CARGO_PROFESSOR)
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columnSpanFull(),

                Section::make('Registros do professor')
                    ->schema([
                        Repeater::make('registros_professor')
                            ->label('Matrículas e lotações')
                            ->schema([
                                Hidden::make('id'),

                                TextInput::make('matricula')
                                    ->label('Matrícula')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('turno')
                                    ->label('Turno')
                                    ->options(Professor::turnosOptions())
                                    ->required(),

                                Select::make('id_escola')
                                    ->label('Escola / CMEI')
                                    ->options(fn (): array => app(UserService::class)->opcoesDeEscolasParaCampo(Auth::user()))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                Repeater::make('vinculos_turma_componente')
                                    ->label('Turmas e componentes')
                                    ->schema([
                                        Select::make('turma_id')
                                            ->label('Turma')
                                            ->options(fn (Get $get): array => static::turmasOptions($get('../../id_escola')))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live(),

                                        Select::make('componente_curricular_id')
                                            ->label('Componente')
                                            ->options(fn (Get $get): array => static::componentesOptions($get('turma_id')))
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Vincular turma')
                                    ->visible(fn (Get $get): bool => filled($get('id_escola')))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->required()
                            ->minItems(1)
                            ->addActionLabel('Adicionar registro')
                            ->helperText('O setor é definido automaticamente pela escola. Os vínculos com turmas são opcionais.')
                            ->afterStateHydrated(function (Repeater $component, ?Servidor $record): void {
                                if (! $record) {
                                    return;
                                }

                                $professores = $record->professores()
                                    ->with(['escola:id,nome'])
                                    ->get();

                                $vinculosPorProfessor = TurmaComponenteProfessor::query()
                                    ->whereIn('professor_id', $professores->pluck('id'))
                                    ->where('tem_professor', true)
                                    ->whereNotNull('professor_id')
                                    ->get()
                                    ->groupBy('professor_id');

                                $component->state(
                                    $professores->map(fn (Professor $professor): array => [
                                        'id' => $professor->id,
                                        'matricula' => $professor->matricula,
                                        'turno' => $professor->turno,
                                        'id_escola' => $professor->id_escola,
                                        'vinculos_turma_componente' => ($vinculosPorProfessor->get($professor->id) ?? collect())
                                            ->map(fn (TurmaComponenteProfessor $vinculo): array => [
                                                'turma_id' => $vinculo->turma_id,
                                                'componente_curricular_id' => $vinculo->componente_curricular_id,
                                            ])
                                            ->values()
                                            ->all(),
                                    ])->all()
                                );
                            })
                            ->columnSpanFull(),

                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'escola:id,nome,setor_id',
                'setor:id,nome',
                'user:id,name,email',
                'professores.escola:id,nome',
            ]))
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('vinculos_resumo')
                    ->label('Matrículas')
                    ->getStateUsing(fn (Servidor $record): string => $record->professores
                        ->pluck('matricula')
                        ->filter()
                        ->unique()
                        ->implode(', ') ?: '—')
                    ->wrap(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->copyable(),

                TextColumn::make('cargo_label')
                    ->label('Cargo')
                    ->getStateUsing(fn (Servidor $record): string => $record->professores->isNotEmpty() ? 'Professor' : '—')
                    ->badge(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Servidor::statusOptions()[$state] ?? 'Não informado')
                    ->color(fn (?string $state): string => match ($state) {
                        Servidor::STATUS_ATIVO => 'success',
                        Servidor::STATUS_INATIVO => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->placeholder('Sem acesso')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('id_escola')
                    ->label('Escola')
                    ->options(fn (): array => app(UserService::class)->opcoesDeEscolasParaCampo(Auth::user()))
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('professores', fn (Builder $professores): Builder => $professores
                            ->where('id_escola', (int) $data['value']));
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Servidor::statusOptions()),

                TernaryFilter::make('user_id')
                    ->label('Usuário vinculado')
                    ->trueLabel('Com usuário')
                    ->falseLabel('Sem usuário')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('user_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (Servidor $record): string => "Detalhes - {$record->nome}")
                    ->modalWidth('3xl')
                    ->schema([
                        Section::make('Informações da pessoa')
                            ->schema([
                                TextEntry::make('nome')->label('Nome'),
                                TextEntry::make('cpf')->label('CPF')->placeholder('Não informado'),
                                TextEntry::make('email')->label('E-mail')->placeholder('Não informado'),
                                TextEntry::make('user.name')->label('Usuário')->placeholder('Sem acesso'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn (?string $state): string => Servidor::statusOptions()[$state] ?? 'Não informado'),
                            ])
                            ->columns(2),

                        Section::make('Registros do professor')
                            ->schema([
                                TextEntry::make('registros_resumo')
                                    ->label('Lotações')
                                    ->getStateUsing(fn (Servidor $record): array => $record->professores
                                        ->map(fn (Professor $professor): string => sprintf(
                                            '%s — %s — %s',
                                            $professor->matricula,
                                            $professor->turnoLabel(),
                                            $professor->escola?->nome ?? 'Sem escola',
                                        ))
                                        ->all())
                                    ->badge()
                                    ->separator(','),
                            ]),
                    ]),

                EditAction::make()
                    ->using(function (Servidor $record, array $data): Servidor {
                        $registros = $data['registros_professor'] ?? [];
                        unset($data['registros_professor']);
                        $data['cargo'] = $data['cargo'] ?? self::CARGO_PROFESSOR;

                        return app(ServidorService::class)->atualizarServidorComFuncoes(
                            $record,
                            $data,
                            ['registros_professor' => $registros],
                        );
                    }),

                DeleteAction::make(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServidores::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $policy = Gate::getPolicyFor(static::getModel());

        if ($user && $policy && method_exists($policy, 'applyViewAnyScope')) {
            return $policy->applyViewAnyScope($user, $query);
        }

        return $query->whereRaw('1 = 0');
    }

    private static function turmasOptions(int|string|null $escolaId): array
    {
        if (! $escolaId) {
            return [];
        }

        return Turma::query()
            ->where('id_escola', $escolaId)
            ->with('serie')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Turma $turma): array => [
                $turma->id => trim(($turma->serie?->nome ? $turma->serie->nome.' - ' : '').$turma->nome.' ('.$turma->turno.')'),
            ])
            ->toArray();
    }

    private static function componentesOptions(int|string|null $turmaId): array
    {
        if (! $turmaId) {
            return [];
        }

        $turma = Turma::query()
            ->with('serie.componentesCurriculares')
            ->find($turmaId);

        if (! $turma?->serie) {
            return [];
        }

        return $turma->serie->componentesCurriculares
            ->sortBy('nome')
            ->mapWithKeys(fn ($componente): array => [
                $componente->id => $componente->nome,
            ])
            ->toArray();
    }
}