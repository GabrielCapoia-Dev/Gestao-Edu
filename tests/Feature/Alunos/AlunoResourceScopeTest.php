<?php

namespace Tests\Feature\Alunos;

use App\Filament\Admin\Resources\Alunos\Pages\ListAlunos;
use App\Jobs\DeleteAlunosEmMassaJob;
use App\Models\Aluno;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AlunoResourceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_vinculado_a_escola_so_ve_alunos_da_propria_escola(): void
    {
        Permission::findOrCreate('Listar Alunos');

        $escolaA = $this->criarEscola('Escola A');
        $escolaB = $this->criarEscola('Escola B');

        $turmaA = $this->criarTurma($escolaA, 'A');
        $turmaB = $this->criarTurma($escolaB, 'B');

        Aluno::query()->create([
            'nome' => 'Aluno Escola A',
            'cgm' => 'A001',
            'data_nascimento' => '2015-01-10',
            'id_turma' => $turmaA->id,
        ]);

        Aluno::query()->create([
            'nome' => 'Aluno Escola B',
            'cgm' => 'B001',
            'data_nascimento' => '2015-02-10',
            'id_turma' => $turmaB->id,
        ]);

        $usuario = User::factory()->create([
            'id_escola' => $escolaA->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Alunos');

        $this->actingAs($usuario)
            ->get(route('filament.admin.resources.alunos.index'))
            ->assertOk()
            ->assertSee('Aluno Escola A')
            ->assertDontSee('Aluno Escola B');
    }

    public function test_professor_so_ve_alunos_das_suas_turmas_e_respeita_o_contexto_da_turma(): void
    {
        Permission::findOrCreate('Listar Alunos');

        $escola = $this->criarEscola('Escola Professor');
        $outraEscola = $this->criarEscola('Escola Externa');

        $turmaA = $this->criarTurma($escola, 'A');
        $turmaB = $this->criarTurma($escola, 'B');
        $turmaC = $this->criarTurma($outraEscola, 'C');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP001',
            'nome' => 'Matemática',
        ]);

        $usuarioProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuarioProfessor->givePermissionTo('Listar Alunos');

        $professor = Professor::query()->create([
            'user_id' => $usuarioProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF001',
            'nome' => 'Professor 1',
            'email' => 'professor@teste.local',
        ]);

        $turmaA->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $turmaB->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $turmaC->componentes()->attach($componente->id, [
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        Aluno::query()->create([
            'nome' => 'Aluno Turma A',
            'cgm' => 'TA001',
            'data_nascimento' => '2014-03-10',
            'id_turma' => $turmaA->id,
        ]);

        Aluno::query()->create([
            'nome' => 'Aluno Turma B',
            'cgm' => 'TB001',
            'data_nascimento' => '2014-04-10',
            'id_turma' => $turmaB->id,
        ]);

        Aluno::query()->create([
            'nome' => 'Aluno Turma C',
            'cgm' => 'TC001',
            'data_nascimento' => '2014-05-10',
            'id_turma' => $turmaC->id,
        ]);

        $this->actingAs($usuarioProfessor)
            ->get(route('filament.admin.resources.alunos.index'))
            ->assertOk()
            ->assertSee('Aluno Turma A')
            ->assertSee('Aluno Turma B')
            ->assertDontSee('Aluno Turma C');

        $this->actingAs($usuarioProfessor)
            ->get(route('filament.admin.resources.alunos.index', ['turma' => $turmaA->id]))
            ->assertOk()
            ->assertSee('Aluno Turma A')
            ->assertDontSee('Aluno Turma B')
            ->assertDontSee('Aluno Turma C');
    }

    public function test_acesso_de_criacao_respeita_permissao(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Criar Alunos');

        $usuarioSemPermissao = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuarioSemPermissao->givePermissionTo('Listar Alunos');

        Livewire::actingAs($usuarioSemPermissao)
            ->test(ListAlunos::class)
            ->assertActionHidden('create');

        $usuarioComPermissao = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuarioComPermissao->givePermissionTo(['Listar Alunos', 'Criar Alunos']);

        Livewire::actingAs($usuarioComPermissao)
            ->test(ListAlunos::class)
            ->assertActionVisible('create');
    }

    public function test_criacao_de_aluno_pelo_modal_usa_fluxo_de_matricula(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Criar Alunos');

        $escola = $this->criarEscola('Escola Modal Criacao');
        $turma = $this->criarTurma($escola, 'Modal');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Alunos', 'Criar Alunos']);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->callAction('create', [
                'nome' => 'Aluno Criado No Modal',
                'cgm' => 'CGM-MODAL-CRIACAO',
                'data_nascimento' => '2015-01-01',
                'id_escola' => $escola->id,
                'id_serie' => $turma->id_serie,
                'id_turma' => $turma->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('alunos', [
            'nome' => 'Aluno Criado No Modal',
            'cgm' => 'CGM-MODAL-CRIACAO',
            'id_turma' => $turma->id,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);
    }

    public function test_permissao_de_editar_escola_do_aluno_permite_matricular_em_outra_escola(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Criar Alunos');
        Permission::findOrCreate('Editar Escola do Aluno');

        $escolaOrigem = $this->criarEscola('Escola Usuario');
        $escolaDestino = $this->criarEscola('Escola Permitida');
        $turmaDestino = $this->criarTurma($escolaDestino, 'Permitida');

        $usuario = User::factory()->create([
            'id_escola' => $escolaOrigem->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Alunos', 'Criar Alunos', 'Editar Escola do Aluno']);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->callAction('create', [
                'nome' => 'Aluno Outra Escola',
                'cgm' => 'CGM-OUTRA-ESCOLA',
                'data_nascimento' => '2015-02-01',
                'id_escola' => $escolaDestino->id,
                'id_serie' => $turmaDestino->id_serie,
                'id_turma' => $turmaDestino->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('alunos', [
            'nome' => 'Aluno Outra Escola',
            'cgm' => 'CGM-OUTRA-ESCOLA',
            'id_turma' => $turmaDestino->id,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);
    }

    public function test_exclusao_em_massa_de_alunos_e_enfileirada(): void
    {
        Queue::fake();

        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Excluir Alunos');
        Permission::findOrCreate('Excluir Alunos em Massa');

        $escola = $this->criarEscola('Escola Bulk Delete');
        $turma = $this->criarTurma($escola, 'Bulk');

        $alunoA = Aluno::query()->create([
            'nome' => 'Aluno Bulk A',
            'cgm' => 'BULK-A',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);
        $alunoB = Aluno::query()->create([
            'nome' => 'Aluno Bulk B',
            'cgm' => 'BULK-B',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Alunos', 'Excluir Alunos', 'Excluir Alunos em Massa']);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->mountTableBulkAction('delete', [$alunoA, $alunoB])
            ->callMountedTableBulkAction()
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('alunos', ['id' => $alunoA->id]);
        $this->assertDatabaseHas('alunos', ['id' => $alunoB->id]);
        $this->assertDatabaseHas('export_requests', [
            'user_id' => $usuario->id,
            'type' => 'alunos_exclusao_massa',
            'format' => 'processo',
            'status' => 'queued',
        ]);

        Queue::assertPushed(DeleteAlunosEmMassaJob::class, function (DeleteAlunosEmMassaJob $job) use ($alunoA, $alunoB, $usuario): bool {
            return $job->alunoIds === [$alunoA->id, $alunoB->id]
                && $job->usuarioId === $usuario->id
                && filled($job->processRequestId)
                && $job->connection === null
                && $job->queue === config('imports.queue');
        });
    }

    public function test_job_de_exclusao_em_massa_remove_alunos_em_segundo_plano(): void
    {
        Notification::fake();

        Permission::findOrCreate('Excluir Alunos');
        Permission::findOrCreate('Excluir Alunos em Massa');

        $escola = $this->criarEscola('Escola Job Delete');
        $turma = $this->criarTurma($escola, 'Job');

        $alunoA = Aluno::query()->create([
            'nome' => 'Aluno Job A',
            'cgm' => 'JOB-A',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);
        $alunoB = Aluno::query()->create([
            'nome' => 'Aluno Job B',
            'cgm' => 'JOB-B',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Excluir Alunos', 'Excluir Alunos em Massa']);

        app(DeleteAlunosEmMassaJob::class, [
            'alunoIds' => [$alunoA->id, $alunoB->id],
            'usuarioId' => $usuario->id,
        ])->handle();

        $this->assertDatabaseMissing('alunos', ['id' => $alunoA->id]);
        $this->assertDatabaseMissing('alunos', ['id' => $alunoB->id]);

        Notification::assertSentTo($usuario, \App\Notifications\SistemaNotification::class);
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 5)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)) . '@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
    }

    private function criarTurma(Escola $escola, string $sufixo): Turma
    {
        $serie = Serie::query()->create([
            'codigo' => 'SER' . $sufixo,
            'nome' => 'Série ' . $sufixo,
        ]);

        return Turma::query()->create([
            'codigo' => 'TUR' . $sufixo . uniqid(),
            'nome' => $sufixo,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }
}
