<?php

namespace App\Services;

use App\Models\User;
use DomainException;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

class GoogleService
{
    public function registrarOuLogar(SocialiteUserContract $oauthUser): User
    {
        $email = trim((string) $oauthUser->getEmail());

        if ($email === '') {
            throw new DomainException('Nao foi possivel identificar o e-mail retornado pelo Google.');
        }

        $user = User::query()
            ->where('email', $email)
            ->orWhere('google_email', $email)
            ->first();

        if (! $user) {
            $user = $this->registroGoogle($oauthUser);
        }

        $this->salvarTokens($user, $oauthUser);

        return $user;
    }

    private function registroGoogle(SocialiteUserContract $oauthUser): User
    {
        /** @var \App\Models\User|null $currentUser */
        $currentUser = Auth::user();
        $email = trim((string) $oauthUser->getEmail());

        if ($email === '') {
            throw new DomainException('Nao foi possivel identificar o e-mail retornado pelo Google.');
        }

        // Bloqueia o fluxo quando o dominio de e-mail nao esta autorizado.
        if (! app('App\Services\DominioEmailService')->isEmailAutorizado($email)) {
            throw new DomainException('Seu e-mail nao esta autorizado. Entre em contato com o administrador.');
        }

        if ($currentUser instanceof User) {
            return $currentUser;
        }

        return User::create([
            'name' => $oauthUser->getName() ?? 'Usuario Sem Nome',
            'email' => $email,
            'password' => bcrypt(Str::random(16)),
            'email_approved' => false,
            'email_verified_at' => null,
        ]);
    }

    /**
     * Salva ou atualiza tokens do Google para o usuario.
     */
    private function salvarTokens(User $user, SocialiteUserContract $oauthUser): void
    {
        $refresh = $oauthUser->refreshToken ?? $user->google_refresh_token;

        $expiresIn = $oauthUser->expiresIn ?? 3600;
        $expiresAt = now()->addSeconds(max(60, (int) $expiresIn - 60));

        $user->forceFill([
            'google_id' => $oauthUser->getId() ?: $user->google_id,
            'google_email' => $oauthUser->getEmail() ?: $user->google_email,
            'google_token' => $oauthUser->token,
            'google_refresh_token' => $refresh,
            'google_token_expires_in' => $expiresAt,
        ])->save();
    }

    /**
     * Retorna um Google Client autenticado para o usuario.
     */
    public function getGoogleClient(User $user): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setAccessToken([
            'access_token' => $user->google_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in' => $user->google_token_expires_in,
        ]);

        if ($client->isAccessTokenExpired() && $user->google_refresh_token) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
            $user->update([
                'google_token' => $newToken['access_token'] ?? null,
                'google_token_expires_in' => $newToken['expires_in'] ?? null,
            ]);
        }

        return $client;
    }
}
