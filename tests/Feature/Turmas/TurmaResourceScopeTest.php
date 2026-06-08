<?php

namespace Tests\Feature\Turmas;

use App\Filament\Admin\Resources\Turmas\Pages\ManageTurmas;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Services\TurmaService;
use App\Services\UserService;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TurmaResourceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_vinculado_a_escola_so_ve_turmas_da_propria_escola(): void
    {
        Permission::findOrCreate('Listar Turmas');

        $escolaA = $this->criarEscola('Escola A');
        $escolaB = $this->criarEscola('Escola B');

        $turmaA = $this->criarTurma($escolaA, 'Turma A');
        $turmaB = $this->criarTurma($escolaB, 'Turma B');

        $usuario = User::factory()->create([
            'id_escola' => $escolaA->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Turmas');

        $turmasVisiveis = app(UserService::class)
            ->aplicarFiltroTurmasDoUsuario(Turma::query(), $usuario)
            ->pluck('id')
            ->all();

        $this->assertContains($turmaA->id, $turmasVisiveis);
        $this->assertNotContains($turmaB->id, $turmasVisiveis);
    }

    public function test_professor_so_ve_as_turmas_que_esta_vinculado(): void
    {
        Permission::findOrCreate('Listar Turmas');

        $escolaA = $this->criarEscola('Escola Professor A');
        $escolaB = $this->criarEscola('Escola Professor B');
        $escolaC = $this->criarEscola('Escola Externa');

        $turmaA = $this->criarTurma($escolaA, 'Turma Professor A');
        $turmaB = $this->criarTurma($escolaB, 'Turma Professor B');
        $turmaC = $this->criarTurma($escolaC, 'Turma Externa');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-TURMA-01',
            'nome' => 'Matematica',
        ]);

        $usuarioProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuarioProfessor->givePermissionTo('Listar Turmas');

        $professorA = Professor::query()->create([
            'user_id' => $usuarioProfessor->id,
            'id_escola' => $escolaA->id,
            'matricula' => 'PROF-A',
            'nome' => 'Professor A',
            'email' => 'professor@edu.umuarama.pr.gov.br',
        ]);

        $professorB = Professor::query()->create([
            'user_id' => $usuarioProfessor->id,
            'id_escola' => $escolaB->id,
            'matricula' => 'PROF-B',
            'nome' => 'Professor B',
            'email' => 'professor@edu.umuarama.pr.gov.br',
        ]);

        $turmaA->componentes()->attach($componente->id, [
            'professor_id' => $professorA->id,
            'tem_professor' => true,
        ]);

        $turmaB->componentes()->attach($componente->id, [
            'professor_id' => $professorB->id,
            'tem_professor' => true,
        ]);

        $turmaC->componentes()->attach($componente->id, [
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $turmasVisiveis = app(UserService::class)
            ->aplicarFiltroTurmasDoUsuario(Turma::query(), $usuarioProfessor)
            ->pluck('id')
            ->all();

        $this->assertContains($turmaA->id, $turmasVisiveis);
        $this->assertContains($turmaB->id, $turmasVisiveis);
        $this->assertNotContains($turmaC->id, $turmasVisiveis);
    }

    public function test_edicao_atualiza_professor_do_componente_da_turma(): void
    {
        Permission::findOrCreate('Listar Turmas');
        Permission::findOrCreate('Editar Turmas');
        Permission::findOrCreate('Editar Dados da Turma');
        Permission::findOrCreate('Editar Escola da Turma');

        $escola = $this->criarEscola('Escola Edicao');
        $serie = Serie::query()->create([
            'codigo' => 'SER-EDIT',
            'nome' => 'Serie Edicao',
        ]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-EDIT',
            'nome' => 'Matematica',
        ]);
        $serie->componentesCurriculares()->sync([$componente->id]);

        $turma = Turma::query()->create([
            'codigo' => 'TUR-EDIT',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);

        $professorAntigo = $this->criarProfessor($escola, 'PROF-ANT', 'Professor Antigo');
        $professorNovo = $this->criarProfessor($escola, 'PROF-NOVO', 'Professor Novo');

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professorAntigo->id,
            'tem_professor' => true,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo([
            'Listar Turmas',
            'Editar Turmas',
            'Editar Dados da Turma',
            'Editar Escola da Turma',
        ]);

        Livewire::actingAs($usuario)
            ->test(ManageTurmas::class)
            ->callTableAction('edit', $turma, [
                'id_escola' => $escola->id,
                'id_serie' => $serie->id,
                'nome' => $turma->nome,
                'turno' => $turma->turno,
                'codigo' => $turma->codigo,
                'componentes' => [
                    [
                        'componente_curricular_id' => $componente->id,
                        'componente_nome' => $componente->nome,
                        'professor_id' => $professorNovo->id,
                        'tem_professor' => false,
                    ],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('turma_componente_professor', [
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professorNovo->id,
            'tem_professor' => true,
        ]);

        $this->assertDatabaseMissing('turma_componente_professor', [
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professorAntigo->id,
        ]);
    }

    public function test_edicao_marca_checkbox_quando_componente_esta_sem_professor(): void
    {
        Permission::findOrCreate('Listar Turmas');
        Permission::findOrCreate('Editar Turmas');
        Permission::findOrCreate('Editar Dados da Turma');
        Permission::findOrCreate('Editar Escola da Turma');

        $escola = $this->criarEscola('Escola Sem Professor');
        $serie = Serie::query()->create([
            'codigo' => 'SER-SEM-PROF',
            'nome' => 'Serie Sem Professor',
        ]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-SEM-PROF',
            'nome' => 'Arte',
        ]);
        $serie->componentesCurriculares()->sync([$componente->id]);

        $turma = Turma::query()->create([
            'codigo' => 'TUR-SEM-PROF',
            'nome' => 'B',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $componentes = TurmaService::componentesFormulario($turma);

        $this->assertTrue($componentes[0]['tem_professor']);
        $this->assertNull($componentes[0]['professor_id']);
    }

    public function test_edicao_com_checkbox_sem_professor_remove_professor_do_componente(): void
    {
        Permission::findOrCreate('Listar Turmas');
        Permission::findOrCreate('Editar Turmas');
        Permission::findOrCreate('Editar Dados da Turma');
        Permission::findOrCreate('Editar Escola da Turma');

        $escola = $this->criarEscola('Escola Remove Professor');
        $serie = Serie::query()->create([
            'codigo' => 'SER-REMOVE',
            'nome' => 'Serie Remove',
        ]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-REMOVE',
            'nome' => 'Musica',
        ]);
        $serie->componentesCurriculares()->sync([$componente->id]);

        $turma = Turma::query()->create([
            'codigo' => 'TUR-REMOVE',
            'nome' => 'C',
            'turno' => 'tarde',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $professor = $this->criarProfessor($escola, 'PROF-REMOVE', 'Professor Remove');

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo([
            'Listar Turmas',
            'Editar Turmas',
            'Editar Dados da Turma',
            'Editar Escola da Turma',
        ]);

        Livewire::actingAs($usuario)
            ->test(ManageTurmas::class)
            ->callTableAction('edit', $turma, [
                'id_escola' => $escola->id,
                'id_serie' => $serie->id,
                'nome' => $turma->nome,
                'turno' => $turma->turno,
                'codigo' => $turma->codigo,
                'componentes' => [
                    [
                        'componente_curricular_id' => $componente->id,
                        'componente_nome' => $componente->nome,
                        'professor_id' => $professor->id,
                        'tem_professor' => true,
                    ],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('turma_componente_professor', [
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => null,
            'tem_professor' => false,
        ]);
    }

    public function test_edicao_exibe_turno_matricula_e_nome_no_select_de_professor(): void
    {
        Permission::findOrCreate('Listar Turmas');
        Permission::findOrCreate('Editar Turmas');
        Permission::findOrCreate('Editar Dados da Turma');
        Permission::findOrCreate('Editar Escola da Turma');

        $escola = $this->criarEscola('Escola Labels');
        $professor = $this->criarProfessor($escola, 'MAT-001', 'Professora Label', 'tarde');

        $this->assertSame(
            'Tarde - MAT-001 - Professora Label',
            TurmaService::professoresOptionsParaTurma($escola->id)[$professor->id] ?? null,
        );
    }

    public function test_edicao_exibe_nome_da_escola_vinculada_mesmo_fora_do_filtro_de_ativas(): void
    {
        Permission::findOrCreate('Listar Turmas');
        Permission::findOrCreate('Editar Turmas');
        Permission::findOrCreate('Editar Dados da Turma');
        Permission::findOrCreate('Editar Escola da Turma');

        $escola = $this->criarEscola('Escola Historica');
        $escola->update(['ativo' => false]);

        $turma = $this->criarTurma($escola, 'Turma Historica');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo([
            'Listar Turmas',
            'Editar Turmas',
            'Editar Dados da Turma',
            'Editar Escola da Turma',
        ]);

        Livewire::actingAs($usuario)
            ->test(ManageTurmas::class)
            ->mountTableAction('edit', $turma)
            ->assertSchemaComponentExists('id_escola', null, function ($component) use ($escola): bool {
                $this->assertInstanceOf(Select::class, $component);
                $this->assertSame($escola->nome, $component->getOptionLabel(false));

                return true;
            });
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 5)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
    }

    private function criarTurma(Escola $escola, string $nome): Turma
    {
        $serie = Serie::query()->create([
            'codigo' => 'SER'.strtoupper(substr(md5($nome), 0, 4)),
            'nome' => 'Serie '.$nome,
        ]);

        return Turma::query()->create([
            'codigo' => 'TUR'.strtoupper(substr(md5($nome.microtime()), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarProfessor(Escola $escola, string $matricula, string $nome, string $turno = 'manha'): Professor
    {
        return Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => $matricula,
            'turno' => $turno,
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@edu.umuarama.pr.gov.br',
        ]);
    }
}
