<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\GestaoAlternativas;
use App\Models\Alternativa;
use App\Models\TipoAvaliacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_gestao_de_alternativas_persiste_ordem_no_documento_na_criacao_e_edicao(): void
    {
        Permission::findOrCreate('Listar Alternativas');
        Permission::findOrCreate('Criar Alternativas');
        Permission::findOrCreate('Editar Alternativas');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo([
            'Listar Alternativas',
            'Criar Alternativas',
            'Editar Alternativas',
        ]);

        $tipo = TipoAvaliacao::query()->create([
            'nome' => 'Parecer Alternativas Ordenadas',
            'status' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(GestaoAlternativas::class)
            ->call('abrirModalCriacao')
            ->set('form.tipo_avaliacao_id', $tipo->id)
            ->set('form.nome', 'Parcial')
            ->set('form.tem_observacao', false)
            ->set('form.vai_no_documento', true)
            ->set('form.descricao_documento', 'Atingiu a pauta parcialmente.')
            ->set('form.ordem_documento', 2)
            ->set('form.status', true)
            ->call('salvarAlternativa')
            ->assertNotified('Alternativa criada com sucesso.');

        $alternativa = Alternativa::query()->where('nome', 'Parcial')->firstOrFail();

        $this->assertSame(2, $alternativa->ordem_documento);
        $this->assertTrue((bool) $alternativa->vai_no_documento);

        Livewire::actingAs($usuario)
            ->test(GestaoAlternativas::class)
            ->call('abrirModalEdicao', $alternativa->id)
            ->set('form.ordem_documento', 1)
            ->call('salvarAlternativa')
            ->assertNotified('Alternativa atualizada com sucesso.');

        $alternativa->refresh();

        $this->assertSame(1, $alternativa->ordem_documento);
    }
}
