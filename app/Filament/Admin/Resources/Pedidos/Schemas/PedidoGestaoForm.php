<?php

namespace App\Filament\Admin\Resources\Pedidos\Schemas;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Setor;
use App\Models\TipoStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class PedidoGestaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Pedido')
                    ->schema([
                        View::make('components.pedido.pedido-cabecalho')
                            ->viewData(fn ($record) => [
                                'record' => $record,
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Gestão do Pedido')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        Select::make('novo_status_id')
                            ->label('Atualizar Status')
                            ->reactive()
                            ->options(
                                fn () => TipoStatus::query()
                                    ->where('ativo', true)
                                    ->whereNotIn('nome', ['Em Aberto', 'Em Análise', 'Concluído'])
                                    ->orderBy('nome')
                                    ->pluck('nome', 'id')
                                    ->toArray()
                            )
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                if (! $state) {
                                    return;
                                }

                                $status = TipoStatus::find($state);

                                if (! $status) {
                                    return;
                                }

                                $setor = ($setorId = $get('setor_id'))
                                    ? Setor::find($setorId)
                                    : null;

                                if ($status->nome === 'Encaminhado ao Setor' && ! $setor?->encaminhaPedidoParaSetor) {
                                    $set('novo_status_id', null);

                                    Notification::make()
                                        ->title('Status inválido')
                                        ->body('Para encaminhar ao setor, selecione um setor com destino configurado.')
                                        ->danger()
                                        ->send();
                                }
                            })
                            ->helperText('Use este status quando o pedido precisar seguir para o próximo setor do fluxo.')
                            ->placeholder('Padrão: Em Análise')
                            ->searchable()
                            ->nullable(),

                        Select::make('nivel_prioridade')
                            ->label('Nível de Prioridade')
                            ->options(
                                collect(NivelEmergenciaPedido::cases())
                                    ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                    ->toArray()
                            )
                            ->nullable()
                            ->native(false),

                        Select::make('setor_id')
                            ->label('Setor')
                            ->relationship(
                                name: 'setor',
                                titleAttribute: 'nome',
                                modifyQueryUsing: fn ($query) => $query->where('ativo', true)
                            )
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $statusId = $get('novo_status_id');

                                if (! $statusId) {
                                    return;
                                }

                                $status = TipoStatus::find($statusId);

                                if (! $status) {
                                    return;
                                }

                                $setor = $state ? Setor::find($state) : null;

                                if ($status->nome === 'Encaminhado ao Setor' && ! $setor?->encaminhaPedidoParaSetor) {
                                    $set('novo_status_id', null);

                                    Notification::make()
                                        ->title('Status removido')
                                        ->body('Encaminhado ao Setor exige um setor com encaminhamento configurado.')
                                        ->warning()
                                        ->send();
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        TextInput::make('valor_custo')
                            ->label('Valor gasto')
                            ->numeric()
                            ->prefix('R$')
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->minValue(0)
                            ->nullable(),

                        DatePicker::make('data_prevista')
                            ->label('Data Prevista')
                            ->required(),

                        Select::make('empresa_contratada_id')
                            ->label('Empresa Responsável')
                            ->relationship(
                                name: 'empresaContratada',
                                titleAttribute: 'nome',
                                modifyQueryUsing: fn ($query) => $query->where('ativo', true)
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        DatePicker::make('data_entrega')
                            ->label('Data de Entrega')
                            ->nullable(),

                        Textarea::make('descricao_alteracao')
                            ->label('Descrição da Alteração')
                            ->placeholder('Descreva o que foi feito ou observado...')
                            ->rows(4)
                            ->required()
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
