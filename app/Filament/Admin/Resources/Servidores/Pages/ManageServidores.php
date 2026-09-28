<?php

namespace App\Filament\Admin\Resources\Servidores\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Livewire\Pessoas\ServidoresTable;
use App\Models\Servidor;
use App\Models\User;
use App\Services\PessoaUsuarioService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

class ManageServidores extends ManageRecords
{
    protected static string $resource = ServidorResource::class;

    protected string $view = 'filament.admin.resources.servidores.pages.manage-pessoas';

    #[On('pessoa-form-salvo')]
    public function finalizarFormularioPessoa(): void
    {
        $this->flushCachedTableRecords();
        $this->unmountAction(false);
    }

    #[On('pessoa-form-cancelado')]
    public function cancelarFormularioPessoa(): void
    {
        $this->unmountAction(false);
    }

    #[On('servidor-acao')]
    public function abrirAcaoServidor(string $action, int $id): void
    {
        abort_unless(in_array($action, [
            'view', 'edit', 'criar_acesso', 'gerenciar_acesso', 'redefinir_senha',
            'excluir_acesso', 'analisar_solicitacoes_professor', 'alterar_status', 'delete', 'restore',
        ], true), 404);

        $record = ServidorResource::getEloquentQuery()->findOrFail($id);

        // Filament resolves mounted record actions by the table record key, not
        // by the Eloquent model instance.
        $this->mountTableAction($action, (string) $record->getKey());
    }

    #[On('servidores-acao-massa')]
    public function abrirAcaoEmMassa(string $action, array $ids): void
    {
        abort_unless(in_array($action, [
            'alterar_status_em_massa', 'criar_acessos_em_massa', 'verificacao_acesso_em_massa',
            'redefinir_senha_em_massa', 'niveis_em_massa', 'permissoes_em_massa',
            'excluir_acessos_em_massa', 'delete', 'restore',
        ], true), 404);

        abort_unless(array_key_exists($action, ServidoresTable::acoesEmMassaPermitidas()), 403);

        $recebidos = collect($ids)->unique()->values();
        $ids = $recebidos
            ->map(fn (mixed $id): int|false => filter_var($id, FILTER_VALIDATE_INT))
            ->filter(fn (int|false $id): bool => $id !== false && $id > 0)
            ->unique()
            ->values();

        if ($ids->count() !== $recebidos->count()) {
            return;
        }

        $ids = $ids->all();
        $records = ServidorResource::getEloquentQuery()
            ->withTrashed()
            ->with('user:id')
            ->whereKey($ids)
            ->get()
            ->filter(fn (Servidor $record): bool => ServidorResource::pessoaPodeSerSelecionada($record))
            ->values();

        if ($records->isEmpty() || $records->count() !== count($ids)) {
            return;
        }

        $this->mountTableBulkAction(
            $action,
            $records->map(fn (Servidor $record): string => (string) $record->getKey())->all(),
        );
    }

    public function getSelectedTableRecordsQuery(bool $shouldFetchSelectedRecords = true, ?int $chunkSize = null): Builder
    {
        return ServidorResource::getEloquentQuery()
            ->withTrashed()
            ->with(['user:id,name,email,deleted_at', 'user.roles:id,name'])
            ->whereKey($this->selectedTableRecords);
    }

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Servidores',
            'title' => 'Central de servidores',
            'description' => 'Gerencie identidade, cargos, vínculos, contas, níveis de acesso e permissões no mesmo lugar.',
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                LivewireComponent::make(ServidoresTable::class)
                    ->key('servidores-tabela-principal'),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('solicitacoes_acesso')
                ->label(function (): string {
                    $user = auth()->user();
                    $quantidade = $user
                        ? app(PessoaUsuarioService::class)->usuariosSemPessoaQuery($user)->count()
                        : 0;

                    return $quantidade > 0
                        ? "Solicitações de acesso ({$quantidade})"
                        : 'Solicitações de acesso';
                })
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('viewAny', User::class))
                ->url(fn (): string => UserResource::getUrl('index', [
                    'context' => 'solicitacoes',
                    'tableFilters' => [
                        'sem_pessoa' => ['value' => true],
                    ],
                ])),

            Action::make('create')
                ->label('Novo servidor')
                ->visible(fn (): bool => Gate::allows('create', Servidor::class))
                ->modalWidth('6xl')
                ->modalIcon(null)
                ->modalHeading('Novo servidor')
                ->modalDescription('Identidade, cargo, matrículas e lotações no mesmo fluxo.')
                ->formWrapper(false)
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->extraModalWindowAttributes([
                    'class' => 'pessoa-modal-window',
                ])
                ->stickyModalHeader()
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalContent(fn () => view('components.pessoas.form-modal', [
                    'pessoaId' => null,
                ])),
        ];
    }
}
