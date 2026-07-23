<?php

namespace Tests\Feature\Maintenance;

use Tests\TestCase;

class MaintenanceRoutesDisabledTest extends TestCase
{
    public function test_http_maintenance_endpoints_are_not_exposed(): void
    {
        $this->postJson('/api/maintenance/login', [
            'email' => 'admin@example.com',
            'password' => 'invalid-password',
        ])->assertNotFound();

        $this->postJson('/api/maintenance/migrate')->assertNotFound();
        $this->getJson('/api/maintenance/info')->assertNotFound();
    }
}
