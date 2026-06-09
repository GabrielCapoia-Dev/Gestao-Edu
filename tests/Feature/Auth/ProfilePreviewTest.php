<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Services\ProfilePreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProfilePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_without_permission_cannot_start_profile_preview(): void
    {
        $admin = User::factory()->create(['email_approved' => true]);
        $target = User::factory()->create(['email_approved' => true]);

        $this->actingAs($admin)
            ->post(route('profile-preview.start'), ['target_user_id' => $target->id])
            ->assertSessionHasErrors('target_user_id');

        $this->assertFalse(session()->has('profile_preview'));
    }

    public function test_user_with_permission_can_start_and_stop_profile_preview(): void
    {
        $admin = $this->createPreviewAdmin();
        $target = User::factory()->create([
            'name' => 'Usuario Escola',
            'email_approved' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('profile-preview.start'), ['target_user_id' => $target->id])
            ->assertRedirect();

        $this->assertSame($admin->id, session('profile_preview.real_user_id'));
        $this->assertSame($target->id, session('profile_preview.target_user_id'));
        $this->assertNotEmpty(session('profile_preview.started_at'));

        $this->post(route('profile-preview.stop'))
            ->assertRedirect();

        $this->assertFalse(session()->has('profile_preview'));
    }

    public function test_unapproved_target_user_cannot_be_previewed(): void
    {
        $admin = $this->createPreviewAdmin();
        $target = User::factory()->create(['email_approved' => false]);

        $this->actingAs($admin)
            ->post(route('profile-preview.start'), ['target_user_id' => $target->id])
            ->assertSessionHasErrors('target_user_id');

        $this->assertFalse(session()->has('profile_preview'));
    }

    public function test_active_profile_preview_uses_target_user_as_effective_auth_user(): void
    {
        Route::middleware(['web', 'auth'])
            ->get('/__profile-preview-effective-user', fn () => response()->json([
                'auth_id' => Auth::id(),
            ]))
            ->name('test.profile-preview.effective-user');

        $admin = $this->createPreviewAdmin();
        $target = User::factory()->create(['email_approved' => true]);

        $this->actingAs($admin)
            ->withSession([
                'profile_preview' => [
                    'real_user_id' => $admin->id,
                    'target_user_id' => $target->id,
                    'started_at' => now()->toISOString(),
                ],
            ])
            ->get('/__profile-preview-effective-user')
            ->assertOk()
            ->assertJson(['auth_id' => $target->id]);
    }

    public function test_active_profile_preview_blocks_write_requests(): void
    {
        Route::middleware(['web', 'auth'])
            ->post('/__profile-preview-write', fn () => response('written'))
            ->name('test.profile-preview.write');

        $admin = $this->createPreviewAdmin();
        $target = User::factory()->create(['email_approved' => true]);

        $this->actingAs($admin)
            ->withSession([
                'profile_preview' => [
                    'real_user_id' => $admin->id,
                    'target_user_id' => $target->id,
                    'started_at' => now()->toISOString(),
                ],
            ])
            ->post('/__profile-preview-write')
            ->assertRedirect();
    }

    public function test_permissions_command_creates_profile_preview_permission_for_admin(): void
    {
        Artisan::call('permissoes:criar');

        $this->assertDatabaseHas('permissions', [
            'name' => ProfilePreviewService::PERMISSION,
            'guard_name' => 'web',
        ]);

        $adminRole = Role::findByName('Admin', 'web');

        $this->assertTrue($adminRole->hasPermissionTo(ProfilePreviewService::PERMISSION));
    }

    private function createPreviewAdmin(): User
    {
        $permission = Permission::query()->firstOrCreate([
            'name' => ProfilePreviewService::PERMISSION,
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create(['email_approved' => true]);
        $admin->givePermissionTo($permission);

        return $admin;
    }
}
