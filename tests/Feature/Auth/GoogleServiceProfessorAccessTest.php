<?php

namespace Tests\Feature\Auth;

use App\Models\ComponenteCurricular;
use App\Models\DominioEmail;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
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
        $this->assertTrue($user->hasRole('Visualizar Turmas e Alunos'));
        $this->assertTrue($user->hasPermissionTo('Acessar Painel'));
        $this->assertTrue($user->hasPermissionTo('Listar Turmas'));
        $this->assertTrue($user->hasPermissionTo('Listar Alunos'));
        $this->assertTrue($user->hasPermissionTo('Responder Avaliações'));
        $this->assertTrue($user->canAccessAdminPanel());
    }

    public function test_professor_sem_vinculo_pedagogico_nao_recebe_liberacao_automatica(): void
    {
        DominioEmail::query()->create([
            'dominio_email' => 'edu.umuarama.pr.gov.br',
            'status' => true,
        ]);

        Professor::query()->create([
            'id_escola' => $this->criarEscola('Escola Sem Vinculo')->id,
            'matricula' => 'PROFSEM',
            'nome' => 'Professor Sem Vinculo',
            'email' => 'sem.vinculo@edu.umuarama.pr.gov.br',
        ]);

        $oauthUser = $this->fakeOAuthUser('sem.vinculo@edu.umuarama.pr.gov.br', 'Professor Sem Vinculo');

        $user = app(GoogleService::class)->registrarOuLogar($oauthUser);

        $user->refresh();

        $this->assertFalse((bool) $user->email_approved);
        $this->assertFalse($user->hasRole('Acessar Painel'));
        $this->assertFalse($user->hasRole('Visualizar Turmas e Alunos'));
        $this->assertFalse($user->hasPermissionTo('Acessar Painel'));
        $this->assertFalse($user->hasPermissionTo('Responder Avaliações'));
        $this->assertFalse($user->canAccessAdminPanel());
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
            'codigo' => 'TUR' . strtoupper(substr(md5($nome . microtime()), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function fakeOAuthUser(string $email, string $name): SocialiteUserContract
    {
        return new class($email, $name) implements SocialiteUserContract {
            public string $token = 'fake-token';
            public ?string $refreshToken = 'fake-refresh-token';
            public int $expiresIn = 3600;

            public function __construct(
                private readonly string $email,
                private readonly string $name,
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
                return null;
            }
        };
    }
}
