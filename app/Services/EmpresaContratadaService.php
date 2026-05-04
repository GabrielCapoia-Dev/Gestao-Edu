<?php

namespace App\Services;

use App\Models\EmpresaContratada;
use App\Models\Setor;
use Filament\Forms\Form;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\IconColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Services\UserService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;


class EmpresaContratadaService
{
    private const PERMISSAO_LISTAR_EMPRESAS_INATIVAS = 'Listar Empresas Inativas';

    /*
    |--------------------------------------------------------------------------
    | Formulário
    |--------------------------------------------------------------------------
    */

    public function configurarFormulario(Schema $schema): Schema
    {
        return $schema->components($this->schemaFormulario());
    }

    protected function schemaFormulario(): array
    {
        return [

            Section::make('Dados da Empresa')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([

                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->required()
                            ->mask('99.999.999/9999-99')
                            ->regex('/^\d{2}\.\d{3}\.\d{3}\/\d{4}\-\d{2}$/')
                            ->maxLength(18)
                            ->validationMessages([
                                'regex' => 'CNPJ inválido. Use o formato 00.000.000/0000-00',
                            ]),

                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('responsavel')
                            ->label('Responsável')
                            ->maxLength(255),

                        TextInput::make('telefone')
                            ->tel()
                            ->maxLength(20),

                        Select::make('setor_id')
                            ->label('Setor')
                            ->options(fn () => Setor::query()
                                ->ativos()
                                ->when(
                                    filled(Auth::user()?->setor_id),
                                    fn (Builder $query): Builder => $query->whereKey(Auth::user()->setor_id)
                                )
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                                ->toArray())
                            ->default(fn () => Auth::user()?->setor_id)
                            ->searchable()
                            ->preload()
                            ->required(),

                    ]),
                ]),

            Section::make('Endereço')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('cep')->maxLength(10),
                        TextInput::make('logradouro')->columnSpan(2),

                        TextInput::make('numero'),
                        TextInput::make('complemento'),
                        TextInput::make('bairro'),

