<?php

namespace App\Filament\Admin\Pages\Auth;

use Caresome\FilamentAuthDesigner\Pages\Auth\EditProfile as BaseEditProfile;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use App\Models\Pessoa;
use App\Models\Professor;

class EditProfile extends BaseEditProfile
{
    use WithFileUploads;

    protected string $view = 'filament.admin.pages.auth.edit-profile';

    public mixed $profilePhoto = null;

    private ?string $cpfInformado = null;

    private ?Pessoa $pessoaCache = null;

    public function getTitle(): string
    {
        return 'Perfil';
    }

    public function getAvatarPreviewUrl(): string
    {
        return Filament::getUserAvatarUrl($this->getUser());
    }

    public function getPhotoPreviewUrl(): string
    {
        if ($this->profilePhoto instanceof TemporaryUploadedFile) {
            return $this->profilePhoto->temporaryUrl();
        }

        return $this->getAvatarPreviewUrl();
    }

    public function getProfileInitials(): string
    {
        return str($this->getUser()->name)
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
            ->filter()
            ->take(2)
            ->join('');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['cpf'] = $this->getPessoa()?->cpf;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['email']);

        if ($this->profilePhoto instanceof TemporaryUploadedFile) {
            $this->validate([
                'profilePhoto' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ], [
                'profilePhoto.image' => 'Selecione uma imagem válida.',
                'profilePhoto.mimes' => 'A foto deve ser JPG, PNG ou WebP.',
                'profilePhoto.max' => 'A foto deve ter no máximo 2 MB.',
            ]);

            $profilePhotoPath = $this->profilePhoto->storePublicly('profile-photos', 'public');
            $this->deletePreviousLocalAvatar($profilePhotoPath);
            $data['avatar_url'] = $profilePhotoPath;
            $this->profilePhoto = null;
        }

        $this->cpfInformado = Pessoa::normalizarCpf($data['cpf'] ?? null);
        unset($data['cpf']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);
        $pessoa = $this->getPessoa();

        if ($pessoa && blank($pessoa->cpf) && filled($this->cpfInformado)) {
            $pessoa->update(['cpf' => $this->cpfInformado]);
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getCpfFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getCpfFormComponent(): Component
    {
        return TextInput::make('cpf')
            ->label('CPF')
            ->prefixIcon('heroicon-o-identification')
            ->placeholder('Informe seu CPF')
            ->helperText('Depois de preenchido, o CPF só poderá ser corrigido pela equipe autorizada.')
            ->length(11)
            ->rule('digits:11')
            ->rule(fn () => Rule::unique('servidores', 'cpf')->ignore($this->getPessoa()?->getKey()))
            ->visible(fn (): bool => $this->hasCpfPending())
            ->dehydrated(fn (): bool => $this->hasCpfPending());
    }

    public function getPessoa(): ?Pessoa
    {
        if ($this->pessoaCache) {
            return $this->pessoaCache;
        }

        return $this->pessoaCache = $this->getUser()->servidores()
            ->with(['escola', 'setor', 'professores.escola', 'professores.professorMatricula', 'vinculosAtivos.funcaoAdministrativa'])
            ->first();
    }

    public function hasCpfPending(): bool
    {
        return blank($this->getPessoa()?->cpf);
    }

    public function getCargoLabel(): string
    {
        $pessoa = $this->getPessoa();
        $cargos = $pessoa?->vinculosAtivos->map(fn ($vinculo) => $vinculo->funcaoAdministrativa?->nome)->filter()->values() ?? collect();
        if ($pessoa?->professores->contains(fn ($professor): bool => (bool) $professor->ativo)) {
            $cargos->prepend('Professor');
        }
        return $cargos->unique()->implode(', ') ?: 'Não informado';
    }

    public function getEscolaLabel(): string
    {
        $pessoa = $this->getPessoa();
        return collect([$pessoa?->escola?->nome])->merge($pessoa?->professores->map(fn ($professor) => $professor->escola?->nome) ?? [])->filter()->unique()->implode(', ') ?: 'Não informada';
    }

    public function getMatriculaLabel(): string
    {
        $pessoa = $this->getPessoa();
        return collect([$pessoa?->matricula])->merge($pessoa?->professores->map(fn ($professor) => $professor->matricula) ?? [])->filter()->unique()->implode(', ') ?: 'Não informada';
    }

    public function getTurnoLabel(): string
    {
        $turnos = $this->getPessoa()?->professores->map(fn ($professor) => $professor->turnoEfetivo())->filter()->map(fn (string $turno) => Professor::TURNOS[$turno] ?? $turno)->unique() ?? collect();
        return $turnos->implode(', ') ?: 'Não informado';
    }

    public function getStatusLabel(): string
    {
        return $this->getPessoa()?->status === Pessoa::STATUS_ATIVO ? 'Ativo' : 'Inativo';
    }

    public function getSetorLabel(): string
    {
        return $this->getPessoa()?->setor?->nome ?: 'Não informado';
    }

    protected function deletePreviousLocalAvatar(string $newAvatarPath): void
    {
        $previousAvatarPath = $this->publicDiskPathFromAvatar($this->getUser()->avatar_url);

        if (filled($previousAvatarPath) && $previousAvatarPath !== $newAvatarPath) {
            Storage::disk('public')->delete($previousAvatarPath);
        }
    }

    protected function publicDiskPathFromAvatar(?string $avatarUrl): ?string
    {
        if (blank($avatarUrl)) {
            return null;
        }

        $avatarUrl = trim($avatarUrl);

        if (Str::startsWith($avatarUrl, ['http://', 'https://', 'data:image/'])) {
            return null;
        }

        if (Str::startsWith($avatarUrl, ['/storage/', 'storage/'])) {
            return Str::after($avatarUrl, 'storage/');
        }

        return Str::startsWith($avatarUrl, '/') ? null : $avatarUrl;
    }

    protected function getNameFormComponent(): Component
    {
        return parent::getNameFormComponent()
            ->label('Nome')
            ->prefixIcon('heroicon-o-user');
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('E-mail')
            ->prefixIcon('heroicon-o-envelope')
            ->disabled()
            ->dehydrated(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Nova senha')
            ->prefixIcon('heroicon-o-lock-closed');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label('Confirmar senha')
            ->prefixIcon('heroicon-o-lock-closed');
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->label('Senha atual')
            ->prefixIcon('heroicon-o-key')
            ->visible(fn (Get $get): bool => filled($get('password')));
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Salvar perfil')
            ->icon('heroicon-o-check');
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('back')
            ->label('Voltar ao Início')
            ->icon('heroicon-o-home')
            ->color('gray')
            ->action(fn () => $this->redirect(Filament::getUrl()));
    }

    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::End;
    }

}
