<?php

namespace App\Filament\Admin\Resources\Servidores\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Servidor;
use App\Services\ServidorService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

class ManageServidores extends ManageRecords
{
    protected static string $resource = ServidorResource::class;

    protected string $view = 'filament.admin.resources.servidores.pages.manage-pessoas';

    #[On('pessoa-editor-fechar')]
    public function fecharEditorPessoa(): void
    {
        $this->flushCachedTableRecords();
        $this->unmountAction(false);
    }

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Pessoas',
            'title' => 'Central de pessoas',
            'description' => 'Cadastre identidade, cargos e vínculos pedagógicos. Acesso ao sistema fica em Usuários.',
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nova pessoa')
                ->visible(fn (): bool => Gate::allows('create', Servidor::class))
                ->model(Servidor::class)
                ->modalWidth('6xl')
                ->modalIcon(null)
                ->modalHeading('Nova pessoa')
                ->modalDescription('Identidade, cargo, matrículas e lotações no mesmo fluxo.')
                ->modalCancelActionLabel('Cancelar')
                ->modalSubmitActionLabel('Salvar pessoa')
                ->extraModalWindowAttributes([
                    'class' => 'pessoa-modal-window',
                ])
                ->stickyModalHeader()
                ->closeModalByClickingAway(false)
                ->using(function (array $data): Servidor {
                    try {
                        [$data, $vinculos] = ServidorResource::prepararDadosPersistencia($data);

                        $criado = app(ServidorService::class)->criarServidorComFuncoes(
                            $data,
                            $vinculos,
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Pessoa cadastrada')
                            ->success()
                            ->send();

                        return $criado;
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Não foi possível cadastrar')
                            ->body(collect($e->errors())->flatten()->take(5)->implode(' '))
                            ->danger()
                            ->persistent()
                            ->send();

                        throw $e;
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Erro ao cadastrar pessoa')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        throw $e;
                    }
                }),
        ];
    }
}
