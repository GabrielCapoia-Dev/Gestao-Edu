<?php

namespace Tests\Feature\Escolas;

use App\Models\Escola;
use App\Services\EscolaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EscolaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_atualizacao_edita_escola_em_linha_sem_criar_historico(): void
    {
        $setorId = $this->criarSetor('Setor Teste');

        $escola = Escola::query()->create([
            'codigo' => 'ESC043',
            'nome' => 'CEI - Anjo da Guarda',
            'email' => 'cei.anjodaguarda@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        $userId = $this->criarUsuario($escola->id);

        $escolaAtualizada = app(EscolaService::class)->atualizarEmLinha($escola, [
            'nome' => 'CEI - Anjo da Guarda',
            'email' => 'cei.anjodaguarda@edu.umuarama.pr.gov.br',
            'telefone' => null,
            'setor_id' => $setorId,
            'logradouro' => 'Rua Teste',
            'numero' => '123',
            'bairro' => 'Centro',
            'cep' => '87500-000',
            'cidade' => 'Umuarama',
            'estado' => 'PR',
            'complemento' => null,
        ]);

        $this->assertSame($escola->id, $escolaAtualizada->id);
        $this->assertTrue($escolaAtualizada->ativo);
        $this->assertSame('ESC043', $escolaAtualizada->codigo);
        $this->assertSame($setorId, $escolaAtualizada->setor_id);
        $this->assertSame('Rua Teste', $escolaAtualizada->logradouro);
        $this->assertNull($escolaAtualizada->registro_anterior_id);
        $this->assertSame(1, Escola::query()->where('codigo', 'ESC043')->count());
        $this->assertSame($escola->id, (int) DB::table('users')->where('id', $userId)->value('id_escola'));
    }

    public function test_falha_ao_atualizar_em_linha_preserva_escola_atual(): void
    {
        Escola::query()->create([
            'codigo' => 'ESC999',
            'nome' => 'Escola Com Nome Reservado',
            'email' => 'reservado@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        $escola = Escola::query()->create([
            'codigo' => 'ESC043',
            'nome' => 'CEI - Anjo da Guarda',
            'email' => 'cei.anjodaguarda@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        DB::statement('CREATE UNIQUE INDEX escolas_nome_unique ON escolas (nome)');

        try {
            app(EscolaService::class)->atualizarEmLinha($escola, [
                'nome' => 'Escola Com Nome Reservado',
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

            $this->fail('A restricao unica de nome deveria bloquear a atualizacao.');
        } catch (QueryException) {
            $escolaAtual = $escola->fresh();

            $this->assertTrue($escolaAtual->ativo);
            $this->assertSame('CEI - Anjo da Guarda', $escolaAtual->nome);
            $this->assertSame('cei.anjodaguarda@edu.umuarama.pr.gov.br', $escolaAtual->email);
            $this->assertSame(1, Escola::query()->where('codigo', 'ESC043')->count());
        }
    }

    public function test_migration_de_saneamento_move_vinculos_historicos_para_escola_ativa(): void
    {
        $setorId = $this->criarSetor('Setor Escola');

        $escolaHistorica = Escola::query()->create([
            'codigo' => 'ESC043',
            'nome' => 'CEI - Anjo da Guarda',
            'email' => 'historico@edu.umuarama.pr.gov.br',
            'setor_id' => $setorId,
            'ativo' => false,
        ]);

        $escolaAtiva = Escola::query()->create([
            'codigo' => 'ESC043',
            'nome' => 'CEI - Anjo da Guarda Atualizada',
            'email' => 'ativa@edu.umuarama.pr.gov.br',
            'setor_id' => $setorId,
            'ativo' => true,
            'registro_anterior_id' => $escolaHistorica->id,
        ]);

        $userId = $this->criarUsuario($escolaHistorica->id);
        DB::table('escola_user')->insert([
            ['user_id' => $userId, 'escola_id' => $escolaHistorica->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $userId, 'escola_id' => $escolaAtiva->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $serieId = DB::table('series')->insertGetId([
            'codigo' => 'SER001',
            'nome' => 'Infantil I',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $turmaId = DB::table('turmas')->insertGetId([
            'codigo' => 'TUR001',
            'nome' => 'Infantil I A',
            'turno' => 'manha',
            'id_serie' => $serieId,
            'id_escola' => $escolaHistorica->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $professorId = DB::table('professores')->insertGetId([
            'id_escola' => $escolaHistorica->id,
            'matricula' => 'MAT001',
            'nome' => 'Professor Teste',
            'email' => 'professor@edu.umuarama.pr.gov.br',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inventarioId = DB::table('inventarios')->insertGetId([
            'escola_id' => $escolaHistorica->id,
            'setor_id' => $setorId,
            'nome' => 'Inventario Historico',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inventarioPedidoId = DB::table('inventario_pedidos')->insertGetId([
            'inventario_id' => $inventarioId,
            'escola_id' => $escolaHistorica->id,
            'status' => 'pendente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $avaliacaoId = DB::table('avaliacoes')->insertGetId([
            'nome' => 'Avaliacao Teste',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
            'status' => 'ativa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('avaliacao_escola')->insert([
            ['avaliacao_id' => $avaliacaoId, 'escola_id' => $escolaHistorica->id, 'created_at' => now(), 'updated_at' => now()],
            ['avaliacao_id' => $avaliacaoId, 'escola_id' => $escolaAtiva->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $avaliacaoExportacaoId = DB::table('avaliacao_exportacoes')->insertGetId([
            'avaliacao_id' => $avaliacaoId,
            'escola_id' => $escolaHistorica->id,
            'escopo' => 'escola',
            'formato' => 'pdf',
            'quantidade_alunos' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_06_08_000002_sanitize_escola_history_references.php');
        $migration->up();

        $this->assertSame($escolaAtiva->id, (int) DB::table('users')->where('id', $userId)->value('id_escola'));
        $this->assertSame($escolaAtiva->id, (int) DB::table('turmas')->where('id', $turmaId)->value('id_escola'));
        $this->assertSame($escolaAtiva->id, (int) DB::table('professores')->where('id', $professorId)->value('id_escola'));
        $this->assertSame($escolaAtiva->id, (int) DB::table('inventarios')->where('id', $inventarioId)->value('escola_id'));
        $this->assertSame($escolaAtiva->id, (int) DB::table('inventario_pedidos')->where('id', $inventarioPedidoId)->value('escola_id'));
        $this->assertSame($escolaAtiva->id, (int) DB::table('avaliacao_exportacoes')->where('id', $avaliacaoExportacaoId)->value('escola_id'));
        $this->assertFalse((bool) $escolaHistorica->fresh()->ativo);

        $this->assertSame(0, DB::table('escola_user')->where('escola_id', $escolaHistorica->id)->count());
        $this->assertSame(1, DB::table('escola_user')->where('user_id', $userId)->where('escola_id', $escolaAtiva->id)->count());

        $this->assertSame(0, DB::table('avaliacao_escola')->where('escola_id', $escolaHistorica->id)->count());
        $this->assertSame(1, DB::table('avaliacao_escola')->where('avaliacao_id', $avaliacaoId)->where('escola_id', $escolaAtiva->id)->count());
    }

    private function criarSetor(string $nome): int
    {
        return DB::table('setor')->insertGetId([
            'nome' => $nome,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function criarUsuario(int $escolaId): int
    {
        return DB::table('users')->insertGetId([
            'id_escola' => $escolaId,
            'name' => 'Usuario Teste',
            'email' => 'usuario.'.uniqid().'@edu.umuarama.pr.gov.br',
            'email_approved' => true,
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
