<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PedidoHistorico;
use App\Models\TipoStatus;
use App\Models\Setor;
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
use App\Filament\Components\Forms\SliderRating;

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

        // =========================
        // DEFINIÇÃO DO STATUS BASE
        // =========================

        if ($user?->setor?->nome === 'Obras') {

            $statusBase = TipoStatus::where('ativo', true)
                ->where('nome', 'Encaminhado ao Setor')
                ->first();
        } else {

            $statusBase = TipoStatus::where('ativo', true)
                ->where('finaliza_pedido', false)
                ->where('cancela_pedido', false)
                ->first();
        }

        if (!$statusBase) {
            return null;
        }

        // =========================
        // QUERY
        // =========================

        $query = Pedido::where('ativo', true)
            ->where('tipo_status_id', $statusBase->id);

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
        $statusInicial = TipoStatus::where('nome', 'Em Aberto')->firstOrFail();

        // 🔵 Buscar setor Educação
        $setorEducacao = Setor::where('nome', 'Educação')
            ->where('ativo', true)
            ->firstOrFail();

        $pedido = Pedido::create([
            'tipo_manutencao_id' => $data['tipo_manutencao_id'],
            'descricao_pedido'   => $data['descricao_pedido'],
            'nome_solicitante'   => $data['nome_solicitante'],
            'nivel_prioridade'   => NivelEmergenciaPedido::INDEFINIDO,
            'solicitante_id'     => $solicitante->id,
            'escola_id'          => $solicitante->id_escola,
            'tipo_status_id'     => $statusInicial->id,
            'setor_id'           => $setorEducacao->id, // ✅ aqui
            'data_solicitacao'   => now(),
            'ativo'              => true,
        ]);

        // Salvar arquivos
        if (filled($data['arquivos'])) {
            foreach (array_filter($data['arquivos']) as $path) {

                $this->salvarArquivoPedido(
                    pedido: $pedido,
                    path: $path,
                    tipo: TipoArquivoPedido::FOTOS_PROBLEMA,
                    usuarioId: $solicitante->id
                );
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

        // Histórico da primeira alteração
        $this->registrarHistorico(
            pedido: $pedido,
            statusAnteriorId: $statusAnteriorId,
            statusNovoId: $novoStatus->id,
            usuario: $usuario,
            descricao: $descricao
        );

        /*
    |--------------------------------------------------------------------------
    | REGRA ESPECIAL: Encaminhado ao Setor
    |--------------------------------------------------------------------------
    */


        /*
    |--------------------------------------------------------------------------
    | Finalização normal
    |--------------------------------------------------------------------------
    */

        if ($novoStatus->finaliza_pedido) {
            $pedido->update([
                'data_entrega' => now(),
            ]);
        }
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

                    Forms\Components\TextInput::make('nome_solicitante')
                        ->label('Nome de quem será o responsável pela solicitação')
                        ->required()
                        ->maxLength(150)
                        ->helperText('Essa pessoa será o responsável pela solicitação em caso de duvidas')
                        ->placeholder('Informe o nome do responsável pela solicitação')
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('descricao_pedido')
                        ->label('Descrição do Problema')
                        ->required()
                        ->rows(6)
                        ->maxLength(2000)
                        ->placeholder('Descreva o problema de forma detalhada, informando quando o problema começou, onde ocorreu, e caso saiba, informe qual foi o motivo')
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('arquivos')
                        ->label('Fotos do Problema')
                        ->multiple()
                        ->image()
                        ->maxFiles(10)
                        ->maxSize(5120)
                        ->directory('pedidos')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->helperText('Até 10 imagens (JPEG, PNG ou WEBP) - máximo 5MB cada')
                        ->columnSpanFull(),

                ])
                ->columns(1),
        ]);
    }

    private function salvarArquivoPedido(
        Pedido $pedido,
        string $path,
        TipoArquivoPedido $tipo,
        int $usuarioId,
        ?string $descricao = null
    ): void {

        $mime = Storage::mimeType("public/{$path}");

        $pedido->arquivos()->create([
            'usuario_id'    => $usuarioId,
            'tipo_arquivo'  => $tipo,
            'caminho'       => $path,
            'nome_original' => basename($path),
            'mime_type'     => $mime,
            'descricao'     => $descricao,
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

                    Forms\Components\Select::make('novo_status_id')
                        ->label('Atualizar Status')
                        ->reactive()
                        ->options(
                            fn() =>
                            TipoStatus::query()
                                ->where('ativo', true)
                                ->whereNotIn('nome', ['Em Aberto', 'Em Análise', "Concluído"])
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

                            $setorId = $get('setor_id');

                            $setor = $setorId
                                ? \App\Models\Setor::find($setorId)
                                : null;

                            if (
                                $status->nome === 'Encaminhado ao Setor' &&
                                $setor?->nome !== 'Obras'
                            ) {

                                $set('novo_status_id', null);

                                Notification::make()
                                    ->title('Status inválido')
                                    ->body('Para encaminhar ao setor, o setor selecionado deve ser Obras.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->helperText('Encaminhado ao Setor só pode ser usado quando o setor for Obras.')
                        ->placeholder('Padrão: Em Análise')
                        ->searchable()
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

                    Forms\Components\Select::make('setor_id')
                        ->label('Setor')
                        ->relationship(
                            name: 'setor',
                            titleAttribute: 'nome',
                            modifyQueryUsing: fn($query) => $query->where('ativo', true)
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

                            $setor = $state
                                ? \App\Models\Setor::find($state)
                                : null;

                            if (
                                $status->nome === 'Encaminhado ao Setor' &&
                                $setor?->nome !== 'Obras'
                            ) {

                                $set('novo_status_id', null);

                                Notification::make()
                                    ->title('Status removido')
                                    ->body('Encaminhado ao Setor exige que o setor seja Obras.')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\DatePicker::make('data_prevista')
                        ->label('Data Prevista')
                        ->required(),



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
            ->paginated([5, 10, 25, 50, 100])
            ->columns($this->colunasTabela($user))
            ->filters($this->filtrosTabela(), layout: FiltersLayout::AboveContent)
            ->actions($this->acoesTabela($user))
            ->bulkActions($this->acoesEmMassa($user))
            ->headerActions($this->acoesCabecalhoTabela($user));
    }

    public function acoesCabecalhoTabela(?User $user): array
    {
        return [
            Tables\Actions\Action::make('relatorio_geral')
                ->label('Exportar Relatório')
                ->icon('heroicon-o-document-chart-bar')
                ->color(fn() => Color::hex('#00aeff'))

                ->form([
                    Forms\Components\Grid::make()
                        ->columns(2)
                        ->schema([

                            Forms\Components\DatePicker::make('data_inicio')
                                ->label('Data inicial')
                                ->displayFormat('d/m/Y')
                                ->native(false)
                                ->maxDate(fn(Forms\Get $get) => $get('data_fim') ?: now()),

                            Forms\Components\DatePicker::make('data_fim')
                                ->label('Data final')
                                ->displayFormat('d/m/Y')
                                ->native(false)
                                ->minDate(fn(Forms\Get $get) => $get('data_inicio'))
                                ->maxDate(now()),

                            Forms\Components\Select::make('escola_id')
                                ->label('Escola')
                                ->options(
                                    \App\Models\Escola::query()
                                        ->where('ativo', true)
                                        ->orderBy('nome')
                                        ->pluck('nome', 'id')
                                )
                                ->searchable()
                                ->placeholder('Todas as escolas'),

                            Forms\Components\Select::make('tipo_manutencao_id')
                                ->label('Tipo de Manutenção')
                                ->options(
                                    TipoManutencao::query()
                                        ->where('ativo', true)
                                        ->orderBy('nome')
                                        ->pluck('nome', 'id')
                                )
                                ->searchable()
                                ->placeholder('Todos os tipos'),

                            Forms\Components\Select::make('tipo_status_id')
                                ->label('Status')
                                ->options(
                                    TipoStatus::query()
                                        ->where('ativo', true)
                                        ->orderBy('nome')
                                        ->pluck('nome', 'id')
                                )
                                ->searchable()
                                ->placeholder('Todos os status'),

                            Forms\Components\Select::make('nivel_prioridade')
                                ->label('Prioridade')
                                ->options(
                                    collect(NivelEmergenciaPedido::cases())
                                        ->mapWithKeys(fn($case) => [$case->value => $case->label()])
                                        ->toArray()
                                )
                                ->placeholder('Todas as prioridades'),
                        ]),
                ])

                ->modalHeading('Exportar Relatório de Pedidos')
                ->modalDescription('Configure os filtros e clique em Exportar para gerar o PDF.')
                ->modalSubmitActionLabel('Exportar PDF')
                ->modalIcon('heroicon-o-document-chart-bar')
                ->modalWidth('2xl')

                ->action(function (array $data) {

                    $filtros = array_filter($data, fn($v) => $v !== null && $v !== '');

                    foreach (['data_inicio', 'data_fim'] as $campo) {
                        if (!empty($filtros[$campo])) {
                            $filtros[$campo] = \Carbon\Carbon::parse($filtros[$campo])->format('Y-m-d');
                        }
                    }

                    $url = route('pedidos.relatorio-geral', $filtros);

                    return redirect()->away($url);
                }),
        ];
    }



    public function queryTabela(?User $user): Builder
    {
        $query = Pedido::query()
            ->with('ultimoHistorico')
            ->where('ativo', true);

        if (! $user) {
            return $query->nenhum();
        }

        if (
            $user->hasRole('Admin') ||
            $user->hasPermissionTo('Listar Todos os Pedidos')
        ) {
            return $query; // ❌ NÃO ordenar aqui
        }

        if ($user->id_escola) {
            return $query->where('escola_id', $user->id_escola);
        }

        return $query->nenhum();
    }

    public function queryTabTodos(?User $user): Builder
    {
        $query = Pedido::query()
            ->with('ultimoHistorico')
            ->where('ativo', true);

        if (! $user) {
            return $query->nenhum();
        }

        if (
            $user->hasRole('Admin') ||
            $user->hasPermissionTo('Listar Todos os Pedidos')
        ) {
            $query->orderByDesc('updated_at');
            return $query;
        }

        if ($user->id_escola) {
            $query->where('escola_id', $user->id_escola)
                ->orderByDesc('updated_at');

            return $query;
        }

        return $query->nenhum();
    }

    public function filtrosTabela(): array
    {
        return [

            'tipo_status_id' => Tables\Filters\SelectFilter::make('tipo_status_id')
                ->label('Status')
                ->relationship(
                    name: 'tipoStatus',
                    titleAttribute: 'nome',
                    modifyQueryUsing: fn($query) =>
                    $query->where('ativo', true)->orderBy('nome')
                )
                ->searchable()
                ->preload(),

            'escola_id' => Tables\Filters\SelectFilter::make('escola_id')
                ->label('Escola')
                ->relationship('escola', 'nome'),

            'tipo_manutencao_id' => Tables\Filters\SelectFilter::make('tipo_manutencao_id')
                ->label('Tipo')
                ->relationship('tipoManutencao', 'nome'),

            'nivel_prioridade' => Tables\Filters\SelectFilter::make('nivel_prioridade')
                ->label('Prioridade')
                ->options(
                    collect(NivelEmergenciaPedido::cases())
                        ->mapWithKeys(fn($case) => [
                            $case->value => $case->label(),
                        ])
                        ->toArray()
                ),
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

                    Tables\Columns\TextColumn::make('nome_solicitante')
                        ->label('Solicitante')

                        ->tooltip('Quem fez a solicitação')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->icon('heroicon-o-user')
                        ->alignCenter()
                        ->color('gray')
                        ->size('sm'),


                ])->space(1),

                // Bloco 3: Status + Prioridade + Setor + Empresa
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('tipoStatus.nome')
                        ->alignCenter()
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(function (Pedido $record) {

                            $status = $record->tipoStatus?->nome ?? 'Sem status';
                            $setor  = $record->setor?->nome;

                            return $setor
                                ? "{$status} - {$setor}"
                                : $status;
                        })
                        ->color(
                            fn(Pedido $record) =>
                            Color::hex($record->tipoStatus?->cor ?? '#6b7280')
                        ),

                    Tables\Columns\TextColumn::make('nivel_prioridade')
                        ->label('Prioridade')
                        ->alignCenter()
                        ->badge()
                        ->color(fn(Pedido $record) => Color::hex(
                            match ($record->nivel_prioridade?->value) {
                                'Emergencial' => '#a10000',
                                'Corretivo'   => '#973f00',
                                'Preventivo'  => '#013891',
                                default       => '#2b2b2b',
                            }
                        )),

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
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->icon('heroicon-o-clock')
                        ->sortable()
                        ->alignCenter()
                        ->date('d/m/Y')
                        ->color(function (Pedido $record) {

                            if (! $record->data_prevista) {
                                return null;
                            }

                            $prevista = Carbon::parse($record->data_prevista);

                            // 🔵 Se já foi Concoluido, regra muda
                            if ($record->data_entrega) {

                                $entrega = Carbon::parse($record->data_entrega);

                                // Entregou atrasado
                                if ($entrega->greaterThan($prevista)) {
                                    return Color::hex('#a10000'); // vermelho
                                }

                                // Entregou no prazo ou antes
                                return Color::hex('#10b981'); // verde
                            }

                            // 🔵 Ainda não Concoluido → regra normal

                            if ($prevista->isPast()) {
                                return Color::hex('#a10000'); // vermelho
                            }

                            $diasRestantes = now()->diffInDays($prevista, false);

                            if ($diasRestantes <= 15) {
                                return Color::hex('#ff6600'); // amarelo
                            }

                            return null;
                        })
                        ->size('sm')
                        ->placeholder('Sem previsão'),

                    Tables\Columns\TextColumn::make('data_entrega')
                        ->label('Concoluido em')
                        ->tooltip('Data de conclusão')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->icon('heroicon-o-check-circle')
                        ->date('d/m/Y')
                        ->alignCenter()
                        ->sortable()
                        ->color(function (Pedido $record) {

                            if (! $record->data_entrega) {
                                return null;
                            }

                            return "success";
                        })
                        ->size('sm')
                        ->placeholder('Não Concoluido'),
                ])->space(1),

                Tables\Columns\Layout\Stack::make([

                    Tables\Columns\TextColumn::make('descricao_pedido')
                        ->label('Descrição')
                        ->tooltip(
                            fn(Pedido $record) =>
                            "Descrição do pedido: {$record->descricao_pedido}"
                        )
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->limit(60)
                        ->wrap()
                        ->color('gray')
                        ->size('sm'),

                    Tables\Columns\TextColumn::make('ultimoHistorico.descricao_alteracao')
                        ->label('Última Alteração')
                        ->tooltip('Descrição da última alteração realizada')
                        ->extraAttributes([
                            'class' => 'tooltip-hover-effect cursor-help'
                        ])
                        ->limit(60)
                        ->wrap()
                        ->color('primary')
                        ->size('sm')
                        ->placeholder('Sem alterações'),

                ])->space(1),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description('Atualizado em:', position: 'above')
                    ->alignEnd()
                    ->tooltip('Data e hora da última atualização do pedido')
                    ->extraAttributes([
                        'class' => 'tooltip-hover-effect cursor-help'
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),
            ]),

        ];
    }

    protected function acoesTabela(?User $user): array
    {
        return [

            Tables\Actions\Action::make('historico')
                ->label('Histórico')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->slideOver()
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->visible(fn(Pedido $record) => User::authUser()->hasPermissionTo('Visualizar Histórico de Pedidos'))
                ->modalContent(function (Pedido $record) {

                    $historico = $record->historicos()
                        ->with(['statusAnterior', 'statusNovo', 'usuario', 'setor'])
                        ->orderByDesc('created_at')
                        ->get();

                    return view('components.pedido.historico', [
                        'pedido' => $record,
                        'historico' => $historico,
                    ]);
                }),

            Tables\Actions\Action::make('finalizar')
                ->label('Avaliar Pedido')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(function (Pedido $record) {

                    $emManutencao = $record->tipoStatus?->nome === 'Em Manutenção';
                    $podeAvaliar  = User::authUser()->hasPermissionTo('Avaliar Pedidos');

                    return $emManutencao && $podeAvaliar;
                })
                ->modalHeading('Avaliar Pedido')
                ->modalDescription('Ao avaliar o pedido, ele será concluído. Caso a nota seja 1, o pedido será reaberto automaticamente.')
                ->modalSubmitActionLabel('Confirmar Avaliação')
                ->modalCancelActionLabel('Cancelar')
                ->modalWidth('5xl')
                ->form([

                    Forms\Components\Section::make('Avaliação do Serviço')
                        ->schema([

                            SliderRating::make('valor')
                                ->label('Nota (1 a 5)')
                                ->helperText('Avalie o serviço realizado')
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('descricao')
                                ->label('Descrição da Avaliação')
                                ->maxLength(1000)
                                ->columnSpanFull(),

                        ])
                        ->columns(2),

                    Forms\Components\Section::make('Fotos da Conclusão')
                        ->schema([

                            Forms\Components\FileUpload::make('fotos_conclusao')
                                ->label('Adicionar Fotos')
                                ->multiple()
                                ->image()
                                ->maxFiles(10)
                                ->storeFileNamesIn('nome_original')
                                ->maxSize(5120)
                                ->directory('pedidos/conclusao')
                                ->disk('public')
                                ->visibility('public')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->columnSpanFull(),

                        ]),
                ])
                ->action(function (Pedido $record, array $data) use ($user) {

                    $nota = (int) $data['valor'];

                    $statusConcluido = TipoStatus::where('ativo', true)
                        ->where('nome', 'Concluído')
                        ->firstOrFail();

                    $statusReaberto = TipoStatus::where('ativo', true)
                        ->where('nome', 'Reaberto')
                        ->firstOrFail();

                    // 🔹 1️⃣ Criar novo feedback (1:N)
                    $record->feedbacks()->create([
                        'valor'     => $nota,
                        'descricao' => $data['descricao'] ?? null,
                    ]);

                    // 🔹 2️⃣ Salvar fotos
                    if (!empty($data['fotos_conclusao'])) {

                        foreach ($data['fotos_conclusao'] as $path) {

                            $this->salvarArquivoPedido(
                                pedido: $record,
                                path: $path,
                                tipo: TipoArquivoPedido::FOTOS_CONCLUSAO,
                                usuarioId: $user->id,
                                descricao: 'Fotos da conclusão do serviço'
                            );
                        }
                    }

                    // 🔹 3️⃣ Definir novo status
                    $novoStatus = $nota === 1
                        ? $statusReaberto
                        : $statusConcluido;

                    $this->alterarStatus(
                        $record,
                        $novoStatus,
                        $user,
                        $nota === 1
                            ? 'Pedido reaberto automaticamente após avaliação com nota 1.'
                            : 'Pedido concluído e avaliado.'
                    );

                    Notification::make()
                        ->title(
                            $nota === 1
                                ? 'Pedido reaberto para nova execução.'
                                : 'Pedido concluído com sucesso.'
                        )
                        ->success()
                        ->send();
                }),


            Tables\Actions\Action::make('gerenciar')
                ->label('Gerenciar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(function (Pedido $record) use ($user) {

                    if (! $user) {
                        return false;
                    }

                    // Precisa ter permissão
                    if (! $user->hasPermissionTo('Editar Pedidos')) {
                        return false;
                    }

                    // Não permitir se finalizado ou cancelado
                    if (
                        $record->tipoStatus?->finaliza_pedido ||
                        $record->tipoStatus?->cancela_pedido
                    ) {
                        return false;
                    }

                    // Se usuário NÃO tem setor → pode gerenciar tudo
                    if (! $user->setor_id) {
                        return true;
                    }

                    // Se tem setor → só gerencia pedidos do mesmo setor
                    return $record->setor_id === $user->setor_id;
                })
                ->action(function (Pedido $record) use ($user) {

                    $statusEmAberto      = TipoStatus::where('nome', 'Em Aberto')->first();
                    $statusEncaminhado   = TipoStatus::where('nome', 'Encaminhado ao Setor')->first();
                    $statusAnalise       = TipoStatus::where('nome', 'Em Análise')->first();

                    if (! $statusAnalise) {
                        redirect(route('filament.admin.resources.pedidos.edit', $record));
                        return;
                    }

                    $podeIrParaAnalise = false;

                    // 🔹 Caso 1: Em Aberto → qualquer setor pode assumir
                    if (
                        $statusEmAberto &&
                        $record->tipo_status_id === $statusEmAberto->id
                    ) {
                        $podeIrParaAnalise = true;
                    }

                    // 🔹 Caso 2: Encaminhado → somente Obras pode assumir
                    if (
                        $statusEncaminhado &&
                        $record->tipo_status_id === $statusEncaminhado->id &&
                        $user?->setor?->nome === 'Obras'
                    ) {
                        $podeIrParaAnalise = true;
                    }

                    if ($podeIrParaAnalise) {
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

        return [];
    }
}
