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
                'password.confirmed' => 'As senhas devem ser iguais.',
                'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
                'password.mixed' => 'Use letras maiusculas e minusculas.',
                'password.numbers' => 'Use pelo menos um numero.',
                'password.symbols' => 'Use pelo menos um caractere especial.',
            ],
            [
                'password' => 'senha',
                'password_confirmation' => 'confirmacao da senha',
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

        Notification::make()
            ->title('Senha redefinida')
            ->body('Entre novamente usando a nova senha.')
            ->success()
            ->send();

        $this->reset('password', 'password_confirmation');

        Filament::auth()->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return redirect()->to(Filament::getLoginUrl());
    }
}
