<?php

namespace Tests\Feature\Escolas;

use App\Models\Escola;
use App\Services\EscolaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EscolaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_atualizacao_com_historico_cria_nova_versao_da_escola(): void
    {
        $setorId = DB::table('setor')->insertGetId([
            'nome' => 'Setor Teste',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $escola = Escola::query()->create([
            'codigo' => 'ESC043',
            'nome' => 'CEI - Anjo da Guarda',
            'email' => 'cei.anjodaguarda@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        $novaVersao = app(EscolaService::class)->atualizarComHistorico($escola, [
            'nome' => 'CEI - Anjo da Guarda',
            'email' => 'cei.anjodaguarda@edu.umuarama.pr.gov.br',
            'telefone' => null,
            'setor_id' => $setorId,
            'logradouro' => null,
            'numero' => null,
            'bairro' => null,
            'cep' => null,
            'cidade' => null,
            'estado' => null,
            'complemento' => null,
        ]);

        $this->assertFalse($escola->fresh()->ativo);
        $this->assertTrue($novaVersao->ativo);
        $this->assertSame('ESC043', $novaVersao->codigo);
        $this->assertSame('cei.anjodaguarda@edu.umuarama.pr.gov.br', $novaVersao->email);
        $this->assertSame($setorId, $novaVersao->setor_id);
        $this->assertSame($escola->id, $novaVersao->registro_anterior_id);
        $this->assertSame(2, Escola::query()->where('codigo', 'ESC043')->count());
    }

    public function test_falha_ao_criar_nova_versao_nao_desativa_escola_atual(): void
    {
        $escola = Escola::query()->create([
            'codigo' => 'ESC043',
            'nome' => 'CEI - Anjo da Guarda',
            'email' => 'cei.anjodaguarda@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        DB::statement('CREATE UNIQUE INDEX escolas_nome_unique ON escolas (nome)');

        try {
            app(EscolaService::class)->atualizarComHistorico($escola, [
                'nome' => 'CEI - Anjo da Guarda',
                'email' => 'cei.anjodaguarda.novo@edu.umuarama.pr.gov.br',
                'telefone' => null,
                'setor_id' => null,
                'logradouro' => null,
                'numero' => null,
                'bairro' => null,
                'cep' => null,
                'cidade' => null,
                'estado' => null,
                'complemento' => null,
            ]);

            $this->fail('A restricao unica de nome deveria bloquear a nova versao.');
        } catch (QueryException) {
            $this->assertTrue($escola->fresh()->ativo);
            $this->assertSame(1, Escola::query()->where('codigo', 'ESC043')->count());
        }
    }
}
