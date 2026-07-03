<?php

namespace Tests\Feature\Alunos;

use App\Filament\Admin\Resources\Alunos\Pages\ListAlunos;
use App\Jobs\DeleteAlunosEmMassaJob;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\PeriodoAvaliacao;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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

    public function test_modal_consulta_cgm_automaticamente_e_prefill_dados_encontrados(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Criar Alunos');

        $escola = $this->criarEscola('Escola CGM Automatico');
        $turma = $this->criarTurma($escola, 'CGM Auto');

        $alunoExistente = Aluno::query()->create([
            'nome' => 'Aluno Encontrado Pelo CGM',
            'cgm' => 'CGM-AUTO-001',
            'data_nascimento' => '2014-03-05',
            'data_matricula' => '2024-02-01',
            'sexo' => 'F',
            'id_turma' => $turma->id,
            'status' => Aluno::STATUS_TRANSFERIDO,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Alunos', 'Criar Alunos']);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->mountAction('create')
            ->setActionData([
                'cgm' => ' CGM-AUTO-001 ',
            ])
            ->assertSchemaStateSet(function (array $state) use ($alunoExistente): array {
                $this->assertSame('CGM-AUTO-001', $state['cgm'] ?? null);
                $this->assertTrue((bool) ($state['cgm_consultado'] ?? false));
                $this->assertSame($alunoExistente->id, $state['cgm_encontrado_aluno_id'] ?? null);
                $this->assertSame('Aluno Encontrado Pelo CGM', $state['nome'] ?? null);
                $this->assertSame('F', $state['sexo'] ?? null);

                return [];
            });
    }

    public function test_modal_usa_datepicker_nativo_para_permitir_digitacao_e_colagem(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Criar Alunos');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Alunos', 'Criar Alunos']);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->mountAction('create')
            ->assertSchemaComponentExists('data_nascimento', null, function ($component): bool {
                $this->assertInstanceOf(DatePicker::class, $component);
                $this->assertTrue($component->isNative());

                return true;
            })
            ->assertSchemaComponentExists('data_matricula', null, function ($component): bool {
                $this->assertInstanceOf(DatePicker::class, $component);
                $this->assertTrue($component->isNative());

                return true;
            });
    }

    public function test_modal_trava_escola_quando_usuario_tem_um_unico_vinculo(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Criar Alunos');

        $escolaVinculada = $this->criarEscola('Escola Vinculada Unica');
        $outraEscola = $this->criarEscola('Escola Fora Do Vinculo');
        $this->criarTurma($escolaVinculada, 'Vinculada');
        $this->criarTurma($outraEscola, 'Fora');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->escolas()->attach($escolaVinculada->id);
        $usuario->givePermissionTo(['Listar Alunos', 'Criar Alunos']);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->mountAction('create')
            ->setActionData([
                'cgm' => 'CGM-ESCOLA-UNICA',
            ])
            ->assertSchemaStateSet([
                'id_escola' => $escolaVinculada->id,
            ])
            ->assertSchemaComponentExists('id_escola', null, function ($component) use ($escolaVinculada, $outraEscola): bool {
                $this->assertInstanceOf(Select::class, $component);
                $this->assertSame([
                    $escolaVinculada->id => $escolaVinculada->nome,
                ], $component->getOptions());
                $this->assertArrayNotHasKey($outraEscola->id, $component->getOptions());
                $this->assertTrue($component->isDisabled());

                return true;
            });
    }

    public function test_modal_restringe_sem_travar_quando_usuario_tem_multiplas_escolas(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Criar Alunos');

        $escolaA = $this->criarEscola('Escola Vinculada A');
        $escolaB = $this->criarEscola('Escola Vinculada B');
        $escolaFora = $this->criarEscola('Escola Fora Dos Vinculos');
        $this->criarTurma($escolaA, 'A');
        $this->criarTurma($escolaB, 'B');
        $this->criarTurma($escolaFora, 'Fora');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->escolas()->attach([$escolaA->id, $escolaB->id]);
        $usuario->givePermissionTo(['Listar Alunos', 'Criar Alunos']);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->mountAction('create')
            ->setActionData([
                'cgm' => 'CGM-MULTIPLAS-ESCOLAS',
            ])
            ->assertSchemaComponentExists('id_escola', null, function ($component) use ($escolaA, $escolaB, $escolaFora): bool {
                $this->assertInstanceOf(Select::class, $component);
                $this->assertSame([
                    $escolaA->id => $escolaA->nome,
                    $escolaB->id => $escolaB->nome,
                ], $component->getOptions());
                $this->assertArrayNotHasKey($escolaFora->id, $component->getOptions());
                $this->assertFalse($component->isDisabled());

                return true;
            });
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

    public function test_listagem_exibe_tipo_de_vinculo_sem_action_manual_de_copiar_cgm(): void
    {
        Permission::findOrCreate('Listar Alunos');

        $escola = $this->criarEscola('Escola Copia CGM');
        $serie = Serie::query()->create([
            'codigo' => 'SER-CGM',
            'nome' => 'Serie CGM',
        ]);
        $turmaPrincipal = $this->criarTurma($escola, 'Principal', $serie, 'manha');
        $turmaContraTurno = $this->criarTurma($escola, 'Contra', $serie, 'tarde');

        $alunoPrincipal = Aluno::query()->create([
            'nome' => 'Aluno Copia CGM',
            'cgm' => 'CGM-COPIAR',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaPrincipal->id,
            'permite_contra_turno' => true,
        ]);
        $alunoContraTurno = Aluno::query()->create([
            'nome' => 'Aluno Copia CGM',
            'cgm' => 'CGM-COPIAR',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaContraTurno->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Alunos');

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->assertCanSeeTableRecords([$alunoPrincipal, $alunoContraTurno])
            ->assertTableActionDoesNotExist('copiar_cgm')
            ->assertTableColumnExists('cgm', function (\Filament\Tables\Columns\TextColumn $column): bool {
                return $column->isCopyable($column->getState());
            }, $alunoPrincipal)
            ->assertSee('Principal')
            ->assertSee('Contra turno');
    }

    public function test_listagem_restaurada_exibe_acoes_principais_do_aluno_com_permissoes(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Editar Alunos');
        Permission::findOrCreate('Excluir Alunos');
        Permission::findOrCreate('Realizar Remanejamento de Aluno');
        Permission::findOrCreate('Gerar Parecer de Transferencia');

        $escola = $this->criarEscola('Escola Acoes Restauradas');
        $serie = Serie::query()->create([
            'codigo' => 'SER-ACOES',
            'nome' => 'Serie Acoes',
        ]);
        $turmaOrigem = $this->criarTurma($escola, 'Origem', $serie, 'tarde');
        $turmaAtual = $this->criarTurma($escola, 'Atual', $serie, 'manha');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Acoes Restauradas',
            'cgm' => 'CGM-ACOES-RESTAURADAS',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaAtual->id,
            'turma_origem_id' => $turmaOrigem->id,
        ]);

        $this->criarAvaliacaoParaTurma($turmaAtual);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo([
            'Listar Alunos',
            'Editar Alunos',
            'Excluir Alunos',
            'Realizar Remanejamento de Aluno',
            'Gerar Parecer de Transferencia',
        ]);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->assertCanSeeTableRecords([$aluno])
            ->assertTableActionVisible('remanejar', $aluno)
            ->assertTableActionVisible('voltar_turma_anterior', $aluno)
            ->assertTableActionVisible('marcar_contra_turno', $aluno)
            ->assertTableActionVisible('parecer_transferencia', $aluno)
            ->assertTableActionVisible('edit', $aluno)
            ->assertTableActionVisible('delete', $aluno)
            ->assertSee('Remanejar')
            ->assertSee('Voltar turma anterior')
            ->assertSee('Marcar contra turno')
            ->assertSee('Parecer de Transferencia')
            ->assertSee('Editar')
            ->assertSee('Excluir');
    }

    public function test_acao_de_linha_marca_contra_turno_e_exibe_duas_linhas_ativas(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Editar Alunos');

        $escola = $this->criarEscola('Escola Linha Contra Turno');
        $serie = Serie::query()->create([
            'codigo' => 'SER-LINHA-CT',
            'nome' => 'Serie Linha Contra Turno',
        ]);
        $turmaPrincipal = $this->criarTurma($escola, 'Manha', $serie, 'manha');
        $turmaContraTurno = $this->criarTurma($escola, 'Tarde', $serie, 'tarde');

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Linha Contra Turno',
            'cgm' => 'CGM-LINHA-CT',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaPrincipal->id,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Alunos', 'Editar Alunos']);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->callTableAction('marcar_contra_turno', $aluno, [
                'turma_contra_turno_id' => $turmaContraTurno->id,
            ])
            ->assertHasNoTableActionErrors();

        $contraTurno = Aluno::query()
            ->where('cgm', 'CGM-LINHA-CT')
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_CONTRA_TURNO)
            ->first();

        $this->assertNotNull($contraTurno);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->mountTableAction('marcar_contra_turno', $aluno->fresh())
            ->assertTableActionDataSet([
                'turma_contra_turno_id' => $turmaContraTurno->id,
            ]);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->assertCanSeeTableRecords([$aluno->fresh(), $contraTurno])
            ->assertTableActionVisible('marcar_contra_turno', $aluno->fresh())
            ->assertTableActionVisible('encerrar_contra_turno', $contraTurno)
            ->assertTableActionHidden('remanejar', $contraTurno)
            ->assertTableActionHidden('parecer_transferencia', $contraTurno)
            ->assertTableActionHidden('edit', $contraTurno)
            ->assertTableActionHidden('delete', $contraTurno)
            ->assertSee('Contra turno');
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

    public function test_acao_em_massa_de_contra_turno_processa_sucesso_parcial(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Editar Alunos');

        $escola = $this->criarEscola('Escola Bulk Contra Turno');
        $serie = Serie::query()->create([
            'codigo' => 'SER-BULK-CT',
            'nome' => 'Serie Bulk Contra Turno',
        ]);
        $turmaManha = $this->criarTurma($escola, 'Manha', $serie, 'manha');

        $alunoValido = Aluno::query()->create([
            'nome' => 'Aluno Bulk Valido',
            'cgm' => 'CGM-BULK-VALIDO',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaManha->id,
        ]);
        $alunoInvalido = Aluno::query()->create([
            'nome' => 'Aluno Bulk Invalido',
            'cgm' => 'CGM-BULK-INVALIDO',
            'data_nascimento' => '2015-01-02',
            'id_turma' => $turmaManha->id,
            'status' => Aluno::STATUS_PENDENTE,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Alunos', 'Editar Alunos']);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->mountTableBulkAction('marcar_contra_turno_massa', [$alunoValido, $alunoInvalido])
            ->callMountedTableBulkAction()
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('alunos', [
            'id' => $alunoValido->id,
            'permite_contra_turno' => true,
        ]);

        $this->assertDatabaseMissing('alunos', [
            'id' => $alunoInvalido->id,
            'permite_contra_turno' => true,
        ]);
    }

    public function test_listagem_pesquisa_escola_e_filtra_por_turma_e_sexo(): void
    {
        Permission::findOrCreate('Listar Alunos');

        $escolaNorte = $this->criarEscola('Escola Alunos Norte');
        $escolaSul = $this->criarEscola('Escola Alunos Sul');
        $turmaNorte = $this->criarTurma($escolaNorte, 'Norte');
        $turmaSul = $this->criarTurma($escolaSul, 'Sul');

        $alunaNorte = Aluno::query()->create([
            'nome' => 'Aluna Busca Norte',
            'cgm' => 'BUSCA-NORTE',
            'data_nascimento' => '2015-01-01',
            'sexo' => 'F',
            'id_turma' => $turmaNorte->id,
        ]);
        $alunoSul = Aluno::query()->create([
            'nome' => 'Aluno Busca Sul',
            'cgm' => 'BUSCA-SUL',
            'data_nascimento' => '2015-02-01',
            'sexo' => 'M',
            'id_turma' => $turmaSul->id,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Alunos');

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->searchTable('Alunos Norte')
            ->assertCanSeeTableRecords([$alunaNorte])
            ->assertCanNotSeeTableRecords([$alunoSul]);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->searchTable('Feminino')
            ->assertCanSeeTableRecords([$alunaNorte])
            ->assertCanNotSeeTableRecords([$alunoSul]);

        Livewire::actingAs($usuario)
            ->test(ListAlunos::class)
            ->filterTable('id_turma', $turmaSul->id)
            ->filterTable('sexo', 'M')
            ->assertCanSeeTableRecords([$alunoSul])
            ->assertCanNotSeeTableRecords([$alunaNorte]);
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

    private function criarTurma(Escola $escola, string $sufixo, ?Serie $serie = null, string $turno = 'manha'): Turma
    {
        $serie ??= Serie::query()->create([
            'codigo' => 'SER' . $sufixo,
            'nome' => 'Série ' . $sufixo,
        ]);

        return Turma::query()->create([
            'codigo' => 'TUR' . $sufixo . uniqid(),
            'nome' => $sufixo,
            'turno' => $turno,
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarAvaliacaoParaTurma(Turma $turma): Avaliacao
    {
        $tipo = TipoAvaliacao::query()->create([
            'nome' => 'Parecer ' . uniqid(),
            'status' => true,
        ]);
        $periodo = PeriodoAvaliacao::query()->create([
            'nome' => 'Periodo ' . uniqid(),
            'status' => true,
        ]);

        $avaliacao = Avaliacao::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'nome' => 'Avaliacao ' . uniqid(),
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(7)->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->turmas()->attach($turma->id);

        return $avaliacao;
    }
}
