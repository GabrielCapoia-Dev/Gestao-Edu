<?php

namespace Tests\Feature;

use Tests\TestCase;

class RelatorioControllerTest extends TestCase
{
    public function test_bulk_ficha_route_returns_not_found_for_missing_view(): void
    {
        $response = $this->get(route('relatorios.bulkFicha', [
            'view' => 'relatorios.inexistente',
            'data' => base64_encode(json_encode(['alunos' => []], JSON_THROW_ON_ERROR)),
        ]));

        $response->assertNotFound();
    }
}
