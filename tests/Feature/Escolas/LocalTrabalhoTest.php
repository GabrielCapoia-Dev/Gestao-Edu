<?php

namespace Tests\Feature\Escolas;

use App\Filament\Admin\Resources\Escolas\EscolaResource;
use App\Filament\Admin\Resources\Escolas\Pages\ManageEscolas;
use App\Filament\Admin\Resources\Escolas\Pages\ManageLotacoes as ManageLotacoesDoLocal;
use App\Filament\Admin\Resources\Lotacoes\LotacaoResource;
use App\Filament\Admin\Resources\Lotacoes\Pages\ManageLotacoes;
use App\Models\Escola;
use App\Models\LocalTrabalho;
use App\Models\Lotacao;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\EscolaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LocalTrabalhoTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserva_registros_existentes_como_escolas(): void
    {
        $id = DB::table('escolas')->insertGetId([
            'nome' => 'Escola existente',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse((bool) DB::table('escolas')->where('id', $id)->value('nao_e_escola'));
        $this->assertTrue(Schema::hasIndex('escolas', 'escolas_tipo_ativo_nome_index'));
    }

    public function test_escola_e_subtipo_e_locais_nao_escolares_ficam_fora_do_escopo_pedagogico(): void
    {
        $setor = Setor::query()->create(['nome' => 'Rede municipal', 'ativo' => true]);
        $escola = Escola::query()->create([
            'setor_id' => $setor->id,
            'nome' => 'Escola Municipal',
            'email' => 'escola@teste.local',
            'ativo' => true,
        ]);
        $local = LocalTrabalho::query()->create([
            'setor_id' => $setor->id,
            'nome' => 'Secretaria de Educação',
            'email' => 'secretaria@teste.local',
            'ativo' => true,
            'nao_e_escola' => true,
        ]);
        $this->assertEqualsCanonicalizing(
            [$escola->id, $local->id],
            LocalTrabalho::query()->pluck('id')->all(),
        );
        $this->assertSame([$escola->id], Escola::query()->pluck('id')->all());
        $this->assertNull(Escola::query()->find($local->id));
        $this->assertSame('Local não escolar', $local->tipoLabel());
        $this->assertSame(
            [$escola->id => $escola->nome],
            app(EscolaService::class)->opcoesDeEscolas(),
        );
    }

    public function test_classificacao_e_definida_na_criacao_e_nao_pode_ser_alterada(): void
    {
        $local = LocalTrabalho::query()->create([
            'nome' => 'Almoxarifado Central',
            'email' => 'almoxarifado@teste.local',
            'ativo' => true,
            'nao_e_escola' => true,
        ]);

        $this->assertTrue($local->nao_e_escola);

        try {
            $local->update(['nao_e_escola' => false]);
            $this->fail('A classificação deveria ser imutável.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nao_e_escola', $exception->errors());
        }

        $this->assertTrue($local->fresh()->nao_e_escola);
    }

    public function test_resource_lista_escolas_e_locais_do_setor_visivel(): void
    {
        Permission::findOrCreate('Listar Escolas');

        $setorPermitido = Setor::query()->create(['nome' => 'Setor permitido', 'ativo' => true]);
        $setorBloqueado = Setor::query()->create(['nome' => 'Setor bloqueado', 'ativo' => true]);
        $escola = Escola::query()->create([
            'setor_id' => $setorPermitido->id,
            'nome' => 'Escola Permitida',
            'email' => 'escola@teste.local',
            'ativo' => true,
        ]);
        $local = LocalTrabalho::query()->create([
            'setor_id' => $setorPermitido->id,
            'nome' => 'Local Permitido',
            'email' => 'local@teste.local',
            'ativo' => true,
            'nao_e_escola' => true,
        ]);
        $local->lotacoes()->createMany([
            ['codigo' => 'LOT-CONT-001', 'nome' => 'Primeira lotação'],
            ['codigo' => 'LOT-CONT-002', 'nome' => 'Segunda lotação'],
        ]);
        $localBloqueado = LocalTrabalho::query()->create([
            'setor_id' => $setorBloqueado->id,
            'nome' => 'Local Bloqueado',
            'email' => 'bloqueado@teste.local',
            'ativo' => true,
            'nao_e_escola' => true,
        ]);

        $usuario = User::factory()->create(['setor_id' => $setorPermitido->id]);
        $usuario->givePermissionTo('Listar Escolas');
        $this->tornarUsuarioOperacional($usuario, $escola);
        Auth::login($usuario);

        $this->assertEqualsCanonicalizing(
            [$escola->id, $local->id],
            EscolaResource::getEloquentQuery()->pluck('id')->all(),
        );

        Livewire::actingAs($usuario)
            ->test(ManageEscolas::class)
            ->assertCanSeeTableRecords([$escola, $local])
            ->assertTableColumnStateSet('lotacoes_count', 2, $local->loadCount('lotacoes'));

        Livewire::actingAs($usuario)
            ->test(ManageEscolas::class)
            ->filterTable('nao_e_escola', '1')
            ->assertCanSeeTableRecords([$local])
            ->assertCanNotSeeTableRecords([$escola]);

        $this->actingAs($usuario)
            ->get(EscolaResource::getUrl('lotacoes', ['record' => $localBloqueado]))
            ->assertNotFound();
    }

    public function test_pagina_de_lotacoes_lista_e_gerencia_registros_do_local(): void
    {
        Permission::findOrCreate('Listar Escolas');
        Permission::findOrCreate('Editar Escolas');

        $setor = Setor::query()->create(['nome' => 'Setor permitido', 'ativo' => true]);
        $local = LocalTrabalho::query()->create([
            'setor_id' => $setor->id,
            'nome' => 'Paço Municipal',
            'email' => 'paco@teste.local',
            'ativo' => true,
            'nao_e_escola' => true,
        ]);
        $lotacao = $local->lotacoes()->create([
            'codigo' => 'LOT-900',
            'nome' => 'Administrativo',
        ]);
        $usuario = User::factory()->create(['setor_id' => $setor->id]);
        $usuario->givePermissionTo(['Listar Escolas', 'Editar Escolas']);
        $escola = Escola::query()->create([
            'setor_id' => $setor->id,
            'nome' => 'Escola de acesso',
            'email' => 'acesso@teste.local',
            'ativo' => true,
        ]);
        $this->tornarUsuarioOperacional($usuario, $escola);

        $pagina = Livewire::actingAs($usuario)
            ->test(ManageLotacoesDoLocal::class, ['record' => $local->id])
            ->assertCanSeeTableRecords([$lotacao])
            ->callTableAction('create', null, [
                'codigo' => 'LOT-901',
                'nome' => 'Atendimento',
            ])
            ->assertHasNoTableActionErrors();

        $nova = Lotacao::query()->where('codigo', 'LOT-901')->sole();
        $this->assertSame($local->id, $nova->escola_id);

        $pagina
            ->callTableAction('edit', $nova, [
                'codigo' => 'LOT-901',
                'nome' => 'Atendimento ao público',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('lotacoes', [
            'id' => $nova->id,
            'nome' => 'Atendimento ao público',
        ]);

        $pagina->callTableAction('delete', $nova->fresh());
        $this->assertDatabaseMissing('lotacoes', ['id' => $nova->id]);
    }

    public function test_tela_global_de_lotacoes_lista_filtra_e_gerencia_somente_o_escopo_visivel(): void
    {
        Permission::findOrCreate('Listar Escolas');
        Permission::findOrCreate('Editar Escolas');

        $setorPermitido = Setor::query()->create(['nome' => 'Setor permitido', 'ativo' => true]);
        $setorBloqueado = Setor::query()->create(['nome' => 'Setor bloqueado', 'ativo' => true]);
        $escolaPermitida = Escola::query()->create([
            'setor_id' => $setorPermitido->id,
            'nome' => 'Escola Permitida',
            'email' => 'permitida@teste.local',
            'ativo' => true,
        ]);
        $localPermitido = LocalTrabalho::query()->create([
            'setor_id' => $setorPermitido->id,
            'nome' => 'Secretaria Permitida',
            'email' => 'secretaria@teste.local',
            'ativo' => true,
            'nao_e_escola' => true,
        ]);
        $escolaBloqueada = Escola::query()->create([
            'setor_id' => $setorBloqueado->id,
            'nome' => 'Escola Bloqueada',
            'email' => 'bloqueada@teste.local',
            'ativo' => true,
        ]);
        $lotacaoEscola = $escolaPermitida->lotacoes()->create([
            'codigo' => 'LOT-GLOBAL-001',
            'nome' => 'Docentes',
        ]);
        $lotacaoLocal = $localPermitido->lotacoes()->create([
            'codigo' => 'LOT-GLOBAL-002',
            'nome' => 'Administrativo',
        ]);
        $lotacaoBloqueada = $escolaBloqueada->lotacoes()->create([
            'codigo' => 'LOT-GLOBAL-003',
            'nome' => 'Não visível',
        ]);

        $usuario = User::factory()->create(['setor_id' => $setorPermitido->id]);
        $usuario->givePermissionTo(['Listar Escolas', 'Editar Escolas']);
        $this->tornarUsuarioOperacional($usuario, $escolaPermitida);

        $this->actingAs($usuario)
            ->get(LotacaoResource::getUrl())
            ->assertOk();

        $pagina = Livewire::actingAs($usuario)
            ->test(ManageLotacoes::class)
            ->assertCanSeeTableRecords([$lotacaoEscola, $lotacaoLocal])
            ->assertCanNotSeeTableRecords([$lotacaoBloqueada])
            ->filterTable('escola_id', $localPermitido->id)
            ->assertCanSeeTableRecords([$lotacaoLocal])
            ->assertCanNotSeeTableRecords([$lotacaoEscola]);

        $pagina
            ->callAction('create', data: [
                'escola_id' => $localPermitido->id,
                'codigo' => 'LOT-GLOBAL-004',
                'nome' => 'Atendimento',
            ])
            ->assertHasNoActionErrors();

        $nova = Lotacao::query()->where('codigo', 'LOT-GLOBAL-004')->sole();
        $this->assertSame($localPermitido->id, $nova->escola_id);

        $pagina
            ->callTableAction('edit', $nova, [
                'escola_id' => $localPermitido->id,
                'codigo' => 'LOT-GLOBAL-004',
                'nome' => 'Atendimento ao público',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('lotacoes', [
            'id' => $nova->id,
            'nome' => 'Atendimento ao público',
        ]);
    }

    private function tornarUsuarioOperacional(User $usuario, Escola $escola): void
    {
        $servidor = Servidor::query()->create([
            'user_id' => $usuario->id,
            'nome' => $usuario->name,
            'email' => $usuario->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        Professor::query()->create([
            'user_id' => $usuario->id,
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-LOCAL-'.$usuario->id,
            'nome' => $usuario->name,
            'email' => $usuario->email,
            'ativo' => true,
        ]);
    }
}
