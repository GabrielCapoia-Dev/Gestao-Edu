<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarUrlTest extends TestCase
{
    public function test_local_profile_photo_is_served_from_public_disk(): void
    {
        Storage::fake('public');

        $path = 'profile-photos/avatar.png';
        Storage::disk('public')->put($path, 'fake image');

        $user = User::factory()->make([
            'avatar_url' => $path,
        ]);

        $this->assertSame(Storage::disk('public')->url($path), $user->getFilamentAvatarUrl());
    }

    public function test_default_avatar_uses_black_text_on_white_background(): void
    {
        $user = User::factory()->make([
            'name' => 'Admin Escola',
            'avatar_url' => null,
        ]);

        $avatarUrl = $user->getFilamentAvatarUrl();

        $this->assertStringContainsString('color=000000', $avatarUrl);
        $this->assertStringContainsString('background=FFFFFF', $avatarUrl);
    }
}
