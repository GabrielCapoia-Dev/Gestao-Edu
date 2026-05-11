<?php

namespace App\Filament\Admin\Pages\Auth;

use Caresome\FilamentAuthDesigner\Pages\Auth\EditProfile as BaseEditProfile;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditProfile extends BaseEditProfile
{
    protected string $view = 'filament.admin.pages.auth.edit-profile';

    public function getTitle(): string
    {
        return 'Perfil';
    }

    public function getAvatarPreviewUrl(): string
    {
        return Filament::getUserAvatarUrl($this->getUser());
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
        $data['profile_photo'] = $this->publicDiskPathFromAvatar($data['avatar_url'] ?? null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $profilePhoto = Arr::first(Arr::wrap($data['profile_photo'] ?? null));

        unset($data['profile_photo']);

        if (filled($profilePhoto)) {
            $this->deletePreviousLocalAvatar((string) $profilePhoto);
            $data['avatar_url'] = $profilePhoto;
        }

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Foto de perfil')
                    ->description('Use uma imagem quadrada para manter o avatar bem enquadrado.')
                    ->schema([
                        $this->getProfilePhotoFormComponent(),
                    ])
                    ->extraAttributes(['class' => 'edu-profile-photo-section']),

                Section::make('Dados da conta')
                    ->description('Mantenha seu nome e e-mail atualizados no sistema.')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            $this->getNameFormComponent(),
                            $this->getEmailFormComponent(),
                        ]),
                    ])
                    ->extraAttributes(['class' => 'edu-profile-account-section']),

                Section::make('Seguranca da conta')
                    ->description('Preencha somente se quiser alterar sua senha.')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                            $this->getCurrentPasswordFormComponent()->columnSpanFull(),
                        ]),
                    ])
                    ->extraAttributes(['class' => 'edu-profile-password-section']),
            ]);
    }

    protected function getProfilePhotoFormComponent(): Component
    {
        return FileUpload::make('profile_photo')
            ->label('Nova foto')
            ->avatar()
            ->imageEditor()
            ->circleCropper()
            ->disk('public')
            ->directory('profile-photos')
            ->visibility('public')
            ->maxSize(2048)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->helperText('JPG, PNG ou WebP, ate 2 MB.')
            ->columnSpanFull();
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
            ->prefixIcon('heroicon-o-envelope');
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
            ->prefixIcon('heroicon-o-key');
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Salvar perfil')
            ->icon('heroicon-o-check');
    }

    public function getFormActionsAlignment(): string | Alignment
    {
        return Alignment::End;
    }

    protected function deletePreviousLocalAvatar(string $newAvatarPath): void
    {
        $previousAvatarPath = $this->publicDiskPathFromAvatar($this->getUser()->avatar_url);

        if (
            filled($previousAvatarPath)
            && $previousAvatarPath !== $newAvatarPath
        ) {
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

        if (Str::startsWith($avatarUrl, '/storage/')) {
            return Str::after($avatarUrl, '/storage/');
        }

        if (Str::startsWith($avatarUrl, 'storage/')) {
            return Str::after($avatarUrl, 'storage/');
        }

        if (Str::startsWith($avatarUrl, '/')) {
            return null;
        }

        return $avatarUrl;
    }
}
