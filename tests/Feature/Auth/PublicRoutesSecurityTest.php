<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class PublicRoutesSecurityTest extends TestCase
{
    public function test_debug_and_test_routes_are_not_exposed(): void
    {
        foreach ([
            ['GET', '/background'],
            ['GET', '/exemplo'],
            ['GET', '/escopo'],
            ['GET', '/403'],
            ['GET', '/404'],
            ['GET', '/419'],
            ['GET', '/test'],
            ['POST', '/test/notify'],
            ['POST', '/notifications/any-id/read'],
        ] as [$method, $uri]) {
            $this->call($method, $uri)->assertNotFound();
        }
    }

    public function test_required_public_pages_remain_available(): void
    {
        $this->get('/')->assertOk();
        $this->get('/politica-de-privacidade')->assertOk();
        $this->get('/termos-de-servico')->assertOk();
        $this->get('/admin/login')->assertOk();
    }
}
