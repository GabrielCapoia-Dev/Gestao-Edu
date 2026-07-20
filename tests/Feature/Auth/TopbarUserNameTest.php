<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class TopbarUserNameTest extends TestCase
{
    public function test_topbar_displays_user_name_with_responsive_truncation(): void
    {
        $view = $this->view('filament.partials.topbar-user-menu-before', [
            'userName' => 'Maria da Silva Santos',
            'showOnlineUsers' => false,
            'showNotifications' => false,
        ]);

        $view
            ->assertSee('Maria da Silva Santos')
            ->assertSee('topbar-user-name__value', escape: false)
            ->assertDontSee('topbar-user-name--with-online-users', escape: false);

        $styles = file_get_contents(public_path('css/geral.css'));

        $this->assertStringContainsString('@media (max-width: 640px)', $styles);
        $this->assertStringContainsString('.topbar-user-name--with-online-users', $styles);
    }
}
