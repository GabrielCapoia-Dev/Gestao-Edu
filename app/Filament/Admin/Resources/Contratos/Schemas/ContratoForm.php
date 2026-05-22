<?php

namespace App\Filament\Admin\Resources\Contratos\Schemas;

use App\Models\EmpresaContratada;
use App\Models\Item;
use App\Services\UserSetorAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ContratoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do Contrato')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('numero_contrato')
                            ->label('Número do Contrato')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(1),

                        Select::make('id_empresa_contratada')
                            ->label('Empresa Contratada')
                            ->relationship(
                                name: 'empresaContratada',
                                titleAttribute: 'nome',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('ativo', true)
                                    ->doSetorDoUsuario(Auth::user())
                            )
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $set('setor_id', EmpresaContratada::query()->whereKey($state)->value('setor_id'));
                            })
                            ->searchable()
                            ->required()
                            ->columnSpan(1),

                        Select::make('setor_id')
                            ->label('Setor do contrato')
                            ->options(function (Get $get): array {
                                $empresaSetorId = filled($get('id_empresa_contratada'))
                                    ? EmpresaContratada::query()
                                        ->whereKey($get('id_empresa_contratada'))
                                        ->value('setor_id')
                                    : null;

                                return app(UserSetorAccessService::class)->optionsForSelect(Auth::user(), $empresaSetorId);
                            })
                            ->default(fn () => app(UserSetorAccessService::class)->primarySetorId(Auth::user()))
                            ->searchable()
                            ->required()
                            ->columnSpan(1),

                        DatePicker::make('data_inicio')
                            ->label('Data de Início')
                            ->required()
                            ->columnSpan(1),

                        DatePicker::make('data_vencimento')
                            ->label('Data de Vencimento')
                            ->after('data_inicio')
                            ->columnSpan(1),

                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('ativo')
                            ->label('Ativo')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
