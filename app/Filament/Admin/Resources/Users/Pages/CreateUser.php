<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Services\UserService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected string $view = 'filament.admin.resources.users.pages.create-user';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var \App\Models\User $auth */
        $auth = Auth::user();

        if ($auth && ! $auth->hasRole('Admin') && ! empty($auth->id_escola)) {
            $data['id_escola'] = $auth->id_escola;
        }

        if (! empty($data['email_approved'])) {
            $data['email_verified_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(UserService::class)->sincronizarAcessosDoUsuario($this->record, $this->data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl
            ?? request()->query('redirect')
            ?? ServidorResource::getUrl('index', ['tab' => 'com_acesso']);
    }

    public function getOverviewCards(): array
    {
        /** @var \App\Models\User $auth */
        $auth = Auth::user();
        $service = app(UserService::class);

        return [
            [
                'label' => 'Níveis disponíveis',
                'value' => number_format(count($service->opcoesDeRolesParaSelect($auth)), 0, ',', '.'),
                'description' => 'Perfis que você pode atribuir neste cadastro.',
                'icon' => 'heroicon-o-shield-check',
                'tone' => 'amber',
            ],
            [
                'label' => 'Permissões extras',
                'value' => $service->ehAdmin($auth) ? 'Liberadas' : 'Controladas',
                'description' => 'Exceções individuais além dos níveis padrão.',
                'icon' => 'heroicon-o-key',
                'tone' => 'rose',
            ],
            [
                'label' => 'Vinculo escolar',
                'value' => $service->deveTravarCampoEscola($auth, 'create') ? 'Herdado' : 'Editavel',
                'description' => 'Define o alcance operacional do usuário.',
                'icon' => 'heroicon-o-building-library',
                'tone' => 'sky',
            ],
            [
                'label' => 'Vinculo setorial',
                'value' => $service->podeEditarSetor($auth, 'create') ? 'Editavel' : 'Opcional',
                'description' => 'Ajuda a organizar acessos por frente de trabalho.',
                'icon' => 'heroicon-o-building-office-2',
                'tone' => 'emerald',
            ],
        ];
    }

    public function getHighlights(): array
    {
        return [
            'Combine níveis de acesso por tema',
            'Use permissões extras so quando necessário',
            'Defina escola e setor logo no cadastro',
        ];
    }

    public function getSupportItems(): array
    {
        return [
            [
                'title' => 'Cadastro preparado para crescimento',
                'description' => 'Já é possível criar usuários com mais de um nível de acesso, sem perder a simplicidade no preenchimento.',
            ],
            [
                'title' => 'Melhor pratica',
                'description' => 'Priorize níveis de acesso reutilizaveis. As permissões especificas ficam melhores quando usadas apenas para ajustes finos.',
            ],
        ];
    }
}
