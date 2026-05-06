<?php

namespace App\Filament\Admin\Resources\Pedidos\Schemas;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\TipoStatus;
use App\Services\PedidoService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
                            ->options(fn () => static::statusOptions())
                            ->afterStateUpdated(function ($state, callable $set) {
                                $status = $state ? TipoStatus::find($state) : null;

                                if (! static::statusEh($status, 'Encaminhado ao Setor')) {
                                    $set('setor_id', null);
                                }

                                if (! static::statusEh($status, 'Enviado para Empresa')) {
                                    $set('empresa_contratada_id', null);
                                }
                            })
                            ->helperText('Educação pode encaminhar para Obras; Obras pode enviar para empresa.')
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
                            ->label('Setor destino')
                            ->relationship(
                                name: 'setor',
                                titleAttribute: 'nome',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('ativo', true)
                                    ->orderBy('nome')
                            )
                            ->visible(fn (Get $get) => static::statusEh(TipoStatus::find($get('novo_status_id')), 'Encaminhado ao Setor'))
                            ->required(fn (Get $get) => static::statusEh(TipoStatus::find($get('novo_status_id')), 'Encaminhado ao Setor'))
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
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('ativo', true)
                                    ->doSetorDoUsuario(Auth::user())
                            )
                            ->visible(fn (Get $get) => app(PedidoService::class)->podeEnviarParaEmpresa(Auth::user())
                                && static::statusEh(TipoStatus::find($get('novo_status_id')), 'Enviado para Empresa'))
                            ->required(fn (Get $get) => app(PedidoService::class)->podeEnviarParaEmpresa(Auth::user())
                                && static::statusEh(TipoStatus::find($get('novo_status_id')), 'Enviado para Empresa'))
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

    private static function statusOptions(): array
    {
        $service = app(PedidoService::class);
        $user = Auth::user();

        $nomes = [];

        if ($service->usuarioEhSetor($user, 'Educação')) {
            $nomes = array_merge($nomes, [
                'Em Manutenção',
                'Encaminhado ao Setor',
                'Cancelado',
            ]);
        }

        if ($service->usuarioEhSetor($user, 'Obras')) {
            $nomes = array_merge($nomes, [
                'Em Manutenção',
                'Enviado para Empresa',
                'Cancelado',
            ]);
        }

        return collect($nomes)
            ->unique()
            ->map(fn (string $nome) => $service->statusPorNome($nome))
            ->filter(fn (?TipoStatus $status) => $status?->ativo)
            ->sortBy('nome')
            ->mapWithKeys(fn (TipoStatus $status) => [$status->id => $status->nome])
            ->toArray();
    }

    private static function statusEh(?TipoStatus $status, string $nome): bool
    {
        if (! $status) {
            return false;
        }

        return app(PedidoService::class)->statusPorNome($nome)?->is($status) ?? false;
    }
}
