<?php

namespace Tests\Feature\Maintenance;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MaintenanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_only_admin_can_generate_maintenance_token(): void
    {
        Role::query()->create([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create([
            'password' => bcrypt('Senha@1234'),
        ]);
        $admin->assignRole('Admin');

        $regular = User::factory()->create([
            'password' => bcrypt('Senha@1234'),
        ]);

        $this->postJson('/api/maintenance/login', [
            'email' => $regular->email,
            'password' => 'Senha@1234',
        ])->assertForbidden();

        $this->postJson('/api/maintenance/login', [
            'email' => $admin->email,
            'password' => 'Senha@1234',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_at',
            ]);
    }

    public function test_maintenance_routes_require_valid_token(): void
    {
        $this->postJson('/api/maintenance/permissions/sync')
            ->assertUnauthorized();
    }

    public function test_permissions_sync_runs_only_after_valid_token(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('permission:cache-reset', [])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('permissoes:criar', [])
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->twice()
            ->andReturn('');

        Role::query()->create([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create([
            'password' => bcrypt('Senha@1234'),
        ]);
        $admin->assignRole('Admin');

        $token = $this->postJson('/api/maintenance/login', [
            'email' => $admin->email,
            'password' => 'Senha@1234',
        ])->json('access_token');

        $this->postJson('/api/maintenance/permissions/sync', [], [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
