<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_homepage_is_public_and_links_to_privacy_policy(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Gestao Edu')
            ->assertSee('href="/politica-de-privacidade"', false)
            ->assertSee('href="/termos-de-servico"', false)
            ->assertSee('/admin/login', false);
    }

    public function test_privacy_policy_is_public_and_has_required_content(): void
    {
        $response = $this->get('/politica-de-privacidade');

        $response
            ->assertOk()
            ->assertSee('Politica de Privacidade')
            ->assertSee('dados')
            ->assertSee('automacao@edu.umuarama.pr.gov.br');
    }

    public function test_terms_of_service_is_public(): void
    {
        $response = $this->get('/termos-de-servico');

        $response
            ->assertOk()
            ->assertSee('Termos de Servico')
            ->assertSee('Uso institucional')
            ->assertSee('automacao@edu.umuarama.pr.gov.br');
    }

    public function test_privacy_policy_url_is_not_the_homepage_url(): void
    {
        $this->assertNotSame(
            route('public.home', [], false),
            route('public.privacy', [], false)
        );
    }
}