                        TextInput::make('cidade'),
                        TextInput::make('estado')->maxLength(2),
                    ]),
                ]),

            Toggle::make('ativo')
                ->label('Empresa Ativa')
                ->default(true),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Tabela
    |--------------------------------------------------------------------------
    */

    public function configurarTabela(Table $table): Table
    {
        $podeListarInativas = Auth::user()?->hasPermissionTo(self::PERMISSAO_LISTAR_EMPRESAS_INATIVAS) ?? false;

        return $table
            ->query(EmpresaContratada::query()
                ->doSetorDoUsuario(Auth::user())
                ->when(! $podeListarInativas, fn (Builder $query) => $query->where('ativo', true)))
            ->columns($this->colunasTabela())
            ->filters($this->filtrosTabela($podeListarInativas))
            ->recordActions($this->acoesTabela())
            ->toolbarActions($this->acoesEmMassa())
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [

            TextColumn::make('nome')
                ->label('Empresa')
                ->searchable()
                ->sortable(),

            TextColumn::make('cnpj')
                ->label('CNPJ')
                ->searchable(),

            TextColumn::make('responsavel')
                ->label('Responsável')
                ->searchable(),

            TextColumn::make('telefone')
                ->label('Telefone'),

            TextColumn::make('setor.nome')
                ->label('Setor')
                ->searchable()
                ->sortable(),

            IconColumn::make('ativo')
                ->label('Ativa')
                ->boolean(),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->since()
                ->sortable(),
        ];
    }

    private function filtrosTabela(bool $podeListarInativas): array
    {
        if (! $podeListarInativas) {
            return [];
        }

        return [
            TernaryFilter::make('listar_empresas_inativas')
                ->label(self::PERMISSAO_LISTAR_EMPRESAS_INATIVAS)
                ->trueLabel('Sim')
                ->falseLabel('Não')
                ->placeholder('Não')
                ->default(false)
                ->native(false)
                ->queries(
                    // Fluxo: por padrão a listagem mantém somente empresas ativas; ao marcar o filtro, o usuário autorizado também enxerga inativas para reativação.
                    true: fn (Builder $query): Builder => $query,
                    false: fn (Builder $query): Builder => $query->where('ativo', true),
                    blank: fn (Builder $query): Builder => $query->where('ativo', true),
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Ações
    |--------------------------------------------------------------------------
    */

    private function acoesTabela(): array
    {
        return [
            EditAction::make()
                ->using(function (EmpresaContratada $record, array $data, Action $action): EmpresaContratada {

                    $campos = [
                        'nome',
                        'cnpj',
                        'email',
                        'responsavel',
                        'telefone',
                        'setor_id',
                        'cep',
                        'logradouro',
                        'numero',
                        'complemento',
                        'bairro',
                        'cidade',
                        'estado'
                    ];

                    $alterou = false;

                    foreach ($campos as $campo) {
                        if ($record->{$campo} != ($data[$campo] ?? null)) {
                            $alterou = true;
                            break;
                        }
                    }

                    $inativaRegistroAtual = (bool) $record->ativo && ($alterou || ! (bool) ($data['ativo'] ?? false));

                    if ($inativaRegistroAtual) {
                        $motivoBloqueio = $this->motivoBloqueioInativacao($record);

                        if ($motivoBloqueio !== null) {
                            Notification::make()
                                ->title('Ação bloqueada')
                                ->body($motivoBloqueio)
                                ->danger()
                                ->send();

                            // Interrompe o ciclo da EditAction para a notificacao ser despachada na hora, sem ficar presa ate o proximo refresh.
                            $action->halt();
                        }
                    }

                    if ($alterou) {

                        $record->update(['ativo' => false]);

                        return EmpresaContratada::create([
                            ...$data,
                            'ativo' => true,
                            'registro_anterior_id' => $record->id,
                            'alterado_por' => Auth::user()?->name,
                        ]);
                    }

                    $record->update($data);

                    return $record;
                }),

            DeleteAction::make()
                ->successNotification(null)
                ->using(function (EmpresaContratada $record, Action $action) {

                    $motivoBloqueio = $this->motivoBloqueioExclusao($record);

                    if ($motivoBloqueio !== null) {
                        Notification::make()
                            ->title('Ação bloqueada')
                            ->body($motivoBloqueio)
                            ->danger()
                            ->send();

                        // Sem cancelar a action, o Filament conclui o fluxo e a notificacao so aparece no proximo carregamento.
                        $action->cancel();
                    }

                    $record->delete();
                }),
        ];
    }

    private function acoesEmMassa(): array
    {
        return [
            BulkAction::make('alterar_setor')
                ->label('Alterar setor')
                ->visible(fn (): bool => Auth::user()?->hasPermissionTo('Editar Empresa Contratada') ?? false)
                ->form([
                    Select::make('setor_id')
                        ->label('Novo setor')
                        ->options(fn () => Setor::query()
                            ->ativos()
                            ->orderBy('nome')
                            ->pluck('nome', 'id')
                            ->toArray())
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading('Alterar setor das empresas selecionadas')
                ->modalDescription('As empresas selecionadas passarão a aparecer nas listagens e seletores do novo setor.')
                ->action(function (array $data, $records): void {
                    foreach ($records as $record) {
                        $record->update([
                            'setor_id' => $data['setor_id'],
                            'alterado_por' => Auth::user()?->name,
                        ]);
                    }

                    Notification::make()
                        ->title('Setor atualizado')
                        ->body('As empresas selecionadas foram vinculadas ao novo setor.')
                        ->success()
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),

            DeleteBulkAction::make()
                ->successNotification(null)
                ->using(function ($records, Action $action) {

                    foreach ($records as $record) {
                        $motivoBloqueio = $this->motivoBloqueioExclusao($record, true);

                        if ($motivoBloqueio !== null) {
                            Notification::make()
                                ->title('Ação bloqueada')
                                ->body($motivoBloqueio)
                                ->danger()
                                ->send();

                            // Mantem a selecao intacta e mostra o bloqueio imediatamente para o usuario.
                            $action->cancel();
                        }
                    }

                    foreach ($records as $record) {
                        $record->delete();
                    }
                }),
        ];
    }

    private function motivoBloqueioExclusao(EmpresaContratada $empresa, bool $acaoEmMassa = false): ?string
    {
        // Contratos e pedidos usam a empresa como historico operacional; se a exclusao fosse permitida,
        // relatorios, acompanhamentos e rastreabilidade poderiam perder a referencia da contratada.
        $possuiContratos = $empresa->contratos()->exists();
        $possuiPedidos = $empresa->pedidos()->exists();

        if (! $possuiContratos && ! $possuiPedidos) {
            return null;
        }

        if ($acaoEmMassa) {
            return match (true) {
                $possuiContratos && $possuiPedidos => 'Uma ou mais empresas possuem contratos ou pedidos de manutenção vinculados.',
                $possuiContratos => 'Uma ou mais empresas possuem contratos vinculados.',
                default => 'Uma ou mais empresas possuem pedidos de manutenção vinculados.',
            };
        }

        return match (true) {
            $possuiContratos && $possuiPedidos => 'Esta empresa possui contratos e pedidos de manutenção vinculados e não pode ser excluída.',
            $possuiContratos => 'Esta empresa possui contratos vinculados e não pode ser excluída.',
            default => 'Esta empresa possui pedidos de manutenção vinculados e não pode ser excluída.',
        };
    }

    private function motivoBloqueioInativacao(EmpresaContratada $empresa): ?string
    {
        // Regra de negocio: empresa so pode sair de uso quando nao existe contrato vigente nem pedido em andamento.
        // Se isso for relaxado, seletores podem ocultar a empresa enquanto contratos/pedidos ainda dependem dela operacionalmente.
        $possuiContratoAtivoVigente = $empresa->contratos()
            ->where('ativo', true)
            ->whereDate('data_inicio', '<=', today())
            ->where(function (Builder $query): void {
                $query->whereNull('data_vencimento')
                    ->orWhereDate('data_vencimento', '>=', today());
            })
            ->exists();

        $possuiPedidoNaoFinalizado = $empresa->pedidos()
            ->where(function (Builder $query): void {
                $query->whereHas('tipoStatus', function (Builder $status): void {
                    // "Concluido" e "Cancelado" sao representados pelos marcadores finaliza_pedido/cancela_pedido no cadastro de status.
                    $status->where('finaliza_pedido', false)
                        ->where('cancela_pedido', false);
                })->orWhereDoesntHave('tipoStatus');
            })
            ->exists();

        if (! $possuiContratoAtivoVigente && ! $possuiPedidoNaoFinalizado) {
            return null;
        }

        return match (true) {
            $possuiContratoAtivoVigente && $possuiPedidoNaoFinalizado => 'Esta empresa possui contratos ativos vigentes e pedidos de manutenção em andamento, por isso não pode ser inativada.',
            $possuiContratoAtivoVigente => 'Esta empresa possui contratos ativos vigentes e não pode ser inativada.',
            default => 'Esta empresa possui pedidos de manutenção em andamento e não pode ser inativada.',
        };
    }
}
