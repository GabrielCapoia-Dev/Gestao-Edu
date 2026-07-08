<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Professors\Pages\ManageProfessors;
use App\Filament\Admin\Resources\Professors\ProfessorResource;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\UserResource;
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

    public function test_recursos_legados_nao_aparecem_no_menu(): void
    {
        $this->assertFalse(ProfessorResource::shouldRegisterNavigation());
        $this->assertFalse(UserResource::shouldRegisterNavigation());
    }

    public function test_listagem_de_professores_redireciona_para_hub(): void
    {
        $usuario = $this->usuarioComPermissoes(['Listar Professores', 'Listar Servidores']);

        Livewire::actingAs($usuario)
            ->test(ManageProfessors::class)
            ->assertRedirect(ServidorResource::getUrl('index', ['tab' => 'professores']));
    }

    public function test_listagem_de_usuarios_redireciona_para_hub(): void
    {
        $usuario = $this->usuarioComPermissoes(['Listar Usuarios', 'Listar Servidores']);

        Livewire::actingAs($usuario)
            ->test(ListUsers::class)
            ->assertRedirect(ServidorResource::getUrl('index', ['tab' => 'usuarios']));
    }

    /** @param array<int, string> $permissoes */
    private function usuarioComPermissoes(array $permissoes): User
    {
        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao);
        }

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo($permissoes);

        return $usuario;
    }
}