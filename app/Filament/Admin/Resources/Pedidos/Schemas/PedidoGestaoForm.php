<?php

namespace App\Filament\Admin\Resources\Pedidos\Schemas;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\SetorAccessCapability;
use App\Models\Pedido;
use App\Models\TipoStatus;
use App\Services\PedidoService;
use App\Services\SetorPedidoAccessService;
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
                            ->options(fn (?Pedido $record) => static::statusOptions($record))
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $status = $state ? TipoStatus::find($state) : null;

                                if (! static::statusEh($status, 'Encaminhado ao Setor')) {
                                    $set('setor_id', null);
                                }

                                if (! static::statusEh($status, 'Enviado para Empresa')) {
                                    $set('empresa_contratada_id', null);
                                }
                            })
                            ->helperText('As opcoes disponiveis dependem das permissoes e do acesso entre setores.')
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
                            ->options(fn () => app(SetorPedidoAccessService::class)->optionsForCapability(
                                Auth::user(),
                                SetorAccessCapability::ENCAMINHAR,
                            ))
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

    private static function statusOptions(?Pedido $record): array
    {
        $service = app(PedidoService::class);
        $user = Auth::user();
        $nomes = [];

        if ($record && $service->podeGerenciarRegistro($record, $user)) {
            $nomes[] = 'Em Manutenção';
        }

        if ($record && $service->podeCancelarRegistro($record, $user)) {
            $nomes[] = 'Cancelado';
        }

        if ($record && $service->podeEncaminharRegistro($record, $user)) {
            $nomes[] = 'Encaminhado ao Setor';
        }

        if ($record && $service->podeEnviarParaEmpresa($user) && $service->setorPodeEditarRegistro($record, $user)) {
            $nomes[] = 'Enviado para Empresa';
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
