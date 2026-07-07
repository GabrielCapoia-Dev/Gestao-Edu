<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\GestaoAlternativas;
use App\Filament\Admin\Pages\GestaoPautas;
use App\Models\Alternativa;
use App\Models\Pauta;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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

    public function test_gestao_pautas_delega_acesso_para_policy(): void
    {
        Permission::findOrCreate('Listar Pautas');

        $semPermissao = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $comPermissao = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $comPermissao->givePermissionTo('Listar Pautas');

        $this->actingAs($semPermissao)
            ->get(route('filament.admin.pages.avaliacoes-pautas'))
            ->assertForbidden();

        $this->actingAs($comPermissao)
            ->get(route('filament.admin.pages.avaliacoes-pautas'))
            ->assertOk();
    }

    public function test_gestao_alternativas_delega_acesso_para_policy(): void
    {
        Permission::findOrCreate('Listar Alternativas');

        $semPermissao = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $comPermissao = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $comPermissao->givePermissionTo('Listar Alternativas');

        $this->actingAs($semPermissao)
            ->get(route('filament.admin.pages.avaliacoes-alternativas'))
            ->assertForbidden();

        $this->actingAs($comPermissao)
            ->get(route('filament.admin.pages.avaliacoes-alternativas'))
            ->assertOk();
    }

    public function test_gestao_pautas_bloqueia_acoes_sem_permissao_da_policy(): void
    {
        foreach (['Listar Pautas', 'Criar Pautas', 'Editar Pautas', 'Excluir Pautas'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Pautas');

        $tipo = TipoAvaliacao::create(['nome' => 'Parecer Pautas', 'status' => true]);
        $serie = Serie::create(['codigo' => 'SER-PAUTAS', 'nome' => 'Serie Pautas']);
        $pauta = Pauta::create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta existente',
            'serie_id' => $serie->id,
            'status' => true,
        ]);

        $this->actingAs($usuario);

        $this->assertFalse(Gate::allows('create', Pauta::class));
        $this->assertFalse(Gate::allows('update', $pauta));
        $this->assertFalse(Gate::allows('delete', $pauta));

        Livewire::actingAs($usuario)
            ->test(GestaoPautas::class)
            ->set('form.tipo_avaliacao_id', $tipo->id)
            ->set('form.textos.0.texto', 'Pauta sem permissao')
            ->set('form.serie_id', $serie->id)
            ->set('form.status', true)
            ->call('salvarPauta')
            ->assertForbidden();

        Livewire::actingAs($usuario)
            ->test(GestaoPautas::class)
            ->set('pautaIdEditando', $pauta->id)
            ->set('form.tipo_avaliacao_id', $tipo->id)
            ->set('form.textos.0.texto', 'Pauta alterada sem permissao')
            ->set('form.serie_id', $serie->id)
            ->set('form.status', true)
            ->call('salvarPauta')
            ->assertForbidden();

        Livewire::actingAs($usuario)
            ->test(GestaoPautas::class)
            ->call('excluirPauta', $pauta->id)
            ->assertForbidden();

        $this->assertSame(1, Pauta::count());
        $this->assertDatabaseHas('pautas', [
            'id' => $pauta->id,
            'texto' => 'Pauta existente',
        ]);
    }

    public function test_gestao_alternativas_bloqueia_acoes_sem_permissao_da_policy(): void
    {
        foreach (['Listar Alternativas', 'Criar Alternativas', 'Editar Alternativas', 'Excluir Alternativas'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Alternativas');

        $tipo = TipoAvaliacao::create(['nome' => 'Parecer Alternativas', 'status' => true]);
        $alternativa = Alternativa::create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Alternativa existente',
            'tem_observacao' => false,
            'vai_no_documento' => true,
            'status' => true,
        ]);

        $this->actingAs($usuario);

        $this->assertFalse(Gate::allows('create', Alternativa::class));
        $this->assertFalse(Gate::allows('update', $alternativa));
        $this->assertFalse(Gate::allows('delete', $alternativa));

        Livewire::actingAs($usuario)
            ->test(GestaoAlternativas::class)
            ->set('form.tipo_avaliacao_id', $tipo->id)
            ->set('form.nome', 'Alternativa sem permissao')
            ->set('form.tem_observacao', false)
            ->set('form.vai_no_documento', true)
            ->set('form.status', true)
            ->call('salvarAlternativa')
            ->assertForbidden();

        Livewire::actingAs($usuario)
            ->test(GestaoAlternativas::class)
            ->set('alternativaIdEditando', $alternativa->id)
            ->set('form.tipo_avaliacao_id', $tipo->id)
            ->set('form.nome', 'Alternativa alterada sem permissao')
            ->set('form.tem_observacao', false)
            ->set('form.vai_no_documento', true)
            ->set('form.status', true)
            ->call('salvarAlternativa')
            ->assertForbidden();

        Livewire::actingAs($usuario)
            ->test(GestaoAlternativas::class)
            ->call('excluirAlternativa', $alternativa->id)
            ->assertForbidden();

        $this->assertSame(1, Alternativa::count());
        $this->assertDatabaseHas('alternativas', [
            'id' => $alternativa->id,
            'nome' => 'Alternativa existente',
        ]);
    }

    public function test_gestao_pautas_mantem_execucao_com_permissoes_da_policy(): void
    {
        foreach (['Listar Pautas', 'Criar Pautas', 'Editar Pautas', 'Excluir Pautas'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo([
            'Listar Pautas',
            'Criar Pautas',
            'Editar Pautas',
            'Excluir Pautas',
        ]);

        $tipo = TipoAvaliacao::create(['nome' => 'Parecer Pauta Permitida', 'status' => true]);
        $serie = Serie::create(['codigo' => 'SER-PERMITIDA', 'nome' => 'Serie Permitida']);

        Livewire::actingAs($usuario)
            ->test(GestaoPautas::class)
            ->call('abrirModalCriacao')
            ->set('form.tipo_avaliacao_id', $tipo->id)
            ->set('form.textos.0.texto', 'Pauta permitida')
            ->set('form.serie_id', $serie->id)
            ->set('form.status', true)
            ->call('salvarPauta');

        $pauta = Pauta::where('texto', 'Pauta permitida')->firstOrFail();

        Livewire::actingAs($usuario)
            ->test(GestaoPautas::class)
            ->call('abrirModalEdicao', $pauta->id)
            ->set('form.textos.0.texto', 'Pauta permitida editada')
            ->call('salvarPauta');

        $pauta->refresh();
        $this->assertSame('Pauta permitida editada', $pauta->texto);

        Livewire::actingAs($usuario)
            ->test(GestaoPautas::class)
            ->call('excluirPauta', $pauta->id);

        $this->assertDatabaseMissing('pautas', ['id' => $pauta->id]);
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
