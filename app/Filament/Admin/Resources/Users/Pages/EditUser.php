<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Services\PessoaAcessoService;
use App\Services\UserService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected string $view = 'filament.admin.resources.users.pages.edit-user';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $data['roles'] = app(UserService::class)->idsNiveisAdicionais($record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if (! Gate::allows('applyPermissions', $record)) {
            unset($data['roles'], $data['usar_permissoes_extras']);
            $data = collect($data)
                ->reject(fn (mixed $value, string|int $key): bool => str_starts_with((string) $key, 'permissions_'))
                ->all();
        }

        if (! Gate::allows('resetPassword', $record)) {
            unset($data['password']);
        }

        if (! Gate::allows('toggleEmailApproval', [$record, 'table'])) {
            unset($data['email_approved'], $data['email_verified_at']);
        }

        if (! empty($data['email_approved']) && empty($data['email_verified_at'])) {
            $data['email_verified_at'] = now();
        }

        // Não reenviar senha vazia / hash nulo
        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        app(UserService::class)->sincronizarAcessosDoUsuario($this->record, $this->data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl
            ?? UserResource::getUrl('index');
    }

    public function getOverviewCards(): array
    {
        $record = $this->getRecord()->loadMissing([
            'roles',
            'permissions',
            'escola',
            'setor',
            'servidores.professores',
            'professores',
        ]);

        $ehProfessor = app(PessoaAcessoService::class)->usuarioEhProfessor($record);
        $pessoas = $record->servidores->pluck('nome')->filter()->unique()->implode(', ')
            ?: ($record->professores->pluck('nome')->filter()->unique()->implode(', ') ?: '—');

        return [
            [
                'label' => 'Cargo / pessoa',
                'value' => $ehProfessor ? 'Professor' : 'Sem cargo pedagógico',
                'description' => $pessoas !== '—' ? "Pessoa: {$pessoas}" : 'Sem ficha em Pessoas vinculada.',
                'icon' => 'heroicon-o-identification',
                'tone' => $ehProfessor ? 'sky' : 'gray',
            ],
            [
                'label' => 'Níveis ativos',
                'value' => number_format($record->roles->count(), 0, ',', '.'),
                'description' => $record->roles->pluck('name')->join(', ') ?: 'Nenhum nivel vinculado.',
                'icon' => 'heroicon-o-shield-check',
                'tone' => 'amber',
            ],
            [
                'label' => 'Permissões extras',
                'value' => number_format($record->getDirectPermissions()->count(), 0, ',', '.'),
                'description' => 'Permissões aplicadas diretamente ao usuário.',
                'icon' => 'heroicon-o-key',
                'tone' => 'rose',
            ],
            [
                'label' => 'Escola',
                'value' => $record->escola ? Str::limit($record->escola->nome, 24) : 'Não vinculada',
                'description' => 'Escopo operacional principal.',
                'icon' => 'heroicon-o-building-library',
                'tone' => 'emerald',
            ],
        ];
    }

    public function getHighlights(): array
    {
        return [
            'Cargos são geridos na Central de Pessoas',
            'O nível funcional do cargo não pode ser removido pela gestão de acesso',
            'Níveis adicionais e permissões diretas não alteram o cargo',
        ];
    }

    public function getSupportItems(): array
    {
        $record = $this->getRecord()->loadMissing(['roles', 'permissions']);
        $ehProfessor = app(PessoaAcessoService::class)->usuarioEhProfessor($record);

        return [
            [
                'title' => 'Situação atual do acesso',
                'description' => $record->email_approved
                    ? 'O usuário está com acesso liberado ao sistema.'
                    : 'O usuário ainda não está liberado para acessar o sistema.',
            ],
            [
                'title' => $ehProfessor ? 'Cargo Professor vinculado' : 'Sem cargo pedagógico',
                'description' => $ehProfessor
                    ? 'Os níveis padrão do cargo Professor ficam travados. Edite lotação/matrícula em Pessoas.'
                    : 'Este usuário não tem ficha de professor. Atribua o cargo na tela Pessoas se necessário.',
            ],
        ];
    }
}
