<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleService;
use DomainException;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use RuntimeException;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $redirectTo = $this->sanitizeRedirectTo($request->string('redirect_to')->toString());

        if ($redirectTo) {
            $request->session()->put('google_auth.redirect_to', $redirectTo);
        } else {
            $request->session()->forget('google_auth.redirect_to');
        }

        return Socialite::driver('google')
            ->scopes([
                'openid',
                'email',
                'profile',
            ])
            ->with([
                'prompt' => 'select_account',
            ])
            ->redirect();
    }

    public function callback(GoogleService $service): RedirectResponse
    {
        $redirectTo = $this->sanitizeRedirectTo(session()->get('google_auth.redirect_to'));
        $panel = Filament::getPanel('admin');

        if (! $panel) {
            throw new RuntimeException('Painel admin do Filament nao encontrado.');
        }

        $loginUrl = $panel->getLoginUrl();

        try {
            $oauthUser = Socialite::driver('google')->user();
            $user = $service->registrarOuLogar($oauthUser);

            // Impacto: login Google pode criar/localizar usuario antes da aprovacao. Este bloqueio impede acesso ao painel ate email_approved/permissoes estarem validos.
            if (! $user->canAccessAdminPanel()) {
                session()->forget('google_auth.redirect_to');

                Notification::make()
                    ->title('Aguardando aprovacao')
                    ->body('Seu cadastro foi localizado, mas o acesso ainda depende da aprovacao do administrador.')
                    ->warning()
                    ->persistent()
                    ->send();

                return redirect()->to($loginUrl);
            }

            $panel->auth()->login($user, true);
            session()->regenerate();

            session()->forget('google_auth.redirect_to');

            Notification::make()
                ->title('Acesso permitido')
                ->body('Bem-vindo de volta!')
                ->success()
                ->send();

            if ($user->must_change_password) {
                return redirect()->route('auth.force-password.edit');
            }

            return redirect()->intended($redirectTo ?: $panel->getUrl());
        } catch (Throwable $e) {
            report($e);
            session()->forget('google_auth.redirect_to');

            Notification::make()
                ->title('Falha ao autenticar com Google')
                ->body($this->resolveErrorMessage($e))
                ->danger()
                ->persistent()
                ->send();

            return redirect()->to($loginUrl);
        }
    }

    protected function resolveErrorMessage(Throwable $error): string
    {
        if ($error instanceof InvalidStateException) {
            return 'Sua sessao expirou durante o login com Google. Tente novamente.';
        }

        if ($error instanceof DomainException && filled($error->getMessage())) {
            return $error->getMessage();
        }

        return 'Nao foi possivel concluir o login com Google. Tente novamente em instantes.';
    }

    protected function sanitizeRedirectTo(?string $redirectTo): ?string
    {
        if (blank($redirectTo)) {
            return null;
        }

        // Impacto: este filtro evita open redirect apos OAuth. Aceitar URLs externas aqui cria risco de redirecionar o usuario para dominio nao confiavel.
        if (str_starts_with($redirectTo, '/')) {
            return $redirectTo;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && str_starts_with($redirectTo, $appUrl.'/')) {
            return $redirectTo;
        }

        return null;
    }
}
