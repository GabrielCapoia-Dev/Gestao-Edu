<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Professors\Pages\ManageProfessors;
use App\Filament\Admin\Resources\Professors\ProfessorResource;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PessoaLegadoRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_professores_e_usuarios_nao_duplicam_a_central_de_pessoas_no_menu(): void
    {
        $this->assertFalse(ProfessorResource::shouldRegisterNavigation());
        $this->assertFalse(UserResource::shouldRegisterNavigation());
        $this->assertSame('Usuários', UserResource::getNavigationLabel());
    }

    public function test_listagem_de_professores_redireciona_para_hub_pessoas(): void
    {
        $usuario = $this->usuarioComPermissoes(['Listar Professores', 'Listar Servidores', 'Listar Pessoas']);

        Livewire::actingAs($usuario)
            ->test(ManageProfessors::class)
            ->assertRedirect(ServidorResource::getUrl('index', ['tab' => 'professores']));
    }

    public function test_listagem_de_usuarios_nao_redireciona_para_hub(): void
    {
        $usuario = $this->usuarioComPermissoes(['Listar Usuarios', 'Listar Usuários', 'Listar Servidores']);

        Livewire::actingAs($usuario)
            ->test(ListUsers::class)
            ->assertSuccessful()
            ->assertNoRedirect();
    }

    /** @param array<int, string> $permissoes */
    private function usuarioComPermissoes(array $permissoes): User
    {
        $permissoesCriadas = collect($permissoes)
            ->map(function (string $permissao): Permission {
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return Permission::query()->firstOrCreate([
                    'name' => $permissao,
                    'guard_name' => 'web',
                ]);
            })
            ->all();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->syncPermissions($permissoesCriadas);
        $usuario->assignRole(Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']));

        return $usuario;
    }
}
