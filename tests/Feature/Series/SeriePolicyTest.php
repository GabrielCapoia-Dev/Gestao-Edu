<?php

namespace Tests\Feature\Series;

use App\Models\User;
use App\Policies\SeriePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SeriePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_any_authorizes_bulk_actions_without_a_serie_instance(): void
    {
        Permission::findOrCreate('Editar Séries');

        $user = User::factory()->create(['email_approved' => true]);
        $user->givePermissionTo('Editar Séries');

        $this->assertTrue(app(SeriePolicy::class)->updateAny($user));
    }
}
