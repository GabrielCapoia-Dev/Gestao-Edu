<?php

namespace Tests\Feature\Users;

use App\Filament\Admin\Resources\DominioEmails\Pages\ManageDominioEmails;
use App\Models\DominioEmail;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccessRelatedFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_domain_listing_filters_active_and_inactive_records(): void
    {
        $user = User::factory()->create(['email_approved' => true]);
        $user->givePermissionTo(Permission::findOrCreate('Listar Dominios de Email', 'web'));

        $active = DominioEmail::query()->create([
            'dominio_email' => 'ativo.teste.local',
            'setor' => 'Pedagógico',
            'status' => true,
        ]);
        $inactive = DominioEmail::query()->create([
            'dominio_email' => 'inativo.teste.local',
            'setor' => 'Administrativo',
            'status' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ManageDominioEmails::class)
            ->assertTableFilterExists('status')
            ->filterTable('status', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }
}
