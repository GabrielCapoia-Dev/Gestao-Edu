<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

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

        $queryParams = [
            'prompt' => 'select_account',
        ];
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user?->hasGoogleOauth()) {
            $queryParams['prompt'] = 'select_account';
            $queryParams['access_type'] = 'offline';
            $queryParams['include_granted_scopes'] = 'true';
        }

        return Socialite::driver('google')
            ->scopes([
                'openid',
                'email',
                'profile',
                'https://www.googleapis.com/auth/drive.metadata.readonly',
                'https://www.googleapis.com/auth/spreadsheets.readonly'

            ])
            ->with($queryParams)
            ->redirect();
    }


    public function callback(GoogleService $service)
    {
        $redirectTo = $this->sanitizeRedirectTo(session()->get('google_auth.redirect_to'));

        try {
            $oauthUser = Socialite::driver('google')->user();

            $user = $service->registrarOuLogar($oauthUser);
            \Filament\Facades\Filament::auth()->login($user, true);

            session()->forget('google_auth.redirect_to');

            return redirect()->intended($redirectTo ?: \Filament\Facades\Filament::getUrl());
        } catch (\Throwable $e) {
            report($e);
            session()->forget('google_auth.redirect_to');

            return redirect()->to($redirectTo ?: route('filament.admin.auth.login'))
                ->withErrors(['google' => 'Falha ao autenticar com Google: ' . $e->getMessage()]);
        }
    }

    protected function sanitizeRedirectTo(?string $redirectTo): ?string
    {
        if (blank($redirectTo)) {
            return null;
        }

        if (str_starts_with($redirectTo, '/')) {
            return $redirectTo;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && str_starts_with($redirectTo, $appUrl . '/')) {
            return $redirectTo;
        }

        return null;
    }
}
