<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PedidoHistorico;
use App\Models\TipoStatus;
use App\Models\TipoManutencao;
use App\Models\User;
use App\Models\Enums\NivelEmergenciaPedido;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PedidoService
{
    /*
    |--------------------------------------------------------------------------
    | PERMISSÕES
    |--------------------------------------------------------------------------
    */

    public function ehAdmin(?User $user): bool
    {
        return $user?->hasRole('Admin') ?? false;
    }

    public function podeGerenciarPedidos(?User $user): bool
    {
        return $user?->hasPermissionTo('Editar Pedidos') ?? false;
    }

    public function podeCriarPedidos(?User $user): bool
    {
        return $user?->hasPermissionTo('Criar Pedidos') ?? false;
    }

    /*
    |--------------------------------------------------------------------------
    | BADGE
    |--------------------------------------------------------------------------
    */

    public function contarPedidosNovos(?User $user): ?string
    {
        if (!$this->podeGerenciarPedidos($user)) {
            return null;
        }

        $statusInicial = TipoStatus::where('ativo', true)
            ->where('finaliza_pedido', false)
            ->where('cancela_pedido', false)
            ->first();

        if (!$statusInicial) {
            return null;
        }

        $query = Pedido::where('ativo', true)
            ->where('tipo_status_id', $statusInicial->id);

        if (!$this->ehAdmin($user) && $user?->id_escola) {
            $query->where('escola_id', $user->id_escola);
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY BASE POR PERFIL
    |--------------------------------------------------------------------------
    */

    public function queryPorPerfil(Builder $query, ?User $user): Builder
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('ativo', true);

        if ($this->ehAdmin($user)) {
            return $query;
        }

        if ($user->id_escola) {
            $query->where('escola_id', $user->id_escola);
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | CRIAÇÃO
    |--------------------------------------------------------------------------
    */

    public function criarPedido(array $data, User $solicitante): Pedido
    {
        $statusInicial = TipoStatus::where('ativo', true)
            ->where('finaliza_pedido', false)
            ->where('cancela_pedido', false)
            ->firstOrFail();

        $pedido = Pedido::create([
            ...$data,
            'solicitante_id' => $solicitante->id,
            'escola_id' => $solicitante->id_escola,
            'tipo_status_id' => $statusInicial->id,
            'data_solicitacao' => now(),
        ]);

        $this->registrarHistorico(
            pedido: $pedido,
            statusAnteriorId: null,
            statusNovoId: $statusInicial->id,
            usuario: $solicitante,
            descricao: 'Pedido criado'
        );

        return $pedido;
    }

    /*
    |--------------------------------------------------------------------------
    | ALTERAÇÃO DE STATUS
    |--------------------------------------------------------------------------
    */

    public function alterarStatus(
        Pedido $pedido,
        TipoStatus $novoStatus,
        User $usuario,
        ?string $descricao = null
    ): void {

        $statusAnteriorId = $pedido->tipo_status_id;

        $pedido->update([
            'tipo_status_id' => $novoStatus->id,
            'responsavel_id' => $usuario->id,
        ]);

        if ($novoStatus->finaliza_pedido) {
            $pedido->update([
                'data_entrega' => now(),
            ]);
        }

        $this->registrarHistorico(
            pedido: $pedido,
            statusAnteriorId: $statusAnteriorId,
            statusNovoId: $novoStatus->id,
            usuario: $usuario,
            descricao: $descricao
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HISTÓRICO
    |--------------------------------------------------------------------------
    */

    public function registrarHistorico(
        Pedido $pedido,
        ?int $statusAnteriorId,
        int $statusNovoId,
        User $usuario,
        ?string $descricao = null
    ): PedidoHistorico {

        return PedidoHistorico::create([
            'pedido_id' => $pedido->id,
            'status_anterior_id' => $statusAnteriorId,
            'status_novo_id' => $statusNovoId,
            'usuario_id' => $usuario->id,
            'setor_id' => $pedido->setor_id,
            'descricao_alteracao' => $descricao,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FORMULÁRIO - CRIAÇÃO
    |--------------------------------------------------------------------------
    */

    public function configurarFormularioCriacao(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Dados do Pedido')
                ->schema([

                    Forms\Components\Select::make('tipo_manutencao_id')
                        ->label('Tipo de Manutenção')
                        ->options(
                            TipoManutencao::where('ativo', true)
                                ->pluck('nome', 'id')
                        )
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('nivel_prioridade')
                        ->label('Prioridade')
                        ->options(
                            collect(NivelEmergenciaPedido::cases())
                                ->mapWithKeys(fn($case) => [$case->value => $case->value])
                        )
                        ->required(),

                    Forms\Components\Textarea::make('descricao_pedido')
                        ->label('Descrição')
                        ->required()
                        ->rows(5)
                        ->columnSpanFull(),

                ])
                ->columns(2),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FORMULÁRIO - GESTÃO
    |--------------------------------------------------------------------------
    */

    public function configurarFormularioGestao(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Informações')
                ->schema([

                    Forms\Components\TextInput::make('numero_protocolo')
                        ->disabled(),

                    Forms\Components\Textarea::make('descricao_pedido')
                        ->disabled()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('tipo_status_id')
                        ->label('Status')
                        ->options(
                            TipoStatus::where('ativo', true)
                                ->pluck('nome', 'id')
                        )
                        ->required(),

                    Forms\Components\Select::make('responsavel_id')
                        ->relationship('responsavel', 'name')
                        ->searchable(),

                    Forms\Components\DatePicker::make('data_prevista'),

                    Forms\Components\Textarea::make('descricao_alteracao')
                        ->label('Observação da Alteração')
                        ->rows(3)
                        ->dehydrated(false)
                        ->columnSpanFull(),

                ])
                ->columns(2),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TABELA
    |--------------------------------------------------------------------------
    */

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('numero_protocolo')
                    ->label('Protocolo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tipoManutencao.nome')
                    ->label('Tipo')
                    ->badge(),

                Tables\Columns\TextColumn::make('tipoStatus.nome')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('nivel_prioridade')
                    ->label('Prioridade')
                    ->badge(),

                Tables\Columns\TextColumn::make('descricao_pedido')
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }
}
