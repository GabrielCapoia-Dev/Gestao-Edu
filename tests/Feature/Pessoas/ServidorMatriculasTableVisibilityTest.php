<?php

namespace Tests\Feature\Pessoas;

use App\Livewire\Pessoas\ServidoresTable;
use App\Models\FuncaoAdministrativa;
use App\Models\PessoaMatricula;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServidorMatriculasTableVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_visualiza_matriculas_funcionais_na_lista_de_servidores(): void
    {
        $usuarioRh = User::factory()->create();
        $servidor = Servidor::query()->create([
            'user_id' => $usuarioRh->id,
            'nome' => 'Servidor RH',
            'email' => 'rh.servidor@example.test',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        PessoaMatricula::query()->create([
            'servidor_id' => $servidor->id,
            'matricula' => 'RH-1080734',
            'turno' => 'manha',
        ]);

        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::rhPadrao()->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'pessoas',
        ]);

        $this->actingAs($usuarioRh);

        $label = app(ServidoresTable::class)
            ->matriculasLabel($servidor->load(['matriculas', 'professores']));

        $this->assertSame('RH-1080734 (Manhã)', $label);
    }
}
