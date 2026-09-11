<?php

namespace Tests\Feature\Auth;

use App\Filament\Admin\Pages\Auth\EditProfile;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_user_can_fill_cpf_only_when_person_has_no_cpf(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
        ]);

        $pessoa = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'status' => 'inativo',
        ]);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->assertFormFieldVisible('cpf')
            ->fillForm(['cpf' => '12345678909'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('12345678909', $pessoa->refresh()->cpf);
    }

    public function test_user_cannot_replace_existing_cpf_from_profile(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
        ]);

        Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'cpf' => '12345678909',
            'status' => 'inativo',
        ]);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->assertFormFieldHidden('cpf');
    }

    public function test_user_can_update_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'email_approved' => true,
        ]);

        Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'cpf' => '12345678909',
            'status' => 'ativo',
        ]);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('profilePhoto', UploadedFile::fake()->image('perfil.jpg', 300, 300))
            ->call('save')
            ->assertHasNoErrors();

        $avatarPath = $user->refresh()->avatar_url;

        $this->assertNotNull($avatarPath);
        Storage::disk('public')->assertExists($avatarPath);
    }
}
