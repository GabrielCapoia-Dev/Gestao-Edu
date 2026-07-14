<?php

namespace Tests\Feature\Pessoas;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorFuncaoTurma;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\User;
use App\Services\ServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServidorExclusaoEquipeGestoraTest extends TestCase
{
    use RefreshDatabase;

    public function test_bloqueia_exclusao_de_gestor_ativo_e_preserva_vinculos(): void
    {
        [$pessoa, $vinculo, $vinculoTurma, $user] = $this->criarPessoaComHistoricoGestor(
            ServidorFuncaoAdministrativa::STATUS_ATIVO,
        );

        try {
            app(ServidorService::class)->excluirPessoa($pessoa);
            $this->fail('A exclusão deveria ser bloqueada para uma Pessoa da Equipe Gestora.');
        } catch (ValidationException $exception) {
            $mensagem = collect($exception->errors())->flatten()->implode(' ');

            $this->assertStringContainsString('histórico na Equipe Gestora', $mensagem);
            $this->assertStringContainsString('Inative a pessoa ou converta o cargo', $mensagem);
        }

        $this->assertDatabaseHas('servidores', ['id' => $pessoa->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('servidor_funcao_administrativa', ['id' => $vinculo->id]);
        $this->assertDatabaseHas('servidor_funcao_turma', ['id' => $vinculoTurma->id]);
        $this->assertTrue($user->fresh()->hasRole('Equipe Gestora'));
        $this->assertTrue($user->fresh()->servidores()->whereKey($pessoa->id)->exists());
    }

    public function test_bloqueia_exclusao_mesmo_quando_o_vinculo_gestor_esta_encerrado(): void
    {
        [$pessoa, $vinculo, $vinculoTurma] = $this->criarPessoaComHistoricoGestor(
            ServidorFuncaoAdministrativa::STATUS_INATIVO,
        );

        $resultado = app(ServidorService::class)->excluirPessoasEmMassa([$pessoa]);

        $this->assertSame(0, $resultado['excluidos']);
        $this->assertCount(1, $resultado['bloqueados']);
        $this->assertDatabaseHas('servidores', ['id' => $pessoa->id]);
        $this->assertDatabaseHas('servidor_funcao_administrativa', ['id' => $vinculo->id]);
        $this->assertDatabaseHas('servidor_funcao_turma', ['id' => $vinculoTurma->id]);
    }

    /** @return array{Servidor, ServidorFuncaoAdministrativa, ServidorFuncaoTurma, User} */
    private function criarPessoaComHistoricoGestor(string $status): array
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor Gestão',
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);
        $escola = Escola::query()->create([
            'codigo' => 'ESC-GESTAO',
            'nome' => 'Escola Gestão',
            'setor_id' => $setor->id,
            'ativo' => true,
        ]);
        $serie = Serie::query()->create([
            'codigo' => 'SER-GESTAO',
            'nome' => 'Série Gestão',
        ]);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-GESTAO',
            'nome' => 'Turma Gestão',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $user = User::factory()->create([
            'id_escola' => $escola->id,
        ]);
        $user->assignRole(Role::query()->create([
            'name' => 'Equipe Gestora',
            'guard_name' => 'web',
        ]));
        $pessoa = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => 'Gestora com Histórico',
            'status' => Servidor::STATUS_ATIVO,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
        ]);
        $vinculo = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::coordenacaoPadrao()->id,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'status' => $status,
            'origem' => 'teste',
            'portaria' => 'PORT-EXCLUSAO/2026',
            'principal' => false,
            'data_inicio' => '2026-01-01',
            'data_fim' => $status === ServidorFuncaoAdministrativa::STATUS_INATIVO ? '2026-06-30' : null,
        ]);
        $vinculoTurma = ServidorFuncaoTurma::query()->create([
            'servidor_funcao_administrativa_id' => $vinculo->id,
            'turma_id' => $turma->id,
            'principal' => false,
            'status' => $status,
            'data_inicio' => '2026-01-01',
            'data_fim' => $status === ServidorFuncaoAdministrativa::STATUS_INATIVO ? '2026-06-30' : null,
        ]);

        return [$pessoa, $vinculo, $vinculoTurma, $user];
    }
}
