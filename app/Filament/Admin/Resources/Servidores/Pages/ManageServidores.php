<?php

namespace App\Filament\Admin\Resources\Servidores\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Users\Concerns\HasUsersOverview;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Tables\UsersTable;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
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
use Illuminate\Support\Facades\Auth;

class ManageServidores extends ManageRecords
{
    use HasUsersOverview;

    protected static string $resource = ServidorResource::class;

    protected string $view = 'filament.admin.resources.servidores.pages.manage-pessoas';

    public function mount(): void
    {
        parent::mount();

        $tab = request()->query('activeTab');

        if (filled($tab) && array_key_exists($tab, $this->getCachedTabs())) {
            $this->activeTab = (string) $tab;
        }
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    public function abaUsuarios(): bool
    {
        return $this->activeTab === 'usuarios';
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

    public function table(Table $table): Table
    {
        if ($this->abaUsuarios()) {
            return UsersTable::configure($table);
        }

        $table = static::getResource()::table($table);

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
                    'servidorFuncoes',
                    fn (Builder $vinculos): Builder => $vinculos
                        ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                        ->whereHas(
                            'funcaoAdministrativa',
                            fn (Builder $funcao): Builder => $funcao->where('exige_professor', true)
                        )
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
                        'redirect' => ServidorResource::getUrl('index', ['activeTab' => 'usuarios']),
                    ])),
            ];
        }

        return [
            CreateAction::make()
                ->label('Nova pessoa')
                ->slideOver()
                ->closeModalByClickingAway(false)
                ->using(function (array $data): Servidor {
                    $vinculos = $data['vinculos_funcionais'] ?? [];
                    unset($data['vinculos_funcionais']);

                    return app(ServidorService::class)->criarServidorComFuncoes($data, $vinculos);
                }),
        ];
    }

    private function enriquecerTabelaProfessores(Table $table): Table
    {
        return $table->pushColumns([
            TextColumn::make('escolas_pedagogicas')
                ->label('Escolas')
                ->getStateUsing(fn (Servidor $record): string => $record->servidorFuncoesAtivas
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