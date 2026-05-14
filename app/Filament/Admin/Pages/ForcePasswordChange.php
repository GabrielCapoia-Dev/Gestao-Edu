<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class ForcePasswordChange extends Page
{
    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static ?string $slug = 'alterar-senha-obrigatoria';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.force-password-change';

    protected Width | string | null $maxContentWidth = Width::Full;

    public string $password = '';

    public string $password_confirmation = '';

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string
    {
        try {
            return parent::getUrl($parameters, $isAbsolute, $panel, $tenant);
        } catch (RouteNotFoundException) {
            return $isAbsolute
                ? URL::to('/admin/alterar-senha-obrigatoria')
                : '/admin/alterar-senha-obrigatoria';
        }
    }

    public function mount(): void
    {
        if (! (Auth::user()?->must_change_password ?? false)) {
            $this->redirect(Filament::getUrl());
        }
    }

    public function getTitle(): string
    {
        return 'Redefinir senha';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function salvar()
    {
        $data = $this->validate(
            [
                'password' => [
                    'required',
                    'confirmed',
                    PasswordRule::min(8)->mixedCase()->numbers()->symbols(),
                ],
            ],
            [
                'password.required' => 'Informe a nova senha.',
                'password.confirmed' => 'A confirmacao da senha nao confere.',
            ],
            [
                'password' => 'nova senha',
            ],
        );

        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect()->to(Filament::getLoginUrl());
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        session()->regenerate();

        Notification::make()
            ->title('Senha redefinida')
            ->body('Acesse o sistema com sua nova senha a partir de agora.')
            ->success()
            ->send();

        return redirect()->to(Filament::getUrl());
    }
}
