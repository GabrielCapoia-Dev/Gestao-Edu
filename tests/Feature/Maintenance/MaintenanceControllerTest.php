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

    public function test_commands_catalog_requires_valid_token_and_lists_safe_aliases(): void
    {
        $this->getJson('/api/maintenance/commands')
            ->assertUnauthorized();

        $token = $this->generateMaintenanceToken();

        $this->getJson('/api/maintenance/commands', [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'cache.clear',
                'label' => 'Limpar caches',
            ])
            ->assertJsonFragment([
                'key' => 'queue.retry_failed',
                'label' => 'Reexecutar jobs com falha',
            ]);
    }

    public function test_unknown_command_alias_is_rejected_without_running_artisan(): void
    {
        Artisan::shouldReceive('call')->never();

        $token = $this->generateMaintenanceToken();

        $this->postJson('/api/maintenance/commands/config-show', [], [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Comando de manutencao desconhecido.');
    }

    public function test_queue_run_accepts_only_known_queues_and_runs_until_empty(): void
    {
        $token = $this->generateMaintenanceToken();

        $this->postJson('/api/maintenance/queue/run', [
            'queue' => 'root',
        ], [
            'Authorization' => "Bearer {$token}",
        ])->assertUnprocessable();

        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:work', [
                '--stop-when-empty' => true,
                '--queue' => 'notifications,exports',
                '--tries' => 2,
                '--timeout' => 30,
                '--max-jobs' => 5,
            ])
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('Fila processada.');

        $this->postJson('/api/maintenance/queue/run', [
            'queue' => 'notifications,exports',
            'tries' => 2,
            'timeout' => 30,
            'max_jobs' => 5,
        ], [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('results.0.command', 'queue:work');
    }

    public function test_info_endpoint_returns_operational_summary(): void
    {
        $token = $this->generateMaintenanceToken();

        $this->getJson('/api/maintenance/info', [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonPath('queue.counts.jobs_table_exists', true)
            ->assertJsonStructure([
                'app' => ['name', 'environment', 'laravel', 'php', 'server_time'],
                'database' => ['default', 'connection'],
                'cache' => ['default', 'prefix'],
                'queue' => ['default', 'connection', 'queues', 'counts'],
            ]);
    }

    private function generateMaintenanceToken(): string
    {
        Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create([
            'password' => bcrypt('Senha@1234'),
        ]);
        $admin->assignRole('Admin');

        return (string) $this->postJson('/api/maintenance/login', [
            'email' => $admin->email,
            'password' => 'Senha@1234',
        ])->json('access_token');
    }
}
