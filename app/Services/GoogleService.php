<?php

namespace App\Services;

use App\Models\Pessoa;
use App\Models\Professor;
use App\Models\User;
use DomainException;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

class GoogleService
{
    public function __construct(
        private readonly ProfessorEscolaVinculoService $professorEscolaVinculoService
    ) {}

    public function registrarOuLogar(SocialiteUserContract $oauthUser): User
    {
        $email = Professor::normalizarEmail((string) $oauthUser->getEmail());

        if ($email === '') {
            throw new DomainException('Não foi possível identificar o e-mail retornado pelo Google.');
        }

        $pessoas = Pessoa::query()
            ->where('email_normalizado', $email)
            ->where('status', Pessoa::STATUS_ATIVO)
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($pessoas->count() !== 1) {
            throw new DomainException(
                $pessoas->isEmpty()
                    ? 'Não existe um servidor ativo cadastrado com este e-mail.'
                    : 'Este e-mail está duplicado em Pessoas. Corrija o cadastro antes de acessar.'
            );
        }

        $pessoa = $pessoas->first();
        $user = app(PessoaUsuarioService::class)->garantirUsuario($pessoa);

        if (! $user->canAuthenticate()) {
            throw new DomainException('Seu cadastro de servidor está inativo. Entre em contato com o administrador.');
        }

        $this->salvarIdentidadeGoogle($user, $oauthUser);
        $this->professorEscolaVinculoService->sincronizarPorUsuario($user, preservarVinculosExistentes: true);

        return $user;
    }

    private function salvarIdentidadeGoogle(User $user, SocialiteUserContract $oauthUser): void
    {
        $googleEmail = Professor::normalizarEmail((string) ($oauthUser->getEmail() ?: $user->google_email));
        $avatarUrl = $oauthUser->getAvatar();

        $user->forceFill([
            'google_id' => $oauthUser->getId() ?: $user->google_id,
            'google_email' => $googleEmail !== '' ? $googleEmail : $user->google_email,
            'avatar_url' => filled($avatarUrl) ? $avatarUrl : $user->avatar_url,
            // Campos legados sao limpos para que o login nao mantenha credenciais de APIs Google.
            'google_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_in' => null,
        ])->save();
    }

}
