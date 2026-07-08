<?php

namespace App\Filament\Admin\Resources\Servidores\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Servidor;
use App\Services\ServidorService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ManageServidores extends ManageRecords
{
    protected static string $resource = ServidorResource::class;

    protected string $view = 'filament.admin.resources.servidores.pages.manage-pessoas';

    public function mount(): void
    {
        parent::mount();

        if ($this->activeTab === 'usuarios') {
            $this->activeTab = 'com_acesso';
        }
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
        $this->refreshHeaderActionsCache();

        if (filled($this->mountedActions)) {
            $this->unmountAction();
        }
    }

    protected function refreshHeaderActionsCache(): void
    {
        $this->cachedHeaderActions = [];
        $this->cacheInteractsWithHeaderActions();
    }

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Pessoas',
            'title' => 'Central de pessoas',
            'description' => 'Cadastre identidade, vínculos pedagógicos e acesso ao sistema em um único lugar.',
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

            'com_acesso' => Tab::make('Com acesso')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('user_id')),

            'sem_acesso' => Tab::make('Sem acesso')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('user_id')),
        ];
    }

    protected function getHeaderActions(): array
    {
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