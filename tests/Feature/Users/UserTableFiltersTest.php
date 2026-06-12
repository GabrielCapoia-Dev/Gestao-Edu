<?php

namespace Tests\Feature\Users;

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Escola;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setor;
use App\Models\User;
use App\Services\UserSetorAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserTableFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_listing_filters_by_sector_school_role_and_approval(): void
    {
        $adminRole = Role::findOrCreate('Admin', 'web');
        $secretarioRole = Role::findOrCreate('Secretario', 'web');
        $administrativoRole = Role::findOrCreate('Administrativo', 'web');

        $permissions = collect([
            'Listar Usuarios',
            "Visualizar Setor do Usu\u{00E1}rio",
            UserSetorAccessService::GLOBAL_SCOPE_PERMISSION,
        ])->map(fn (string $permission) => Permission::findOrCreate($permission, 'web'));

        $admin = User::factory()->create(['email_approved' => true]);
        $admin->assignRole($adminRole);
        $admin->givePermissionTo($permissions);

        $root = Setor::create([
            'nome' => 'Secretaria',
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
        ]);
        $pedagogico = Setor::create([
            'nome' => 'Pedagogico',
            'parent_id' => $root->id,
            'ativo' => true,
            'status' => 'Ativo',
        ]);
        $administrativo = Setor::create([
            'nome' => 'Administrativo',
            'parent_id' => $root->id,
            'ativo' => true,
            'status' => 'Ativo',
        ]);

        $escolaA = Escola::create([
            'nome' => 'Escola A',
            'codigo' => 'ESC-A',
            'setor_id' => $pedagogico->id,
            'ativo' => true,
        ]);
        $escolaB = Escola::create([
            'nome' => 'Escola B',
            'codigo' => 'ESC-B',
            'setor_id' => $administrativo->id,
            'ativo' => true,
        ]);

        $matchingUser = User::factory()->create([
            'setor_id' => $pedagogico->id,
            'id_escola' => $escolaA->id,
            'email_approved' => true,
        ]);
        $matchingUser->assignRole($secretarioRole);

        $pendingUser = User::factory()->create([
            'setor_id' => $pedagogico->id,
            'id_escola' => $escolaA->id,
            'email_approved' => false,
        ]);
        $pendingUser->assignRole($secretarioRole);

        $otherUser = User::factory()->create([
            'setor_id' => $administrativo->id,
            'id_escola' => $escolaB->id,
            'email_approved' => true,
        ]);
        $otherUser->assignRole($administrativoRole);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->filterTable('setor_id', $pedagogico->id)
            ->filterTable('id_escola', $escolaA->id)
            ->filterTable('roles', [$secretarioRole->id])
            ->filterTable('email_approved', true)
            ->assertCanSeeTableRecords([$matchingUser])
            ->assertCanNotSeeTableRecords([$pendingUser, $otherUser]);
    }
}
