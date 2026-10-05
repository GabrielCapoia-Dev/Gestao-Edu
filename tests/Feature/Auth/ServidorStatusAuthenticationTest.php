<?php

namespace Tests\Feature\Auth;

use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\Role;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ServidorStatusAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_da_pessoa_e_o_gate_de_autenticacao(): void
    {
        $user = User::factory()->create([
            'email_approved' => false,
            'ativo' => false,
        ]);
        $pessoa = Pessoa::query()->create([
            'user_id' => $user->id,
            'nome' => 'Pessoa Ativa',
            'email' => 'pessoa.ativa@edu.umuarama.pr.gov.br',
            'status' => Pessoa::STATUS_ATIVO,
        ]);
        $this->vincularCargo($pessoa);

        $this->assertTrue($user->fresh()->canAuthenticate());

        $pessoa->update(['status' => Pessoa::STATUS_INATIVO]);

        $this->assertFalse($user->fresh()->canAuthenticate());
    }

    public function test_acessar_painel_nao_libera_usuario_sem_pessoa_ativa(): void
    {
        $user = User::factory()->create();
        $permission = Permission::findOrCreate('Acessar Painel', 'web');
        $user->givePermissionTo($permission);

        $this->assertFalse($user->fresh()->canAuthenticate());
        $this->assertFalse($user->fresh()->canAccessAdminPanel());
    }

    public function test_admin_pode_autenticar_com_multiplos_vinculos_funcionais(): void
    {
        User::factory()->create(); // reserva o id raiz para que o teste cubra o papel Admin
        $admin = User::factory()->create(['ativo' => true]);
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));

        foreach (['Primeiro vínculo', 'Segundo vínculo'] as $nome) {
            $pessoa = Pessoa::query()->create([
                'user_id' => $admin->id,
                'nome' => $nome,
                'email' => str($nome)->slug('.').'@edu.umuarama.pr.gov.br',
                'status' => Pessoa::STATUS_ATIVO,
            ]);
            $this->vincularCargo($pessoa);
        }

        $this->assertTrue($admin->fresh()->canAuthenticate());
        $this->assertTrue(User::query()->canAuthenticate()->whereKey($admin->id)->exists());
    }

    public function test_backfill_cria_usuario_e_inativa_cadastro_sem_email(): void
    {
        $elegivel = Pessoa::query()->create([
            'nome' => 'Pessoa Elegível',
            'email' => 'pessoa.elegivel@edu.umuarama.pr.gov.br',
            'status' => Pessoa::STATUS_ATIVO,
        ]);
        $semEmail = Pessoa::query()->create([
            'nome' => 'Pessoa Sem E-mail',
            'status' => Pessoa::STATUS_ATIVO,
        ]);
        $this->vincularCargo($elegivel);
        $this->vincularCargo($semEmail);

        $exitCode = Artisan::call('pessoas:sincronizar-acessos', ['--apply' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertNotNull($elegivel->fresh()->user_id);
        $this->assertTrue($elegivel->fresh()->user->canAuthenticate());
        $this->assertSame(Pessoa::STATUS_INATIVO, $semEmail->fresh()->status);
        $this->assertNotNull($semEmail->fresh()->user_id);
        $this->assertNull($semEmail->fresh()->user->email);
        $this->assertFalse($semEmail->fresh()->user->canAuthenticate());
    }

    private function vincularCargo(Pessoa $pessoa): void
    {
        $funcao = FuncaoAdministrativa::query()->firstOrCreate(
            ['codigo' => 'cargo_teste'],
            [
                'nome' => 'Cargo de Teste',
                'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
                'ativo' => true,
                'exige_professor' => false,
                'concede_acesso_sistema' => true,
            ],
        );

        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => $funcao->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
        ]);
    }
}
