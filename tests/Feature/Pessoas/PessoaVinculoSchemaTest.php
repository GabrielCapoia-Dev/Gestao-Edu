<?php

namespace Tests\Feature\Pessoas;

use App\Models\Enums\SetorContexto;
use App\Models\Setor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PessoaVinculoSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_pessoa_vinculos_possui_colunas_esperadas(): void
    {
        $this->assertTrue(Schema::hasColumn('servidores', 'cpf'));
        $this->assertTrue(Schema::hasColumn('setor', 'contexto'));
        $this->assertTrue(Schema::hasColumn('setor', 'exige_vinculo_escola'));
        $this->assertTrue(Schema::hasColumn('servidor_funcao_administrativa', 'matricula'));
        $this->assertTrue(Schema::hasColumn('professores', 'servidor_funcao_administrativa_id'));
    }

    public function test_setor_contexto_define_exigencia_de_escola(): void
    {
        $setor = Setor::query()->create([
            'nome' => 'CMEI Exemplo',
            'contexto' => SetorContexto::Cmei->value,
            'status' => 'ativo',
            'ativo' => true,
        ]);

        $this->assertTrue($setor->fresh()->exigeVinculoEscola());
    }
}