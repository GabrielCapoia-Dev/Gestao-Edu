<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\FuncaoAdministrativa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use App\Services\PessoaUsuarioService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PessoaAcessoUnificadoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('permissoes:criar');
        $roleProfessor = Role::query()->firstOrCreate([
            'name' => 'Professor',
            'guard_name' => 'web',
        ]);
        FuncaoAdministrativa::professorPadrao()->rolesPadrao()->syncWithoutDetaching([$roleProfessor->id]);
        $this->admin = User::factory()->create(['email_approved' => true]);
        $this->admin->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());
    }

    public function test_niveis_adicionais_nao_removem_cargo_ou_permissoes_diretas(): void
    {
        $roleProfessor = Role::query()->where('name', 'Professor')->firstOrFail();
        $roleA = Role::query()->create(['name' => 'Acesso Extra A', 'guard_name' => 'web']);
        $roleB = Role::query()->create(['name' => 'Acesso Extra B', 'guard_name' => 'web']);
        $permissaoDireta = Permission::query()->create([
            'name' => 'Permissão Direta de Teste',
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create();
        $user->syncRoles([$roleProfessor, $roleA]);
        $user->givePermissionTo($permissaoDireta);

        $pessoa = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => 'Pessoa com acessos',
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $this->actingAs($this->admin);
        app(UserService::class)->sincronizarNiveisAdicionais(
            $user,
            [$roleB->id],
            'replace',
            $this->admin,
        );

        $user->refresh();
        $this->assertTrue($user->hasRole('Professor'));
        $this->assertTrue($user->hasRole('Acesso Extra B'));
        $this->assertFalse($user->hasRole('Acesso Extra A'));
        $this->assertTrue($user->hasDirectPermission($permissaoDireta));
        $this->assertSame($pessoa->id, $user->servidores()->firstOrFail()->id);
    }

    public function test_central_de_pessoas_expoe_acoes_de_acesso_somente_com_permissoes_de_usuario(): void
    {
        $targetUser = User::factory()->create(['email_approved' => false]);
        $roleExtra = Role::query()->create(['name' => 'Nível em Massa', 'guard_name' => 'web']);
        $pessoa = Servidor::query()->create([
            'user_id' => $targetUser->id,
            'nome' => 'Pessoa alvo',
            'email' => $targetUser->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ManageServidores::class)
            ->assertTableActionVisible('gerenciar_acesso', $pessoa)
            ->assertTableActionVisible('redefinir_senha', $pessoa)
            ->assertTableActionVisible('excluir_acesso', $pessoa)
            ->assertTableBulkActionVisible('verificacao_acesso_em_massa')
            ->assertTableBulkActionVisible('redefinir_senha_em_massa')
            ->assertTableBulkActionVisible('niveis_em_massa')
            ->assertTableBulkActionVisible('permissoes_em_massa')
            ->assertTableBulkActionVisible('excluir_acessos_em_massa');

        Livewire::actingAs($this->admin)
            ->test(ManageServidores::class)
            ->mountTableBulkAction('niveis_em_massa', [$pessoa])
            ->setTableBulkActionData([
                'modo' => 'add',
                'roles' => [$roleExtra->id],
            ])
            ->callMountedTableBulkAction()
            ->assertHasNoTableBulkActionErrors();

        $this->assertTrue($targetUser->fresh()->hasRole($roleExtra));

        $restrito = User::factory()->create(['email_approved' => true]);
        $restrito->givePermissionTo([
            'Listar Pessoas',
            'Acessar Escopo Global de Setores',
        ]);

        Livewire::actingAs($restrito)
            ->test(ManageServidores::class)
            ->assertTableActionHidden('gerenciar_acesso', $pessoa)
            ->assertTableActionHidden('redefinir_senha', $pessoa)
            ->assertTableActionHidden('excluir_acesso', $pessoa)
            ->assertTableBulkActionHidden('verificacao_acesso_em_massa')
            ->assertTableBulkActionHidden('redefinir_senha_em_massa')
            ->assertTableBulkActionHidden('niveis_em_massa')
            ->assertTableBulkActionHidden('permissoes_em_massa')
            ->assertTableBulkActionHidden('excluir_acessos_em_massa');
    }

    public function test_cria_conta_na_pessoa_e_provisiona_role_funcional_sem_tela_de_usuarios_no_menu(): void
    {
        $pessoa = Servidor::query()->create([
            'nome' => 'Pessoa sem acesso',
            'email' => 'pessoa.sem.acesso@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->rolesPadrao()->syncWithoutDetaching([
            Role::query()->where('name', 'Professor')->firstOrFail()->id,
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => $funcao->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'data_inicio' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin);
        $user = app(PessoaUsuarioService::class)->criarOuVincularAcesso(
            $pessoa,
            $this->admin,
            'Mudar@1234',
            true,
        );

        $this->assertSame($user->id, $pessoa->fresh()->user_id);
        $this->assertTrue($user->hasRole('Professor'));
        $this->assertTrue((bool) $user->email_approved);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('Mudar@1234', $user->password));
        $this->assertFalse(UserResource::shouldRegisterNavigation());
    }

    public function test_vincula_solicitacao_de_acesso_a_pessoa_existente_com_mesmo_email(): void
    {
        $conta = User::factory()->create([
            'email' => 'solicitacao@edu.umuarama.pr.gov.br',
            'email_approved' => false,
        ]);
        $pessoa = Servidor::query()->create([
            'nome' => 'Pessoa da solicitação',
            'email' => $conta->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $this->actingAs($this->admin);
        app(PessoaUsuarioService::class)->vincularContaExistente($pessoa, $conta, $this->admin);

        $this->assertSame($conta->id, $pessoa->fresh()->user_id);
        $this->assertSame(0, app(PessoaUsuarioService::class)->usuariosSemPessoaQuery($this->admin)->count());
    }
}
