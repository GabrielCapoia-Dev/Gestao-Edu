<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Professor;
use App\Models\Role;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use DomainException;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Spatie\Permission\PermissionRegistrar;

class GoogleService
{
    private const ROLE_ACESSAR_PAINEL = 'Acessar Painel';
    private const ROLE_VISUALIZAR_TURMAS_ALUNOS = 'Visualizar Turmas e Alunos';
    private const PERMISSION_ACESSAR_PAINEL = 'Acessar Painel';
    private const PERMISSION_RESPONDER_AVALIACOES = 'Responder Avaliações';

    public function registrarOuLogar(SocialiteUserContract $oauthUser): User
    {
        $email = Professor::normalizarEmail((string) $oauthUser->getEmail());

        if ($email === '') {
            throw new DomainException('Nao foi possivel identificar o e-mail retornado pelo Google.');
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orWhereRaw('LOWER(google_email) = ?', [$email])
            ->first();

        if (! $user) {
            $user = $this->registroGoogle($oauthUser, $email);
        }

        $this->salvarTokens($user, $oauthUser);
        $this->sincronizarProfessorAoUsuario($user, $email);

        return $user;
    }

    private function registroGoogle(SocialiteUserContract $oauthUser, string $email): User
    {
        /** @var \App\Models\User|null $currentUser */
        $currentUser = Auth::user();

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

    private function sincronizarProfessorAoUsuario(User $user, string $email): void
    {
        if (! Professor::emailInstitucionalValido($email)) {
            return;
        }

        $professoresElegiveis = Professor::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
            })
            ->get();

        if ($professoresElegiveis->isEmpty()) {
            return;
        }

        $professorIds = $professoresElegiveis->pluck('id')->all();

        $temVinculoPedagogico = TurmaComponenteProfessor::query()
            ->whereIn('professor_id', $professorIds)
            ->exists();

        if (! $temVinculoPedagogico) {
            return;
        }

        DB::transaction(function () use ($user, $professorIds): void {
            Professor::query()
                ->whereIn('id', $professorIds)
                ->update(['user_id' => $user->id]);

            $this->garantirAcessoProfessor($user);

            if (! $user->email_approved) {
                $user->forceFill([
                    'email_approved' => true,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }
        });
    }

    private function garantirAcessoProfessor(User $user): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate(self::PERMISSION_ACESSAR_PAINEL, 'web');
        Permission::findOrCreate('Visualizar Tela de Inicio', 'web');
        Permission::findOrCreate('Listar Turmas', 'web');
        Permission::findOrCreate('Listar Alunos', 'web');
        Permission::findOrCreate(self::PERMISSION_RESPONDER_AVALIACOES, 'web');

        $roleAcessoPainel = Role::findOrCreate(self::ROLE_ACESSAR_PAINEL, 'web');
        $roleAcessoPainel->syncPermissions([
            'Visualizar Tela de Inicio',
        ]);

        $roleVisualizacaoProfessor = Role::findOrCreate(self::ROLE_VISUALIZAR_TURMAS_ALUNOS, 'web');
        $roleVisualizacaoProfessor->syncPermissions([
            'Listar Turmas',
            'Listar Alunos',
            self::PERMISSION_RESPONDER_AVALIACOES,
        ]);

        $user->givePermissionTo(self::PERMISSION_ACESSAR_PAINEL);
        $user->assignRole($roleAcessoPainel);
        $user->assignRole($roleVisualizacaoProfessor);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Salva ou atualiza tokens do Google para o usuario.
     */
    private function salvarTokens(User $user, SocialiteUserContract $oauthUser): void
    {
        $refresh = $oauthUser->refreshToken ?? $user->google_refresh_token;
        $googleEmail = Professor::normalizarEmail((string) ($oauthUser->getEmail() ?: $user->google_email));

        $expiresIn = $oauthUser->expiresIn ?? 3600;
        $expiresAt = now()->addSeconds(max(60, (int) $expiresIn - 60));

        $user->forceFill([
            'google_id' => $oauthUser->getId() ?: $user->google_id,
            'google_email' => $googleEmail !== '' ? $googleEmail : $user->google_email,
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
