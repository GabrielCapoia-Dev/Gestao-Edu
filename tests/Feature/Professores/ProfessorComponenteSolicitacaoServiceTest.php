<?php

namespace Tests\Feature\Professores;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Models\ComponenteCurricular;
use App\Models\Enums\ListaPermissoes;
use App\Models\Escola;
use App\Models\PessoaMatricula;
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
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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

    public function test_occupied_component_can_be_requested_and_approval_replaces_only_its_professor(): void
    {
        [$escola] = $this->criarEscola('Escola Ocupada');
        [$user, $professorSolicitante] = $this->criarProfessor($escola, 'Professor Solicitante');
        [$userAtual, $professorAtual] = $this->criarProfessor($escola, 'Professor Atual');
        $vinculo = $this->criarVinculoVago($escola);
        $vinculo->update(['professor_id' => $professorAtual->id, 'tem_professor' => true]);
        $outroComponente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-OUTRO',
            'nome' => 'Língua Portuguesa',
        ]);
        $outroVinculo = TurmaComponenteProfessor::query()->create([
            'turma_id' => $vinculo->turma_id,
            'componente_curricular_id' => $outroComponente->id,
            'professor_id' => $professorAtual->id,
            'tem_professor' => true,
        ]);
        $service = app(ProfessorComponenteSolicitacaoService::class);
        $opcao = $service->contextosDoProfessor($user)->first()['escolas']->first()['opcoes']->firstWhere('turma.id', $vinculo->turma_id);

        $this->assertSame('ocupado', $opcao['estado']);
        $this->assertSame('PROFESSOR ATUAL', $opcao['professor_atual']);
        $this->assertTrue($service->opcoesDisponiveis($user)->contains('id', $vinculo->id));

        $solicitacao = $service->solicitarComponente($user, $professorSolicitante->id, $vinculo->turma_id, $vinculo->componente_curricular_id);
        [$concorrente, $professorConcorrente] = $this->criarProfessor($escola, 'Professor Concorrente');
        $solicitacaoConcorrente = $service->solicitarComponente($concorrente, $professorConcorrente->id, $vinculo->turma_id, $vinculo->componente_curricular_id);
        $this->assertSame($professorAtual->id, $vinculo->fresh()->professor_id);
        $this->assertSame(ProfessorComponenteSolicitacao::STATUS_PENDENTE, $solicitacao->status);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        $service->aprovar($admin, $solicitacao->id);

        $this->assertSame($professorSolicitante->id, $vinculo->fresh()->professor_id);
        $this->assertSame(ProfessorComponenteSolicitacao::STATUS_REJEITADA, $solicitacaoConcorrente->fresh()->status);
        $this->assertSame($professorAtual->id, $outroVinculo->fresh()->professor_id);
        $this->assertFalse($service->vinculosAtuais($userAtual)->contains('id', $vinculo->id));
        $this->assertTrue($service->vinculosAtuais($user)->contains('id', $vinculo->id));
    }

    public function test_professor_can_request_series_component_without_existing_pivot_and_approval_activates_it(): void
    {
        [$escola] = $this->criarEscola('Escola Grade');
        [$user, $professor] = $this->criarProfessor($escola);
        $serie = Serie::query()->create(['codigo' => 'SER-GRADE', 'nome' => '2º Ano']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-GRADE', 'nome' => 'Ciências']);
        $serie->componentesCurriculares()->attach($componente->id);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-GRADE', 'nome' => 'B', 'turno' => 'manha',
            'id_serie' => $serie->id, 'id_escola' => $escola->id,
        ]);
        $service = app(ProfessorComponenteSolicitacaoService::class);
        $contexto = $service->contextosDoProfessor($user)->first()['escolas']->first();

        $this->assertCount(1, $contexto['opcoes']);
        $this->assertDatabaseMissing('turma_componente_professor', [
            'turma_id' => $turma->id, 'componente_curricular_id' => $componente->id,
        ]);

        $solicitacao = $service->solicitarComponente($user, $professor->id, $turma->id, $componente->id);
        $this->assertSame(ProfessorComponenteSolicitacao::STATUS_PENDENTE, $solicitacao->status);
        $this->assertDatabaseHas('turma_componente_professor', [
            'turma_id' => $turma->id, 'componente_curricular_id' => $componente->id,
            'professor_id' => null, 'tem_professor' => false,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        $service->aprovar($admin, $solicitacao->id);
        $this->assertDatabaseHas('turma_componente_professor', [
            'turma_id' => $turma->id, 'componente_curricular_id' => $componente->id,
            'professor_id' => $professor->id, 'tem_professor' => true,
        ]);
    }

    public function test_contexts_are_grouped_by_matricula_and_school_and_request_is_school_scoped(): void
    {
        [$escolaA] = $this->criarEscola('Escola A');
        [$escolaB] = $this->criarEscola('Escola B');
        [$escolaC] = $this->criarEscola('Escola C');
        [$user, $professorA] = $this->criarProfessor($escolaA);
        $matriculaA = PessoaMatricula::query()->create([
            'servidor_id' => $professorA->servidor_id, 'matricula' => 'MAT-A', 'turno' => 'manha',
        ]);
        $matriculaB = PessoaMatricula::query()->create([
            'servidor_id' => $professorA->servidor_id, 'matricula' => 'MAT-B', 'turno' => 'tarde',
        ]);
        $professorA->update(['professor_matricula_id' => $matriculaA->id]);
        Professor::query()->create([
            'user_id' => $user->id, 'servidor_id' => $professorA->servidor_id,
            'professor_matricula_id' => $matriculaA->id, 'id_escola' => $escolaB->id,
            'matricula' => 'MAT-A', 'turno' => 'manha', 'nome' => $user->name, 'email' => $user->email, 'ativo' => true,
        ]);
        $professorB = Professor::query()->create([
            'user_id' => $user->id, 'servidor_id' => $professorA->servidor_id,
            'professor_matricula_id' => $matriculaB->id, 'id_escola' => $escolaB->id,
            'matricula' => 'MAT-B', 'turno' => 'tarde', 'nome' => $user->name, 'email' => $user->email, 'ativo' => true,
        ]);
        $service = app(ProfessorComponenteSolicitacaoService::class);
        $contextos = $service->contextosDoProfessor($user);

        $this->assertCount(2, $contextos);
        $this->assertSame('MAT-A', $contextos[0]['matricula']);
        $this->assertCount(2, $contextos[0]['escolas']);
        $this->assertSame('MAT-B', $contextos[1]['matricula']);
        $this->assertCount(1, $contextos[1]['escolas']);

        $vinculoFora = $this->criarVinculoVago($escolaC);
        $this->expectException(AuthorizationException::class);
        $service->solicitarComponente($user, $professorB->id, $vinculoFora->turma_id, $vinculoFora->componente_curricular_id);
    }

    public function test_professor_listing_counts_pending_requests_and_clears_indicator_after_approval(): void
    {
        [$escola] = $this->criarEscola('Escola Avisos');
        [$user, $professor] = $this->criarProfessor($escola);
        $vinculo = $this->criarVinculoVago($escola);
        $service = app(ProfessorComponenteSolicitacaoService::class);
        $solicitacao = $service->solicitarComponente($user, $professor->id, $vinculo->turma_id, $vinculo->componente_curricular_id);

        [$admin] = $this->criarProfessor($escola, 'Administrador de Teste');
        $admin->update(['email_approved' => true, 'email_verified_at' => now()]);
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        $admin->givePermissionTo(Permission::findOrCreate(ListaPermissoes::ListarPessoas->label(), 'web'));

        $pagina = Livewire::actingAs($admin)->test(ManageServidores::class)->assertOk();
        $registro = $pagina->instance()
            ->getTableRecords()
            ->firstWhere('id', $professor->servidor_id);
        $this->assertSame(1, (int) $registro->solicitacoes_pendentes_count);
        $pagina
            ->filterTable('solicitacoes_pendentes', true)
            ->assertCanSeeTableRecords([$professor->servidor]);

        $service->aprovar($admin, $solicitacao->id);

        $pagina = Livewire::actingAs($admin)->test(ManageServidores::class)->assertOk();
        $registro = $pagina->instance()
            ->getTableRecords()
            ->firstWhere('id', $professor->servidor_id);
        $this->assertSame(0, (int) $registro->solicitacoes_pendentes_count);
        $pagina
            ->filterTable('solicitacoes_pendentes', true)
            ->assertCanNotSeeTableRecords([$professor->servidor]);
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
