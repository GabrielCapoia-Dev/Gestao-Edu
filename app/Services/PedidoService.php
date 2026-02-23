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
use App\Models\Enums\TipoArquivoPedido;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Filters\Tabs;
use Filament\Tables\Filters\Tabs\Tab;
use Filament\Support\Colors\Color;

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
            'tipo_manutencao_id' => $data['tipo_manutencao_id'],
            'descricao_pedido'   => $data['descricao_pedido'],
            'nivel_prioridade'   => NivelEmergenciaPedido::INDEFINIDO,
            'solicitante_id'     => $solicitante->id,
            'escola_id'          => $solicitante->id_escola,
            'tipo_status_id'     => $statusInicial->id,
            'data_solicitacao'   => now(),
            'ativo'              => true,
        ]);

        // Salvar arquivos
        if (!empty($data['arquivos'])) {
            foreach ($data['arquivos'] as $path) {

                $mime = Storage::mimeType("public/pedidos/{$path}");


                $pedido->arquivos()->create([
                    'usuario_id'    => $solicitante->id,
                    'tipo_arquivo'  => TipoArquivoPedido::FOTOS_PROBLEMA,
                    'caminho'       => $path,
                    'nome_original' => basename($path),
                    'mime_type'     => $mime,
                ]);
            }
        }


        $this->registrarHistorico(
            $pedido,
            null,
            $statusInicial->id,
            $solicitante,
            'Pedido criado'
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

            Forms\Components\Section::make('Novo Pedido')
                ->description('Informe o problema encontrado')
                ->schema([

                    Forms\Components\Select::make('tipo_manutencao_id')
                        ->label('Tipo de Manutenção')
                        ->options(
                            TipoManutencao::where('ativo', true)
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                        )
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Textarea::make('descricao_pedido')
                        ->label('Descrição do Problema')
                        ->required()
                        ->rows(6)
                        ->maxLength(2000)
                        ->helperText('Descreva o problema com o máximo de detalhes possível')
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('arquivos')
                        ->label('Fotos do Problema')
                        ->multiple()
                        ->image()
                        ->maxFiles(10)
                        ->maxSize(5120) // 5MB por arquivo
                        ->directory('pedidos')
                        ->disk('public')
                        ->storeFiles()
                        ->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->helperText('Até 10 imagens (JPEG, PNG ou WEBP) - máximo 5MB cada')
                        ->columnSpanFull(),

                ])
                ->columns(1),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FORMULÁRIO - GESTÃO
    |--------------------------------------------------------------------------
    */

    // TODO: Adicionar campo de Setor para referenciar qual setor alterou o status
    public function configurarFormularioGestao(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Informações do Pedido')
                ->schema([
                    Forms\Components\Placeholder::make('cabecalho')
                        ->label(false)
                        ->content(fn(Pedido $record) => view('components.pedido.pedido-cabecalho', ['record' => $record]))
                        ->columnSpanFull(),
                ])
                ->collapsible(),


            Forms\Components\Section::make('Gestão do Pedido')
                ->collapsible()
                ->schema([

                    Forms\Components\Select::make('empresa_contratada_id')
                        ->label('Empresa Responsável')
                        ->relationship(
                            name: 'empresaContratada',
                            titleAttribute: 'nome',
                            modifyQueryUsing: fn($query) => $query->where('ativo', true)
                        )
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('nivel_prioridade')
                        ->label('Nível de Prioridade')
                        ->options([
                            'Emergencial' => 'Emergencial',
                            'Preventivo'  => 'Preventivo',
                            'Corretivo'   => 'Corretivo',
                        ])
                        ->nullable()
                        ->native(false),

                    Forms\Components\Select::make('novo_status_id')
                        ->label('Atualizar Status')
                        ->options(function () {
                            return TipoStatus::query()
                                ->where('ativo', true)
                                ->whereNotIn('nome', ['Em Aberto', 'Em Análise'])
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                                ->toArray();
                        })
                        ->placeholder('Padrão: Em Análise')
                        ->searchable()
                        ->nullable(),

                    Forms\Components\DatePicker::make('data_prevista')
                        ->label('Data Prevista')
                        ->required(),

                    Forms\Components\Select::make('setor_id')
                        ->label('Setor')
                        ->relationship(
                            name: 'setor',
                            titleAttribute: 'nome',
                            modifyQueryUsing: fn($query) => $query->where('ativo', true)
                        )
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\DatePicker::make('data_entrega')
                        ->label('Data de Entrega')
                        ->nullable(),

                    Forms\Components\Textarea::make('descricao_alteracao')
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


    /* 
    |-------------------------------------------------------------------------- 
    | TABELA 
    |--------------------------------------------------------------------------
     */


    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->query($this->queryTabela($user))
            ->columns($this->colunasTabela($user))
            ->filters($this->filtrosTabela(), layout: FiltersLayout::AboveContent)
            ->actions($this->acoesTabela($user))
            ->bulkActions($this->acoesEmMassa($user))
            ->defaultSort('created_at', 'desc');
    }
    protected function queryTabela(?User $user): Builder
    {
        $query = Pedido::query()->where('ativo', true);
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->hasRole('Admin')) {
            return $this->ordenarPorStatus($query);
        }
        if ($user->id_escola) {
            return $this->ordenarPorStatus($query->where('escola_id', $user->id_escola));
        }
        return $query->whereRaw('1 = 0');
    }

    protected function ordenarPorStatus(Builder $query): Builder
    {
        $statusEmAbertoId = TipoStatus::where('nome', 'Em Aberto')->value('id');

        return $query
            ->orderByRaw("tipo_status_id = ? DESC", [$statusEmAbertoId])
            ->orderBy('created_at', 'asc');
    }

    protected function filtrosTabela(): array
    {
        return [
            Tables\Filters\SelectFilter::make('escola_id')
                ->label('Escola')
                ->relationship('escola', 'nome'),


            Tables\Filters\SelectFilter::make('tipo_manutencao_id')
                ->label('Tipo')
                ->relationship('tipoManutencao', 'nome'),

            Tables\Filters\SelectFilter::make('nivel_prioridade')
                ->label('Prioridade')
                ->options(['Emergencial' => 'Emergencial', 'Preventivo' => 'Preventivo', 'Corretivo' => 'Corretivo',]),

        ];
    }

    // TODO: Ajustar visualização da coluna para mostrar a coluna de Status, junto com Setor e Descrição do Status, de maneira empilhada
    // TODO: ADICIONAR COLUNA COM NOME DA ESCOLA
    protected function colunasTabela(?User $user): array
    {
        return [

            Tables\Columns\TextColumn::make('numero_protocolo')
                ->label('Protocolo')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            Tables\Columns\TextColumn::make('tipoManutencao.nome')
                ->label('Tipo')
                ->sortable(),

            Tables\Columns\TextColumn::make('tipoStatus.nome')
                ->label('Status')
                ->badge()
                ->color(fn(Pedido $record) => Color::hex($record->tipoStatus?->cor ?? '#6b7280')),

            Tables\Columns\TextColumn::make('nivel_prioridade')
                ->label('Prioridade')
                ->badge()
                ->color(fn(Pedido $record) => Color::hex(
                    match ($record->nivel_prioridade?->value) {
                        'Emergencial' => '#ef4444',
                        'Corretivo'   => '#f97316',
                        'Preventivo'  => '#3b82f6',
                        default       => '#2b2b2b',
                    }
                ))
                ->sortable(),

            Tables\Columns\TextColumn::make('descricao_pedido')
                ->label('Descrição')
                ->limit(40)
                ->wrap()
                ->toggleable(),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Criado em')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ];
    }


    protected function acoesTabela(?User $user): array
    {
        return  [
            Tables\Actions\EditAction::make()
                ->label('Gerenciar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning'),
        ];
    }


    protected function acoesEmMassa(?User $user): array
    {
        if (!$user?->hasPermissionTo('Editar Pedidos')) {
            return [];
        }

        return [
            Tables\Actions\DeleteBulkAction::make(),
        ];
    }
}
