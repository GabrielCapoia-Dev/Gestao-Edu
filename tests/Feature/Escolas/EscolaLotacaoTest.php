<?php

namespace Tests\Feature\Escolas;

use App\Filament\Admin\Resources\Escolas\Pages\ManageEscolas;
use App\Models\Escola;
use App\Models\Lotacao;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EscolaLotacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_codigo_da_lotacao_e_unico_em_toda_a_rede(): void
    {
        $escolaA = $this->criarEscola('Escola A');
        $escolaB = $this->criarEscola('Escola B');

        $escolaA->lotacoes()->createMany([
            ['codigo' => 'LOT-001', 'nome' => 'Docentes'],
            ['codigo' => 'LOT-002', 'nome' => 'Administrativo'],
        ]);
        $this->assertCount(2, $escolaA->lotacoes);
        $this->assertSame($escolaA->id, Lotacao::query()->where('codigo', 'LOT-002')->sole()->escola->id);

        $this->expectException(QueryException::class);

        $escolaB->lotacoes()->create([
            'codigo' => 'LOT-001',
            'nome' => 'Equipe escolar',
        ]);
    }

    public function test_edicao_da_escola_salva_lotacoes_no_mesmo_formulario(): void
    {
        Permission::findOrCreate('Listar Escolas');
        Permission::findOrCreate('Editar Escolas');

        $escola = $this->criarEscola('Escola com lotações');
        $usuario = User::factory()->create([
            'id_escola' => $escola->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Escolas', 'Editar Escolas']);
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
            'matricula' => 'PROF-LOT-001',
            'nome' => $usuario->name,
            'email' => $usuario->email,
            'ativo' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(ManageEscolas::class)
            ->callTableAction('edit', $escola, [
                'nome' => $escola->nome,
                'email' => $escola->email,
                'telefone' => $escola->telefone,
                'setor_id' => $escola->setor_id,
                'logradouro' => null,
                'numero' => null,
                'bairro' => null,
                'cep' => null,
                'cidade' => null,
                'estado' => null,
                'complemento' => null,
                'lotacoes' => [
                    ['codigo' => 'LOT-101', 'nome' => 'Professores'],
                    ['codigo' => 'LOT-102', 'nome' => 'Apoio escolar'],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('lotacoes', [
            'escola_id' => $escola->id,
            'codigo' => 'LOT-101',
            'nome' => 'Professores',
        ]);
        $this->assertDatabaseHas('lotacoes', [
            'escola_id' => $escola->id,
            'codigo' => 'LOT-102',
            'nome' => 'Apoio escolar',
        ]);
    }

    private function criarEscola(string $nome): Escola
    {
        $setorId = DB::table('setor')->insertGetId([
            'nome' => "Setor {$nome}",
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Escola::query()->create([
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'setor_id' => $setorId,
            'ativo' => true,
        ]);
    }
}
