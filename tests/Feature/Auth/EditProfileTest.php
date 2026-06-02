<?php

namespace Tests\Feature\Auth;

use App\Filament\Admin\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_change_own_email_from_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Nome Original',
            'email' => 'original@example.com',
            'email_approved' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->assertFormSet([
                'email' => 'original@example.com',
            ])
            ->assertFormFieldDisabled('email')
            ->fillForm([
                'name' => 'Nome Atualizado',
                'email' => 'alterado@example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Nome Atualizado', $user->name);
        $this->assertSame('original@example.com', $user->email);
    }
}
