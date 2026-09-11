<?php

namespace Tests\Feature\Professores;

use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\ProfessorComponenteSolicitacao;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\ProfessorComponenteSolicitacaoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessorComponenteSolicitacaoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_requests_empty_component_and_link_only_becomes_valid_after_approval(): void
    {
        [$escola, $setor] = $this->criarEscola('Escola Professor');
        [$user, $professor] = $this->criarProfessor($escola);
        $vinculo = $this->criarVinculoVago($escola);
        $service = app(ProfessorComponenteSolicitacaoService::class);

        $this->assertTrue($service->opcoesDisponiveis($user)->contains('id', $vinculo->id));

        $solicitacao = $service->solicitar($user, $vinculo->id);

        $this->assertSame(ProfessorComponenteSolicitacao::STATUS_PENDENTE, $solicitacao->status);
        $this->assertDatabaseHas('turma_componente_professor', [
            'id' => $vinculo->id,
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        $service->aprovar($admin, $solicitacao->id);

        $this->assertDatabaseHas('professor_componente_solicitacoes', [
            'id' => $solicitacao->id,
            'status' => ProfessorComponenteSolicitacao::STATUS_APROVADA,
            'analisado_por_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('turma_componente_professor', [
            'id' => $vinculo->id,
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);
        $this->assertTrue($service->vinculosAtuais($user)->contains('id', $vinculo->id));
    }

    public function test_professor_cannot_request_component_from_another_school(): void
    {
        [$escolaProfessor] = $this->criarEscola('Escola Permitida');
        [$outraEscola] = $this->criarEscola('Escola Bloqueada');
        [$user] = $this->criarProfessor($escolaProfessor);
        $vinculoOutraEscola = $this->criarVinculoVago($outraEscola);
        $service = app(ProfessorComponenteSolicitacaoService::class);

        $this->assertFalse($service->opcoesDisponiveis($user)->contains('id', $vinculoOutraEscola->id));

        $this->expectException(AuthorizationException::class);
        $service->solicitar($user, $vinculoOutraEscola->id);
    }

    public function test_component_with_professor_is_not_available_for_request(): void
    {
        [$escola] = $this->criarEscola('Escola Ocupada');
        [$user] = $this->criarProfessor($escola, 'Professor Solicitante');
        [, $professorAtual] = $this->criarProfessor($escola, 'Professor Atual');
        $vinculo = $this->criarVinculoVago($escola);
        $vinculo->update(['professor_id' => $professorAtual->id, 'tem_professor' => true]);

        $this->assertFalse(
            app(ProfessorComponenteSolicitacaoService::class)
                ->opcoesDisponiveis($user)
                ->contains('id', $vinculo->id)
        );
    }

    /** @return array{Escola, Setor} */
    private function criarEscola(string $nome): array
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor '.$nome,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => false,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);

        $escola = Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);

        return [$escola, $setor];
    }

    /** @return array{User, Professor} */
    private function criarProfessor(Escola $escola, string $nome = 'Professor Teste'): array
    {
        $user = User::factory()->create(['name' => $nome]);
        $servidor = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $nome,
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
            'id_escola' => $escola->id,
        ]);
        $professor = Professor::query()->create([
            'user_id' => $user->id,
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => fake()->unique()->numerify('PROF####'),
            'turno' => 'manha',
            'nome' => $nome,
            'email' => $user->email,
            'ativo' => true,
        ]);

        return [$user, $professor];
    }

    private function criarVinculoVago(Escola $escola): TurmaComponenteProfessor
    {
        $serie = Serie::query()->create([
            'codigo' => fake()->unique()->bothify('SER-####'),
            'nome' => '1º Ano',
        ]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => fake()->unique()->bothify('COMP-####'),
            'nome' => 'Matemática',
        ]);
        $turma = Turma::query()->create([
            'codigo' => fake()->unique()->bothify('TUR-####'),
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);

        return TurmaComponenteProfessor::query()->create([
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => null,
            'tem_professor' => false,
        ]);
    }
}
