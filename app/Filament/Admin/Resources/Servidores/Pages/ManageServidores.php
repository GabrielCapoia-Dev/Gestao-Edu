<?php

namespace App\Filament\Admin\Resources\Servidores\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Users\Concerns\HasUsersOverview;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Tables\UsersTable;
use App\Models\Servidor;
use App\Models\User;
use App\Services\ServidorService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ManageServidores extends ManageRecords
{
    use HasUsersOverview;

    protected static string $resource = ServidorResource::class;

    protected string $view = 'filament.admin.resources.servidores.pages.manage-pessoas';

    public function updatedActiveTab(): void
    {
        $this->resetTable();
        $this->refreshHeaderActionsCache();

        if (filled($this->mountedActions)) {
            $this->unmountAction();
        }
    }

    public function abaUsuarios(): bool
    {
        return $this->activeTab === 'usuarios';
    }

    protected function refreshHeaderActionsCache(): void
    {
        $this->cachedHeaderActions = [];
        $this->cacheInteractsWithHeaderActions();
    }

    public function getHeader(): ?View
    {
        if ($this->abaUsuarios()) {
            return view('filament.admin.pages.partials.page-header', [
                'actions' => $this->getCachedHeaderActions(),
                'eyebrow' => 'Pessoas',
                'title' => 'Usuários do sistema',
                'description' => 'Gerencie níveis de acesso, permissões e verificação dos usuários vinculados às pessoas.',
            ]);
        }

        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Pessoas',
            'title' => 'Central de pessoas',
            'description' => 'Cadastro único de servidores, vínculos por matrícula, perfis pedagógicos e acesso ao sistema.',
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function makeTable(): Table
    {
        if ($this->abaUsuarios()) {
            return $this->makeBaseTable()
                ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
                ->query(fn (): Builder => $this->getTableQuery())
                ->modelLabel('Usuário')
                ->pluralModelLabel('Usuários');
        }

        return parent::makeTable();
    }

    public function table(Table $table): Table
    {
        if ($this->abaUsuarios()) {
            return UsersTable::configure($table);
        }

        if ($this->activeTab === 'professores') {
            return $this->enriquecerTabelaProfessores($table);
        }

        return $table;
    }

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos'),

            'professores' => Tab::make('Professores')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas(
                    'professores',
                    fn (Builder $professores): Builder => $professores->where('ativo', true),
                )),

            'usuarios' => Tab::make('Usuários'),
        ];
    }

    protected function getTableQuery(): Builder
    {
        if ($this->abaUsuarios()) {
            return User::query();
        }

        return parent::getTableQuery();
    }

    protected function getHeaderActions(): array
    {
        if ($this->abaUsuarios()) {
            return [
                Action::make('novoUsuario')
                    ->label('Novo usuário')
                    ->icon(Heroicon::UserPlus)
                    ->url(fn (): string => CreateUser::getUrl([
                        'redirect' => ServidorResource::getUrl('index', ['tab' => 'usuarios']),
                    ])),
            ];
        }

        return [
            CreateAction::make()
                ->label('Nova pessoa')
                ->model(Servidor::class)
                ->slideOver()
                ->modalWidth('7xl')
                ->closeModalByClickingAway(false)
                ->using(function (array $data): Servidor {
                    $registros = $data['registros_professor'] ?? [];
                    unset($data['registros_professor']);
                    $data['cargo'] = $data['cargo'] ?? ServidorResource::CARGO_PROFESSOR;

                    return app(ServidorService::class)->criarServidorComFuncoes(
                        $data,
                        ['registros_professor' => $registros],
                    );
                }),
        ];
    }

    private function enriquecerTabelaProfessores(Table $table): Table
    {
        return $table->pushColumns([
            TextColumn::make('escolas_pedagogicas')
                ->label('Escolas')
                ->getStateUsing(fn (Servidor $record): string => $record->professores
                    ->loadMissing('escola')
                    ->pluck('escola.nome')
                    ->filter()
                    ->unique()
                    ->implode(', ') ?: '—')
                ->wrap()
                ->toggleable(),
        ]);
    }
}