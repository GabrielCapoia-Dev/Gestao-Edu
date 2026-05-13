<?php

namespace Tests\Feature\Users;

use App\Filament\Admin\Pages\ForcePasswordChange;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Livewire\LoginPage;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_bulk_action_sets_default_password_and_requires_password_change(): void
    {
        $admin = $this->userWithPermissions([
            'Listar Usuários',
            'Editar Usuários',
        ]);

        $firstUser = User::factory()->create([
            'password' => Hash::make('SenhaAntiga@123'),
            'must_change_password' => false,
        ]);

        $secondUser = User::factory()->create([
            'password' => Hash::make('OutraSenha@123'),
            'must_change_password' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->mountTableBulkAction('setar_senha_padrao', [$firstUser, $secondUser])
            ->assertTableBulkActionDataSet([
                'nova_senha' => 'Mudar@1234',
            ])
            ->callMountedTableBulkAction()
            ->assertHasNoTableBulkActionErrors();

        $this->assertTrue(Hash::check('Mudar@1234', $firstUser->refresh()->password));
        $this->assertTrue($firstUser->must_change_password);
        $this->assertTrue(Hash::check('Mudar@1234', $secondUser->refresh()->password));
        $this->assertTrue($secondUser->must_change_password);
    }

    public function test_user_marked_to_change_password_is_redirected_to_force_page(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(ForcePasswordChange::getUrl());

        $this->actingAs($user)
            ->get(ForcePasswordChange::getUrl())
            ->assertOk()
            ->assertSee('Redefina sua senha');
    }

    public function test_login_with_default_password_redirects_immediately_to_force_page(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'password' => Hash::make('Mudar@1234'),
            'must_change_password' => true,
        ]);

        Livewire::test(LoginPage::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'Mudar@1234',
            ])
            ->call('authenticate')
            ->assertRedirect(ForcePasswordChange::getUrl());
    }

    public function test_user_marked_to_change_password_cannot_access_custom_admin_routes(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('notifications.center'))
            ->assertRedirect(ForcePasswordChange::getUrl());
    }

    public function test_force_page_updates_password_and_clears_required_change_flag(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'password' => Hash::make('Mudar@1234'),
            'must_change_password' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ForcePasswordChange::class)
            ->set('password', 'SenhaNova@1234')
            ->set('password_confirmation', 'SenhaNova@1234')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(Filament::getUrl());

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('SenhaNova@1234', $user->password));
    }

    private function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create([
            'email_approved' => true,
        ]);

        $user->givePermissionTo($permissions);

        return $user;
    }
}
