<?php

namespace Tests\Feature\Filament;

use Filament\Facades\Filament;
use Tests\TestCase;

class AdminPanelConfigurationTest extends TestCase
{
    public function test_global_search_is_disabled(): void
    {
        $this->assertNull(
            Filament::getPanel('admin')->getGlobalSearchProvider()
        );
    }
}
