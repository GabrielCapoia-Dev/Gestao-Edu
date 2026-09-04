<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserPermissionLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_consulta_permissao_parcial_direta_e_via_role_sem_alterar_semantica(): void
    {
        $direta = Permission::findOrCreate('Responder Avaliacoes Especiais');
        $viaRole = Permission::findOrCreate('Responder Avaliacoes');
        $role = Role::findOrCreate('Professor de teste');
        $role->givePermissionTo($viaRole);

        $usuarioDireto = User::factory()->create();
        $usuarioDireto->givePermissionTo($direta);
        $usuarioViaRole = User::factory()->create();
        $usuarioViaRole->assignRole($role);
        $usuarioSemPermissao = User::factory()->create();

        $this->assertTrue($usuarioDireto->hasPermissionLike('responder avaliacoes'));
        $this->assertTrue($usuarioViaRole->hasPermissionLike('responder avaliacoes'));
        $this->assertFalse($usuarioSemPermissao->hasPermissionLike('responder avaliacoes'));
        $this->assertFalse($usuarioViaRole->hasPermissionLike('excluir avaliacoes'));
    }
}
