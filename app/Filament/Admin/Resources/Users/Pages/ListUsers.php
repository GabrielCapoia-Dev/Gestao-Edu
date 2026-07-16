<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\Concerns\HasUsersOverview;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;

class ListUsers extends ListRecords
{
    use HasUsersOverview;

    #[Url]
    public ?string $context = null;

    protected static string $resource = UserResource::class;

    protected string $view = 'filament.admin.resources.users.pages.list-users';

    public function getHeader(): ?View
    {
        $solicitacoes = $this->context === 'solicitacoes';

        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Acesso',
            'title' => $solicitacoes ? 'Solicitações de acesso' : 'Usuários',
            'description' => $solicitacoes
                ? 'Contas criadas pelo login ou cadastro que ainda não possuem uma ficha vinculada na Central de Pessoas.'
                : 'Tela de compatibilidade. A gestão principal de acesso agora fica na Central de Pessoas.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        if ($this->context === 'solicitacoes') {
            return [
                Action::make('voltar_pessoas')
                    ->label('Voltar para Pessoas')
                    ->icon('heroicon-o-arrow-left')
                    ->url(ServidorResource::getUrl('index')),
            ];
        }

        return [
            CreateAction::make()
                ->label('Novo usuário'),
        ];
    }

    public function getSupportItems(): array
    {
        return [
            [
                'title' => 'Gestão centralizada',
                'description' => 'Identidade, cargo, conta e níveis agora são gerenciados na Central de Pessoas.',
            ],
            [
                'title' => 'Cargo e nível adicional são independentes',
                'description' => 'O nível funcional do cargo é preservado; níveis adicionais podem ser incluídos sem alterar o cargo.',
            ],
        ];
    }
}
