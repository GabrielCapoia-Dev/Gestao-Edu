<?php

namespace Tests\Feature\Servidores;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\User;
use App\Services\ServidorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ServidorFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_normaliza_nome_do_servidor_para_maiusculas_ao_criar_e_editar(): void
    {
        $servidor = Servidor::query()->create([
            'nome' => 'João da Conceição',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $this->assertSame('JOÃO DA CONCEIÇÃO', $servidor->fresh()->nome);

        $servidor->update(['nome' => 'Márcia Gonçalves de Sá']);

        $this->assertSame('MÁRCIA GONÇALVES DE SÁ', $servidor->fresh()->nome);
    }

    public function test_backfill_cria_servidor_para_professor_existente_sem_duplicar(): void
    {
        $escola = $this->criarEscola('Escola Backfill');

        $professor = Professor::withoutEvents(fn (): Professor => Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-BACKFILL',
            'nome' => 'Professor Backfill',
            'email' => 'professor.backfill@edu.umuarama.pr.gov.br',
        ]));

        $this->assertNull($professor->servidor_id);

        app(ServidorService::class)->backfillProfessores();

        $professor->refresh();
        $funcaoProfessor = FuncaoAdministrativa::professorPadrao();

        $this->assertNotNull($professor->servidor_id);
        $this->assertDatabaseHas('servidores', [
            'id' => $professor->servidor_id,
            'nome' => 'PROFESSOR BACKFILL',
            'matricula' => 'PROF-BACKFILL',
        ]);
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $professor->servidor_id,
            'funcao_administrativa_id' => $funcaoProfessor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);

        $servidoresAntes = Servidor::query()->count();

        app(ServidorService::class)->backfillProfessores();

        $this->assertSame($servidoresAntes, Servidor::query()->count());
        $this->assertSame(1, ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $professor->servidor_id)
            ->where('funcao_administrativa_id', $funcaoProfessor->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->count());
    }

    public function test_cria_servidor_auxiliar_sem_usuario_nem_professor(): void
    {
        $funcaoAuxiliar = FuncaoAdministrativa::query()->create([
            'nome' => 'Auxiliar de Serviços Gerais',
            'categoria' => FuncaoAdministrativa::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => false,
        ]);

        $setor = $this->criarSetor('Operacional');

        $servidor = app(ServidorService::class)->criarServidorComFuncoes([
            'nome' => 'Servidor Auxiliar',
            'matricula' => 'AUX-001',
            'setor_id' => $setor->id,
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'funcao_administrativa_id' => $funcaoAuxiliar->id,
            'matricula' => 'AUX-001',
            'setor_id' => $setor->id,
        ]]);

        $this->assertNull($servidor->user_id);
        $this->assertFalse($servidor->professores()->exists());
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcaoAuxiliar->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);
    }

    public function test_assessoria_pedagogica_pode_assessorar_varias_escolas(): void
    {
        $escolaA = $this->criarEscola('Escola Assessoria A');
        $escolaB = $this->criarEscola('Escola Assessoria B');

        $assessor = app(ServidorService::class)->criarServidorComFuncoes([
            'cargo' => ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
            'nome' => 'Assessora Pedagógica',
            'email' => 'assessora.pedagogica@edu.umuarama.pr.gov.br',
            'matricula' => 'ASS-001',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'assessoria_pedagogica' => [
                'matricula' => 'ASS-001',
                'turno' => 'integral',
                'escola_ids' => [$escolaA->id, $escolaB->id],
            ],
        ]);

        $this->assertDatabaseHas('professor_matriculas', [
            'servidor_id' => $assessor->id,
            'matricula' => 'ASS-001',
            'turno' => 'integral',
            'carga_horaria' => 40,
        ]);

        $vinculo = $assessor->vinculosAtivos()
            ->where('funcao_administrativa_id', FuncaoAdministrativa::assessoriaPedagogicaPadrao()->id)
            ->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$escolaA->id, $escolaB->id],
            $vinculo->escolasAssessoradas()->pluck('escolas.id')->all(),
        );
        $this->assertTrue($escolaA->vinculosAssessoriaPedagogica()->whereKey($vinculo->id)->exists());
        $this->assertTrue($escolaB->vinculosAssessoriaPedagogica()->whereKey($vinculo->id)->exists());
    }

    public function test_migration_da_assessoria_recupera_tabela_criada_parcialmente(): void
    {
        Schema::drop('assessoria_pedagogica_escola');
        Schema::create('assessoria_pedagogica_escola', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('servidor_funcao_administrativa_id');
            $table->foreignId('escola_id');
            $table->timestamps();
        });

        $migration = require database_path(
            'migrations/2026_08_13_120000_create_assessoria_pedagogica_escola_table.php',
        );
        $migration->up();

        $foreignColumns = collect(Schema::getForeignKeys('assessoria_pedagogica_escola'))
            ->flatMap(fn (array $foreign): array => $foreign['columns'] ?? [])
            ->all();

        $this->assertContains('servidor_funcao_administrativa_id', $foreignColumns);
        $this->assertContains('escola_id', $foreignColumns);
        $this->assertTrue(Schema::hasIndex(
            'assessoria_pedagogica_escola',
            'assessoria_pedagogica_escola_unique',
        ));
        $this->assertTrue(Schema::hasIndex(
            'assessoria_pedagogica_escola',
            'assessoria_pedagogica_escola_reverso',
        ));
    }

    public function test_atualizacao_da_assessoria_sincroniza_as_escolas_sem_duplicar_vinculo_funcional(): void
    {
        $escolaAnterior = $this->criarEscola('Escola Assessoria Anterior');
        $escolaAtual = $this->criarEscola('Escola Assessoria Atual');
        $servico = app(ServidorService::class);
        $dados = [
            'cargo' => ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
            'nome' => 'Assessor Pedagógico',
            'email' => 'assessor.pedagogico@edu.umuarama.pr.gov.br',
            'matricula' => 'ASS-002',
            'status' => Servidor::STATUS_ATIVO,
        ];

        $assessor = $servico->criarServidorComFuncoes($dados, [
            'assessoria_pedagogica' => [
                'matricula' => 'ASS-002',
                'turno' => 'manha',
                'escola_ids' => [$escolaAnterior->id],
            ],
        ]);
        $servico->atualizarServidorComFuncoes($assessor, $dados, [
            'assessoria_pedagogica' => [
                'matricula' => 'ASS-002',
                'turno' => 'tarde',
                'escola_ids' => [$escolaAtual->id, $escolaAtual->id],
            ],
        ]);

        $this->assertDatabaseHas('professor_matriculas', [
            'servidor_id' => $assessor->id,
            'matricula' => 'ASS-002',
            'turno' => 'tarde',
        ]);

        $vinculos = $assessor->vinculosAtivos()
            ->where('funcao_administrativa_id', FuncaoAdministrativa::assessoriaPedagogicaPadrao()->id)
            ->get();

        $this->assertCount(1, $vinculos);
        $this->assertSame([$escolaAtual->id], $vinculos->first()->escolasAssessoradas()->pluck('escolas.id')->all());
        $this->assertDatabaseMissing('assessoria_pedagogica_escola', [
            'servidor_funcao_administrativa_id' => $vinculos->first()->id,
            'escola_id' => $escolaAnterior->id,
        ]);
    }

    public function test_formulario_exige_matricula_do_servidor(): void
    {
        Permission::findOrCreate('Listar Servidores');
        Permission::findOrCreate('Criar Servidores');
        Permission::findOrCreate('Gerenciar Funções de Servidores');

        $funcaoAuxiliar = FuncaoAdministrativa::query()->create([
            'nome' => 'Auxiliar Administrativo',
            'categoria' => FuncaoAdministrativa::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => false,
        ]);
        $setor = $this->criarSetor('Administrativo');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Servidores', 'Criar Servidores', 'Gerenciar Funções de Servidores']);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->callAction('create', [
                'nome' => 'Servidor Sem Matricula',
                'status' => Servidor::STATUS_ATIVO,
                'vinculos_funcionais' => [
                    [
                        'funcao_administrativa_id' => $funcaoAuxiliar->id,
                        'setor_id' => $setor->id,
                    ],
                ],
            ])
            ->assertHasActionErrors(['vinculos_funcionais.0.matricula' => 'required']);

        $this->assertDatabaseMissing('servidores', [
            'nome' => 'Servidor Sem Matricula',
        ]);
    }

    public function test_formulario_permite_matricula_repetida_para_servidores(): void
    {
        $funcaoAuxiliar = FuncaoAdministrativa::query()->create([
            'nome' => 'Auxiliar Operacional',
            'categoria' => FuncaoAdministrativa::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => false,
        ]);

        $setor = $this->criarSetor('Operacional Repetida');

        app(ServidorService::class)->criarServidorComFuncoes([
            'nome' => 'Servidor Existente',
            'matricula' => 'MAT-REPETIDA',
            'setor_id' => $setor->id,
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'funcao_administrativa_id' => $funcaoAuxiliar->id,
            'matricula' => 'MAT-REPETIDA',
            'setor_id' => $setor->id,
        ]]);

        app(ServidorService::class)->criarServidorComFuncoes([
            'nome' => 'Servidor Nova Matricula Repetida',
            'matricula' => 'MAT-REPETIDA',
            'setor_id' => $setor->id,
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'funcao_administrativa_id' => $funcaoAuxiliar->id,
            'matricula' => 'MAT-REPETIDA',
            'setor_id' => $setor->id,
        ]]);

        $this->assertSame(2, Servidor::query()->where('matricula', 'MAT-REPETIDA')->count());
    }

    public function test_fluxo_generico_rejeita_vinculo_da_equipe_gestora(): void
    {
        $escola = $this->criarEscola('Escola Vinculo Funcional');
        $serie = Serie::query()->create(['codigo' => 'SER-FUNC', 'nome' => 'Serie Funcional']);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-FUNC',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $funcaoCoordenacao = FuncaoAdministrativa::query()->create([
            'nome' => 'Coordenacao Pedagogica',
            'categoria' => FuncaoAdministrativa::CATEGORIA_PEDAGOGICO,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => true,
            'coordenacao_pedagogica' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ServidorService::class)->criarServidorComFuncoes([
            'id_escola' => $escola->id,
            'setor_id' => $escola->setor_id,
            'nome' => 'Servidor Coordenacao',
            'matricula' => 'COORD-001',
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'funcao_administrativa_id' => $funcaoCoordenacao->id,
            'matricula' => 'COORD-001',
            'setor_id' => $escola->setor_id,
            'id_escola' => $escola->id,
            'portaria' => '123/2026',
            'turma_ids' => [$turma->id],
        ]]);

    }

    public function test_funcao_professor_cria_ou_vincula_cadastro_pedagogico(): void
    {
        $escola = $this->criarEscola('Escola Professor Servidor');

        $servidor = app(ServidorService::class)->criarServidorComFuncoes([
            'id_escola' => $escola->id,
            'setor_id' => $escola->setor_id,
            'nome' => 'Servidor Professor',
            'matricula' => 'PROF-SERV-001',
            'email' => 'servidor.professor@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'funcao_administrativa_id' => FuncaoAdministrativa::professorPadrao()->id,
            'matricula' => 'PROF-SERV-001',
            'setor_id' => $escola->setor_id,
            'id_escola' => $escola->id,
        ]]);

        $this->assertDatabaseHas('professores', [
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-SERV-001',
            'nome' => 'Servidor Professor',
        ]);
    }

    public function test_nao_remove_funcao_professor_com_vinculo_pedagogico_ativo(): void
    {
        $escola = $this->criarEscola('Escola Vinculo Pedagogico');
        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-VINCULO',
            'nome' => 'Professor com Vinculo',
            'email' => 'professor.vinculo@edu.umuarama.pr.gov.br',
        ]);

        $serie = Serie::query()->create(['codigo' => 'SER-VINC', 'nome' => 'Serie Vinculo']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-VINC', 'nome' => 'Arte']);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-VINC',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ServidorService::class)->removerFuncao(
            $professor->fresh()->servidor,
            FuncaoAdministrativa::professorPadrao(),
        );
    }

    public function test_listagem_de_servidores_respeita_escopo_por_setor_da_escola(): void
    {
        Permission::findOrCreate('Listar Servidores');

        $root = $this->criarSetor('Secretaria');
        $pedagogico = $this->criarSetor('Pedagogico', $root);
        $administrativo = $this->criarSetor('Administrativo', $root);

        $escolaA = $this->criarEscola('Escola A', $pedagogico);
        $escolaB = $this->criarEscola('Escola B', $administrativo);

        $servidorVisivel = Servidor::query()->create([
            'id_escola' => $escolaA->id,
            'nome' => 'Servidor Visivel',
            'matricula' => 'SERV-A',
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $servidorOculto = Servidor::query()->create([
            'id_escola' => $escolaB->id,
            'nome' => 'Servidor Oculto',
            'matricula' => 'SERV-B',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $usuario = User::factory()->create([
            'id_escola' => $escolaA->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Servidores');

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->assertCanSeeTableRecords([$servidorVisivel])
            ->assertCanNotSeeTableRecords([$servidorOculto]);
    }

    public function test_policy_de_servidores_exige_permissoes_especificas(): void
    {
        $escola = $this->criarEscola('Escola Policy');
        $servidor = Servidor::query()->create([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Policy',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $usuario = User::factory()->create([
            'id_escola' => $escola->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($usuario)->allows('viewAny', Servidor::class));

        Permission::findOrCreate('Listar Servidores');
        Permission::findOrCreate('Editar Servidores');

        $usuario->givePermissionTo(['Listar Servidores', 'Editar Servidores']);

        $this->assertTrue(Gate::forUser($usuario)->allows('viewAny', Servidor::class));
        $this->assertTrue(Gate::forUser($usuario)->allows('update', $servidor));
    }

    public function test_altera_status_de_apenas_um_servidor(): void
    {
        $escola = $this->criarEscola('Escola Status Individual');
        $servidorAlterado = Servidor::query()->create([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Status Individual',
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $servidorPreservado = Servidor::query()->create([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Status Preservado',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        app(ServidorService::class)->alterarStatus($servidorAlterado, Servidor::STATUS_INATIVO);

        $this->assertSame(Servidor::STATUS_INATIVO, $servidorAlterado->fresh()->status);
        $this->assertSame(Servidor::STATUS_ATIVO, $servidorPreservado->fresh()->status);
    }

    public function test_altera_status_em_massa_de_todos_os_servidores_informados(): void
    {
        $escola = $this->criarEscola('Escola Status em Massa');
        $primeiro = Servidor::query()->create([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Status Massa Um',
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $segundo = Servidor::query()->create([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Status Massa Dois',
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $naoSelecionado = Servidor::query()->create([
            'id_escola' => $escola->id,
            'nome' => 'Servidor Status Fora da Massa',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $alterados = app(ServidorService::class)->alterarStatusEmMassa(
            collect([$primeiro, $segundo]),
            Servidor::STATUS_INATIVO,
        );

        $this->assertSame(2, $alterados);
        $this->assertSame(Servidor::STATUS_INATIVO, $primeiro->fresh()->status);
        $this->assertSame(Servidor::STATUS_INATIVO, $segundo->fresh()->status);
        $this->assertSame(Servidor::STATUS_ATIVO, $naoSelecionado->fresh()->status);
    }

    public function test_pessoa_vinculada_a_admin_nao_pode_ser_selecionada_nem_sofrer_acoes_destrutivas(): void
    {
        foreach ([
            'Listar Pessoas',
            'Editar Pessoas',
            'Excluir Pessoas',
            'Gerenciar Vínculos Estruturais de Pessoas',
        ] as $permissao) {
            Permission::findOrCreate($permissao);
        }

        $roleAdmin = Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);
        $operador = User::factory()->create();
        $operador->assignRole($roleAdmin);
        $operador->givePermissionTo([
            'Listar Pessoas',
            'Editar Pessoas',
            'Excluir Pessoas',
            'Gerenciar Vínculos Estruturais de Pessoas',
        ]);

        $adminProtegido = User::factory()->create();
        $adminProtegido->assignRole($roleAdmin);
        $pessoaAdmin = Servidor::query()->create([
            'user_id' => $adminProtegido->id,
            'nome' => 'Pessoa Administradora Protegida',
            'email' => $adminProtegido->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $this->actingAs($operador);

        $this->assertFalse(ServidorResource::pessoaPodeSerSelecionada($pessoaAdmin->load('user.roles')));
        $this->assertFalse(Gate::forUser($operador)->allows('manageStructure', $pessoaAdmin));
        $this->assertFalse(Gate::forUser($operador)->allows('delete', $pessoaAdmin));
        $this->assertFalse(Gate::forUser($operador)->allows('restore', $pessoaAdmin));
    }

    private function criarSetor(string $nome, ?Setor $parent = null): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'parent_id' => $parent?->id,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => $parent === null,
        ]);
    }

    private function criarEscola(string $nome, ?Setor $setor = null): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome.microtime()), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor?->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }
}
