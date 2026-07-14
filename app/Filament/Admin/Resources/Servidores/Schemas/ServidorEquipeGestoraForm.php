<?php

namespace App\Filament\Admin\Resources\Servidores\Schemas;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Professor;
use App\Models\Turma;
use App\Services\UserService;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;

class ServidorEquipeGestoraForm
{
    public const CARGO_DIRETOR = 'diretor';
    public const CARGO_COORDENADOR = 'coordenador';
    public const CARGO_SECRETARIO = 'secretario';

    public static function section(): Section
    {
        return Section::make('Equipe Gestora')
            ->description('Defina a escola, os cargos ativos, a portaria e as turmas sob responsabilidade da coordenação.')
            ->icon('heroicon-o-building-office-2')
            ->schema([
                Select::make('id_escola')
                    ->label('Escola / CMEI')
                    ->options(fn (): array => app(UserService::class)->opcoesDeEscolasParaCampo(Auth::user()))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state, mixed $old): void {
                        if ((string) $state === (string) $old) {
                            return;
                        }

                        $set('turma_ids', []);
                        $set('turmas_principais_ids', []);
                        $set('diretor_principal', false);
                        $set('data_inicio', null);
                    })
                    ->columnSpanFull(),

                CheckboxList::make('cargos_gestores')
                    ->label('Cargos gestores')
                    ->options([
                        self::CARGO_DIRETOR => 'Diretor',
                        self::CARGO_COORDENADOR => 'Coordenador',
                        self::CARGO_SECRETARIO => 'Secretário',
                    ])
                    ->required()
                    ->minItems(1)
                    ->maxItems(2)
                    ->columns(3)
                    ->live()
                    ->rule(static function (): Closure {
                        return static function (string $attribute, mixed $value, Closure $fail): void {
                            $cargos = collect(is_array($value) ? $value : [])->filter()->values();

                            if ($cargos->contains(self::CARGO_SECRETARIO) && $cargos->count() > 1) {
                                $fail('Secretário é exclusivo e não pode ser combinado com outro cargo gestor.');
                            }

                            $invalidos = $cargos->diff([
                                self::CARGO_DIRETOR,
                                self::CARGO_COORDENADOR,
                                self::CARGO_SECRETARIO,
                            ]);

                            if ($invalidos->isNotEmpty()) {
                                $fail('Há um cargo gestor inválido no formulário.');
                            }
                        };
                    })
                    ->columnSpanFull(),

                TextInput::make('portaria')
                    ->label('Portaria')
                    ->helperText('Diretor e Coordenador compartilham a mesma portaria alfanumérica.')
                    ->required(fn (Get $get): bool => self::possuiCargoComPortaria($get))
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, mixed $state, mixed $old): void {
                        if ((string) $state !== (string) $old) {
                            $set('data_inicio', null);
                        }
                    })
                    ->maxLength(255),

                DatePicker::make('data_inicio')
                    ->label('Início da vigência')
                    ->required()
                    ->native(false),

                Toggle::make('diretor_principal')
                    ->label('Diretor principal da escola')
                    ->helperText('Ao marcar, a substituição é transacional. Sem Diretor principal, os pareceres da escola ficam bloqueados.')
                    ->visible(fn (Get $get): bool => self::possuiCargo($get, self::CARGO_DIRETOR))
                    ->default(false)
                    ->columnSpanFull(),

                CheckboxList::make('turma_ids')
                    ->label('Turmas da coordenação')
                    ->helperText('“Selecionar todas” vincula somente as turmas existentes nesta escola.')
                    ->options(fn (Get $get): array => self::turmasOptions($get('id_escola')))
                    ->required(fn (Get $get): bool => self::possuiCargo($get, self::CARGO_COORDENADOR))
                    ->minItems(1)
                    ->bulkToggleable()
                    ->searchable()
                    ->columns(2)
                    ->live()
                    ->visible(fn (Get $get): bool => self::possuiCargo($get, self::CARGO_COORDENADOR))
                    ->columnSpanFull(),

                CheckboxList::make('turmas_principais_ids')
                    ->label('Coordenação principal por turma')
                    ->helperText('Marque as turmas nas quais esta Pessoa será principal. Turmas sem principal ficam com parecer bloqueado.')
                    ->options(function (Get $get): array {
                        $selecionadas = collect($get('turma_ids') ?? [])
                            ->map(fn (mixed $id): int => (int) $id)
                            ->filter()
                            ->values();

                        return collect(self::turmasOptions($get('id_escola')))
                            ->only($selecionadas->all())
                            ->all();
                    })
                    ->bulkToggleable()
                    ->searchable()
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::possuiCargo($get, self::CARGO_COORDENADOR))
                    ->rule(static function (Get $get): Closure {
                        return static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $turmas = collect($get('turma_ids') ?? [])->map(fn (mixed $id): int => (int) $id);
                            $principais = collect(is_array($value) ? $value : [])->map(fn (mixed $id): int => (int) $id);

                            if ($principais->diff($turmas)->isNotEmpty()) {
                                $fail('Uma turma principal precisa estar entre as turmas vinculadas à coordenação.');
                            }
                        };
                    })
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->visible(fn (Get $get): bool => ($get('cargo') ?? null) === ServidorResource::CARGO_EQUIPE_GESTORA)
            ->columnSpanFull()
            ->collapsible();
    }

    public static function usuarioPodeAdministrar(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->hasRole('Admin') || $user?->hasPermissionTo('Gerenciar Funções de Servidores'));
    }

    /** @return array<int, string> */
    private static function turmasOptions(int|string|null $escolaId): array
    {
        if (! filled($escolaId)) {
            return [];
        }

        return Turma::query()
            ->where('id_escola', (int) $escolaId)
            ->with('serie:id,nome')
            ->orderBy('turno')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(function (Turma $turma): array {
                $turno = Professor::turnosOptions()[$turma->turno] ?? $turma->turno;
                $nome = collect([$turma->serie?->nome, $turma->nome])->filter()->implode(' - ');

                return [$turma->id => trim("{$nome} ({$turno})")];
            })
            ->all();
    }

    private static function possuiCargo(Get $get, string $cargo): bool
    {
        return in_array($cargo, $get('cargos_gestores') ?? [], true);
    }

    private static function possuiCargoComPortaria(Get $get): bool
    {
        return self::possuiCargo($get, self::CARGO_DIRETOR)
            || self::possuiCargo($get, self::CARGO_COORDENADOR);
    }
}
