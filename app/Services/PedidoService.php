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
use Filament\Tables\Columns\Layout\Stack;
use Illuminate\Support\Carbon;


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
                        ->options(
                            collect(NivelEmergenciaPedido::cases())
                                ->mapWithKeys(fn($case) => [
                                    $case->value => $case->label()
                                ])
                                ->toArray()
                        )
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

    protected function colunasTabela(?User $user): array
    {
        return [
            Tables\Columns\Layout\Split::make([

                // Bloco 1: Protocolo + Tipo de Manutenção
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('numero_protocolo')
                        ->label('Protocolo')
                        ->tooltip('Número do protocolo')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->searchable()
                        ->sortable()
                        ->weight('bold'),

                    Tables\Columns\TextColumn::make('tipoManutencao.nome')
                        ->label('Tipo')
                        ->tooltip('Tipo de manutenção')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->sortable(),

                    Tables\Columns\TextColumn::make('tipoManutencao.descricao')
                        ->label('')
                        ->color('gray')
                        ->size('sm'),
                ])->space(1),

                // Bloco 2: Escola + Solicitante + Responsável
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('escola.nome')
                        ->label('Escola')
                        ->tooltip('Escola que fez a solicitação')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->icon('heroicon-o-building-office-2')
                        ->alignCenter()
                        ->sortable(),

                    Tables\Columns\TextColumn::make('solicitante.name')
                        ->label('Solicitante')

                        ->tooltip('Quem fez a solicitação')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->icon('heroicon-o-user')
                        ->alignCenter()
                        ->color('gray')
                        ->size('sm'),

                    Tables\Columns\TextColumn::make('responsavel.name')
                        ->label('Responsável')
                        ->alignCenter()
                        ->tooltip('Responsável atual')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->icon('heroicon-o-user-circle')
                        ->color('gray')
                        ->size('sm')
                        ->placeholder('Sem responsável'),
                ])->space(1),

                // Bloco 3: Status + Prioridade + Setor + Empresa
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('tipoStatus.nome')
                        ->alignCenter()
                        ->label('Status')
                        ->badge()
                        ->color(fn(Pedido $record) => Color::hex($record->tipoStatus?->cor ?? '#6b7280')),

                    Tables\Columns\TextColumn::make('nivel_prioridade')
                        ->label('Prioridade')
                        ->alignCenter()
                        ->badge()
                        ->color(fn(Pedido $record) => Color::hex(
                            match ($record->nivel_prioridade?->value) {
                                'emergencial' => '#a10000',
                                'corretivo'   => '#973f00',
                                'preventivo'  => '#013891',
                                default       => '#2b2b2b',
                            }
                        ))
                        ->sortable(),

                    Tables\Columns\TextColumn::make('setor.nome')
                        ->label('Setor')
                        ->tooltip('Setor Responsável')
                        ->alignCenter()
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->icon('heroicon-o-building-storefront')
                        ->color('gray')
                        ->size('sm')
                        ->placeholder('Sem setor'),

                    Tables\Columns\TextColumn::make('empresaContratada.nome')
                        ->alignCenter()
                        ->label('Empresa')
                        ->tooltip('Empresa contratada')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->icon('heroicon-o-briefcase')
                        ->color('gray')
                        ->size('sm')
                        ->placeholder('Sem empresa'),
                ])->space(1),

                // Bloco 4: Datas
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('data_solicitacao')
                        ->label('Solicitado em')
                        ->icon('heroicon-o-calendar')
                        ->tooltip('Data de solicitação')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->date('d/m/Y')
                        ->alignCenter()
                        ->sortable(),

                    Tables\Columns\TextColumn::make('data_prevista')
                        ->label('Previsto para')
                        ->tooltip('Data prevista para entrega')
                        ->icon('heroicon-o-clock')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->alignCenter()
                        ->date('d/m/Y')
                        ->color(function (Pedido $record) {
                            if (! $record->data_prevista) {
                                return null;
                            }

                            return Carbon::parse($record->data_prevista)->isPast()
                                ? Color::hex('#a10000') // vermelho
                                : Color::hex('#facc15'); // amarelo
                        })
                        ->size('sm')
                        ->placeholder('Sem previsão'),

                    Tables\Columns\TextColumn::make('data_entrega')
                        ->label('Entregue em')
                        ->icon('heroicon-o-check-circle')
                        ->date('d/m/Y')
                        ->alignCenter()
                        ->color('success')
                        ->size('sm')
                        ->placeholder('Não entregue'),
                ])->space(1),

                // Bloco 5: Descrição
                Tables\Columns\TextColumn::make('descricao_pedido')
                    ->label('Descrição')
                    ->limit(60)
                    ->alignCenter()
                    ->wrap()
                    ->color('gray')
                    ->size('sm')
                    ->toggleable(),

            ]),
        ];
    }


    protected function acoesTabela(?User $user): array
    {
        return [
            Tables\Actions\Action::make('gerenciar')
                ->label('Gerenciar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->action(function (Pedido $record) use ($user) {
                    if (!$user) return;

                    $statusEmAberto = TipoStatus::where('nome', 'Em Aberto')->first();
                    $statusAnalise  = TipoStatus::where('nome', 'Em Análise')->first();

                    if (
                        $statusAnalise &&
                        $statusEmAberto &&
                        $record->tipo_status_id === $statusEmAberto->id
                    ) {
                        $this->alterarStatus(
                            $record,
                            $statusAnalise,
                            $user,
                            'Pedido assumido para análise por ' . $user->name . '.'
                        );
                    }

                    redirect(route('filament.admin.resources.pedidos.edit', $record));
                })
                ->openUrlInNewTab(false),
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
