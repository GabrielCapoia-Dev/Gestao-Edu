<?php

namespace Tests\Feature\Avaliacoes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AvaliacoesCustomPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginas_customizadas_de_avaliacoes_ficam_acessiveis_com_permissoes_corretas(): void
    {
        Permission::findOrCreate('Listar Avaliações');
        Permission::findOrCreate('Listar Pautas');
        Permission::findOrCreate('Listar Alternativas');
        Permission::findOrCreate('Exportar Avaliações');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo([
            'Listar Avaliações',
            'Listar Pautas',
            'Listar Alternativas',
            'Exportar Avaliações',
        ]);

        $this->actingAs($usuario)
            ->get(route('filament.admin.pages.avaliacoes-gestao'))
            ->assertOk();

        $this->actingAs($usuario)
            ->get(route('filament.admin.pages.avaliacoes-pautas'))
            ->assertOk();

        $this->actingAs($usuario)
            ->get(route('filament.admin.pages.avaliacoes-alternativas'))
            ->assertOk();

        $this->actingAs($usuario)
            ->get(route('filament.admin.pages.avaliacoes-log-exportacoes'))
            ->assertOk();

        $this->actingAs($usuario)
            ->get(route('filament.admin.pages.avaliacoes-exportar'))
            ->assertOk();
    }

    public function test_pagina_exportar_avaliacoes_exige_permissao_de_exportacao(): void
    {
        Permission::findOrCreate('Exportar Avaliações');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($usuario)
            ->get(route('filament.admin.pages.avaliacoes-exportar'))
            ->assertForbidden();
    }

    public function test_resources_antigos_de_avaliacoes_nao_sao_acessiveis(): void
    {
        Permission::findOrCreate('Listar Avaliações');
        Permission::findOrCreate('Listar Pautas');
        Permission::findOrCreate('Listar Alternativas');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo([
            'Listar Avaliações',
            'Listar Pautas',
            'Listar Alternativas',
        ]);

        $this->actingAs($usuario)
            ->get(route('filament.admin.resources.avaliacoes.index'))
            ->assertForbidden();

        $this->actingAs($usuario)
            ->get(route('filament.admin.resources.pautas.index'))
            ->assertForbidden();

        $this->actingAs($usuario)
            ->get(route('filament.admin.resources.alternativas.index'))
            ->assertForbidden();
    }
}
