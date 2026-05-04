<?php

namespace App\Filament\Admin\Resources\Contratos\Schemas;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
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
                            ->searchable()
                            ->preload()
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
