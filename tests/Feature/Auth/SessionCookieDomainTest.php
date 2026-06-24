<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class SessionCookieDomainTest extends TestCase
{
    public function test_login_uses_host_cookie_when_configured_session_domain_does_not_match_request_host(): void
    {
        config(['session.domain' => 'edu.hubdetestes.online']);

        $response = $this
            ->withHeader('Host', 'gestaoedu.umuarama.pr.gov.br')
            ->get('https://gestaoedu.umuarama.pr.gov.br/admin/login');

        $response->assertOk();

        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($sessionCookie);
        $this->assertStringNotContainsString('edu.hubdetestes.online', (string) $sessionCookie);
    }
}
