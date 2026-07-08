<?php

namespace App\Filament\Admin\Resources\Servidores\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Services\ServidorService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ManageServidores extends ManageRecords
{
    protected static string $resource = ServidorResource::class;

    public function mount(): void
    {
        parent::mount();

        $tab = request()->query('activeTab');

        if (filled($tab) && array_key_exists($tab, $this->getCachedTabs())) {
            $this->activeTab = (string) $tab;
        }
    }

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Pessoas',
            'title' => 'Central de pessoas',
            'description' => 'Cadastro único de servidores, vínculos por matrícula, perfis pedagógicos e acesso ao sistema.',
        ]);
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

            'usuarios' => Tab::make('Usuários')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('user_id')),
        ];
    }

    protected function getHeaderActions(): array
    {
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
}