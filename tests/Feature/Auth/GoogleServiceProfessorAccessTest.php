<?php

namespace Tests\Feature\Auth;

use App\Models\ComponenteCurricular;
use App\Models\DominioEmail;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\GoogleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Tests\TestCase;

class GoogleServiceProfessorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_com_vinculo_pedagogico_recebe_acesso_automatico_no_login_google(): void
    {
        DominioEmail::query()->create([
            'dominio_email' => 'edu.umuarama.pr.gov.br',
            'status' => true,
        ]);

        $escola = $this->criarEscola('Escola Professor');
        $turma = $this->criarTurma($escola, 'Turma A');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP001',
            'nome' => 'Matematica',
        ]);

        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF001',
            'nome' => 'Professor 01',
            'email' => 'Professor@Edu.Umuarama.PR.GOV.BR',
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $oauthUser = $this->fakeOAuthUser('Professor@Edu.Umuarama.PR.GOV.BR', 'Professor 01');

        $user = app(GoogleService::class)->registrarOuLogar($oauthUser);

        $professor->refresh();
        $user->refresh();

        $this->assertSame($user->id, $professor->user_id);
        $this->assertTrue((bool) $user->email_approved);
        $this->assertTrue($user->hasRole('Acessar Painel'));
        $this->assertTrue($user->hasRole('Professor'));
        $this->assertTrue($user->hasRole('Visualizar Turmas e Alunos'));
        $this->assertTrue($user->hasPermissionTo('Acessar Painel'));
        $this->assertTrue($user->hasPermissionTo('Listar Turmas'));
        $this->assertTrue($user->hasPermissionTo('Listar Alunos'));
        $this->assertTrue($user->hasPermissionTo("Responder Avalia\u{00E7}\u{00F5}es"));
        $this->assertTrue($user->canAccessAdminPanel());
        $this->assertSame([$escola->id], $user->escolas()->pluck('escolas.id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame($escola->id, (int) $user->id_escola);
    }

    public function test_professor_cadastrado_sem_vinculo_pedagogico_recebe_liberacao_automatica(): void
    {
        DominioEmail::query()->create([
            'dominio_email' => 'edu.umuarama.pr.gov.br',
            'status' => true,
        ]);

        $escola = $this->criarEscola('Escola Sem Vinculo');

        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROFSEM',
            'nome' => 'Professor Sem Vinculo',
            'email' => 'sem.vinculo@edu.umuarama.pr.gov.br',
        ]);

        $oauthUser = $this->fakeOAuthUser('sem.vinculo@edu.umuarama.pr.gov.br', 'Professor Sem Vinculo');

        $user = app(GoogleService::class)->registrarOuLogar($oauthUser);

        $professor->refresh();
        $user->refresh();

        $this->assertSame($user->id, $professor->user_id);
        $this->assertTrue((bool) $user->email_approved);
        $this->assertTrue($user->hasRole('Acessar Painel'));
        $this->assertTrue($user->hasRole('Professor'));
        $this->assertTrue($user->hasRole('Visualizar Turmas e Alunos'));
        $this->assertTrue($user->hasPermissionTo('Acessar Painel'));
        $this->assertTrue($user->hasPermissionTo("Responder Avalia\u{00E7}\u{00F5}es"));
        $this->assertTrue($user->canAccessAdminPanel());
        $this->assertSame([$escola->id], $user->escolas()->pluck('escolas.id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame($escola->id, (int) $user->id_escola);
    }

    public function test_login_google_salva_foto_do_perfil_para_avatar_do_filament(): void
    {
        DominioEmail::query()->create([
            'dominio_email' => 'edu.umuarama.pr.gov.br',
            'status' => true,
        ]);

        $avatarUrl = 'https://lh3.googleusercontent.com/a/profile-photo';
        $oauthUser = $this->fakeOAuthUser('foto@edu.umuarama.pr.gov.br', 'Usuario Foto', $avatarUrl);

        $user = app(GoogleService::class)->registrarOuLogar($oauthUser);
        $user->refresh();

        $this->assertSame($avatarUrl, $user->avatar_url);
        $this->assertSame($avatarUrl, $user->getFilamentAvatarUrl());
    }

    public function test_login_google_preserva_escola_e_setor_de_usuario_existente_sem_vinculo_pedagogico(): void
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor Escola',
            'ativo' => true,
            'status' => 'Ativo',
        ]);

        $escola = $this->criarEscola('Escola Vinculada Manualmente');
        $escola->update(['setor_id' => $setor->id]);

        $role = Role::query()->create([
            'name' => 'Visualizar Turmas e Alunos',
            'guard_name' => 'web',
        ]);

        $userExistente = User::factory()->create([
            'name' => 'Usuario Escola',
            'email' => 'usuario.escola@edu.umuarama.pr.gov.br',
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'email_approved' => true,
        ]);
        $userExistente->assignRole($role);
        $userExistente->escolas()->attach($escola->id);

        $oauthUser = $this->fakeOAuthUser('usuario.escola@edu.umuarama.pr.gov.br', 'Usuario Escola');

        $user = app(GoogleService::class)->registrarOuLogar($oauthUser);
        $user->refresh();

        $this->assertSame($userExistente->id, $user->id);
        $this->assertSame($escola->id, (int) $user->id_escola);
        $this->assertSame($setor->id, (int) $user->setor_id);
        $this->assertSame([$escola->id], $user->escolas()->pluck('escolas.id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_usuario_pendente_ja_existente_e_aprovado_com_dados_do_professor_no_login_google(): void
    {
        DominioEmail::query()->create([
            'dominio_email' => 'edu.umuarama.pr.gov.br',
            'status' => true,
        ]);

        $escola = $this->criarEscola('Escola Debora');
        $turma = $this->criarTurma($escola, 'Turma Debora');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-DEB',
            'nome' => 'Lingua Portuguesa',
        ]);

        $userPendente = User::factory()->create([
            'name' => 'Gabriel Capoia',
            'email' => 'gabriel.capoia@edu.umuarama.pr.gov.br',
            'email_approved' => false,
            'email_verified_at' => null,
        ]);

        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => '101722',
            'nome' => 'DEBORA SCANHOLATO DAS CHAGAS',
            'email' => 'gabriel.capoia@edu.umuarama.pr.gov.br',
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $oauthUser = $this->fakeOAuthUser('gabriel.capoia@edu.umuarama.pr.gov.br', 'Gabriel Capoia');

        $user = app(GoogleService::class)->registrarOuLogar($oauthUser);

        $professor->refresh();
        $user->refresh();

        $this->assertSame($userPendente->id, $user->id);
        $this->assertSame($user->id, $professor->user_id);
        $this->assertSame('DEBORA SCANHOLATO DAS CHAGAS', $user->name);
        $this->assertTrue((bool) $user->email_approved);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('Professor'));
        $this->assertTrue($user->hasPermissionTo("Responder Avalia\u{00E7}\u{00F5}es"));
        $this->assertTrue($user->canAccessAdminPanel());
    }

    public function test_professor_em_multiplas_escolas_tem_usuario_vinculado_a_todas_no_login(): void
    {
        DominioEmail::query()->create([
            'dominio_email' => 'edu.umuarama.pr.gov.br',
            'status' => true,
        ]);

        $escolaCentro = $this->criarEscola('Escola Centro');
        $escolaJardim = $this->criarEscola('Escola Jardim');

        $turmaCentro = $this->criarTurma($escolaCentro, 'Turma Centro');
        $turmaJardim = $this->criarTurma($escolaJardim, 'Turma Jardim');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-MULTI',
            'nome' => 'Matematica',
        ]);

        $professorCentro = Professor::query()->create([
            'id_escola' => $escolaCentro->id,
            'matricula' => 'PROF-MULTI-01',
            'nome' => 'Professor Multi',
            'email' => 'prof.multi@edu.umuarama.pr.gov.br',
        ]);

        $professorJardim = Professor::query()->create([
            'id_escola' => $escolaJardim->id,
            'matricula' => 'PROF-MULTI-02',
            'nome' => 'Professor Multi',
            'email' => 'prof.multi@edu.umuarama.pr.gov.br',
        ]);

        $turmaCentro->componentes()->attach($componente->id, [
            'professor_id' => $professorCentro->id,
            'tem_professor' => true,
        ]);

        $turmaJardim->componentes()->attach($componente->id, [
            'professor_id' => $professorJardim->id,
            'tem_professor' => true,
        ]);

        $oauthUser = $this->fakeOAuthUser('prof.multi@edu.umuarama.pr.gov.br', 'Professor Multi');

        $user = app(GoogleService::class)->registrarOuLogar($oauthUser);
        $user->refresh();

        $escolasIds = $user->escolas()
            ->orderBy('escolas.id')
            ->pluck('escolas.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([$escolaCentro->id, $escolaJardim->id], $escolasIds);
        $this->assertContains((int) $user->id_escola, $escolasIds);
    }

    public function test_usuario_do_professor_perde_vinculo_de_escola_automaticamente_quando_professor_e_desvinculado(): void
    {
        DominioEmail::query()->create([
            'dominio_email' => 'edu.umuarama.pr.gov.br',
            'status' => true,
        ]);

        $escolaCentro = $this->criarEscola('Escola Centro Sync');
        $escolaJardim = $this->criarEscola('Escola Jardim Sync');

        $turmaCentro = $this->criarTurma($escolaCentro, 'Turma Centro Sync');
        $turmaJardim = $this->criarTurma($escolaJardim, 'Turma Jardim Sync');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-SYNC',
            'nome' => 'Historia',
        ]);

        $professorCentro = Professor::query()->create([
            'id_escola' => $escolaCentro->id,
            'matricula' => 'PROF-SYNC-01',
            'nome' => 'Professor Sync',
            'email' => 'prof.sync@edu.umuarama.pr.gov.br',
        ]);

        $professorJardim = Professor::query()->create([
            'id_escola' => $escolaJardim->id,
            'matricula' => 'PROF-SYNC-02',
            'nome' => 'Professor Sync',
            'email' => 'prof.sync@edu.umuarama.pr.gov.br',
        ]);

        $turmaCentro->componentes()->attach($componente->id, [
            'professor_id' => $professorCentro->id,
            'tem_professor' => true,
        ]);

        $turmaJardim->componentes()->attach($componente->id, [
            'professor_id' => $professorJardim->id,
            'tem_professor' => true,
        ]);

        $oauthUser = $this->fakeOAuthUser('prof.sync@edu.umuarama.pr.gov.br', 'Professor Sync');

        $user = app(GoogleService::class)->registrarOuLogar($oauthUser);
        $user->refresh();

        $this->assertCount(2, $user->escolas()->get());

        $vinculoJardim = TurmaComponenteProfessor::query()
            ->where('turma_id', $turmaJardim->id)
            ->where('componente_curricular_id', $componente->id)
            ->where('professor_id', $professorJardim->id)
            ->firstOrFail();

        $vinculoJardim->delete();

        $user->refresh();

        $escolasIdsAtualizadas = $user->escolas()
            ->orderBy('escolas.id')
            ->pluck('escolas.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([$escolaCentro->id], $escolasIdsAtualizadas);
        $this->assertSame($escolaCentro->id, (int) $user->id_escola);
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 5)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)) . '@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
    }

    private function criarTurma(Escola $escola, string $nome): Turma
    {
        $serie = Serie::query()->create([
            'codigo' => 'SER' . strtoupper(substr(md5($nome), 0, 4)),
            'nome' => 'Serie ' . $nome,
        ]);

        return Turma::query()->create([
            'codigo' => 'TUR' . strtoupper(substr(md5($nome . uniqid('', true)), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function fakeOAuthUser(string $email, string $name, ?string $avatar = null): SocialiteUserContract
    {
        return new class($email, $name, $avatar) implements SocialiteUserContract {
            public string $token = 'fake-token';
            public ?string $refreshToken = 'fake-refresh-token';
            public int $expiresIn = 3600;

            public function __construct(
                private readonly string $email,
                private readonly string $name,
                private readonly ?string $avatar,
            ) {}

            public function getId()
            {
                return 'google-id-' . md5($this->email);
            }

            public function getNickname()
            {
                return null;
            }

            public function getName()
            {
                return $this->name;
            }

            public function getEmail()
            {
                return $this->email;
            }

            public function getAvatar()
            {
                return $this->avatar;
            }
        };
    }
}
