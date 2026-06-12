<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Professor;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Spatie\Permission\PermissionRegistrar;

class GoogleService
{
    private const ROLE_ACESSAR_PAINEL = 'Acessar Painel';
    private const ROLE_PROFESSOR = 'Professor';
    private const ROLE_VISUALIZAR_TURMAS_ALUNOS = 'Visualizar Turmas e Alunos';
    private const PERMISSION_ACESSAR_PAINEL = 'Acessar Painel';
    private const PERMISSION_RESPONDER_AVALIACOES = 'Responder Avaliações';
    
    public function __construct(
        private readonly ProfessorEscolaVinculoService $professorEscolaVinculoService
    ) {}

    public function registrarOuLogar(SocialiteUserContract $oauthUser): User
    {
        $email = Professor::normalizarEmail((string) $oauthUser->getEmail());

        // Impacto: todo o vinculo professor-usuario depende do e-mail normalizado. Alterar normalizacao aqui pode duplicar usuarios ou perder autoaprovacao de professor.
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

        $this->salvarIdentidadeGoogle($user, $oauthUser);
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
        // Impacto: somente e-mail institucional participa do auto-vinculo docente; ampliar esta regra pode aprovar contas externas com permissoes de professor.
        if (! Professor::emailInstitucionalValido($email)) {
            return;
        }

        $deveSincronizarVinculos = $user->professores()->exists()
            || $user->hasRole(self::ROLE_VISUALIZAR_TURMAS_ALUNOS);

        $professoresElegiveis = Professor::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
            })
            ->get();

        // Impacto: quando nao ha professor elegivel, ainda sincronizamos escolas se o usuario ja tinha vinculo/role. Remover isso pode deixar acesso antigo sem escola_user atualizado.
        if ($professoresElegiveis->isEmpty()) {
            if ($deveSincronizarVinculos) {
                $this->professorEscolaVinculoService->sincronizarPorUsuario($user, preservarVinculosExistentes: true);
            }
            return;
        }

        $professorIds = $professoresElegiveis->pluck('id')->all();
        $nomeProfessor = $professoresElegiveis
            ->pluck('nome')
            ->filter()
            ->first();

        DB::transaction(function () use ($user, $professorIds, $nomeProfessor): void {
            Professor::query()
                ->whereIn('id', $professorIds)
                ->update(['user_id' => $user->id]);

            $this->garantirAcessoProfessor($user);

            $dadosUsuario = [
                'email_approved' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ];

            if (filled($nomeProfessor)) {
                $dadosUsuario['name'] = $nomeProfessor;
            }

            $user->forceFill($dadosUsuario)->save();
        });

        $this->professorEscolaVinculoService->sincronizarPorUsuario($user, preservarVinculosExistentes: true);
    }

    private function garantirAcessoProfessor(User $user): void
    {
        // Impacto: roles e permissoes criadas aqui liberam painel, turmas, alunos e avaliacoes. Remover uma delas quebra o login automatico de professores pelo Google.
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

        $roleProfessor = Role::findOrCreate(self::ROLE_PROFESSOR, 'web');
        $roleProfessor->syncPermissions([
            'Listar Turmas',
            'Listar Alunos',
            self::PERMISSION_RESPONDER_AVALIACOES,
        ]);

        $user->givePermissionTo(self::PERMISSION_ACESSAR_PAINEL);
        $user->assignRole($roleAcessoPainel);
        $user->assignRole($roleProfessor);
        $user->assignRole($roleVisualizacaoProfessor);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
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
