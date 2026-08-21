<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Servidores\Schemas\ServidorEquipeGestoraForm;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Livewire\Pessoas\PessoaForm;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorFuncaoTurma;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\ServidorService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Tabs;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Enums\RecordActionsPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PessoaHubFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_resource_exibe_entrada_servidores_no_menu(): void
    {
        $this->assertSame('Servidores', ServidorResource::getNavigationLabel());
        $this->assertSame('Servidor', ServidorResource::getModelLabel());
    }

    public function test_hub_lista_unica_sem_metodo_de_abas_proprio(): void
    {
        $reflection = new \ReflectionClass(ManageServidores::class);

        $this->assertFalse(
            $reflection->hasMethod('getTabs')
                && $reflection->getMethod('getTabs')->getDeclaringClass()->getName() === ManageServidores::class,
            'ManageServidores não deve declarar abas próprias.',
        );
    }

    public function test_lista_exibe_todas_as_pessoas_sem_filtro_de_aba(): void
    {
        $usuario = $this->usuarioComPermissaoListar();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Hub', $setor);

        $comProfessor = $this->criarServidor('Com registro professor', $escola, $setor);
        Professor::query()->create([
            'servidor_id' => $comProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-HUB',
            'turno' => 'manha',
            'nome' => $comProfessor->nome,
            'email' => 'prof.hub@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        $semProfessor = $this->criarServidor('Sem registro professor', $escola, $setor);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->assertCanSeeTableRecords([$comProfessor, $semProfessor]);
    }

    public function test_lista_usa_layout_responsivo_e_menu_unico_de_acoes(): void
    {
        $usuario = $this->usuarioComPermissaoListar();
        $table = Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->instance()
            ->getTable();

        $this->assertTrue($table->hasColumnsLayout());
        $this->assertTrue(collect($table->getColumnsLayout())->contains(
            fn ($column): bool => $column instanceof Grid,
        ));
        $this->assertSame(RecordActionsPosition::AfterContent, $table->getRecordActionsPosition());

        $actions = $table->getRecordActions();
        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
        $this->assertSame('Ações', $actions[0]->getLabel());
        $this->assertSame('bottom-end', $actions[0]->getDropdownPlacement());
        $this->assertSame(6, $actions[0]->getDropdownOffset());
        $this->assertTrue($actions[0]->hasDropdownTeleport());

        $niveisDeAcesso = $table->getColumn('user.roles.name');
        $this->assertTrue($niveisDeAcesso->canWrap());
        $this->assertSame(2, $niveisDeAcesso->getColumnSpan('xl'));

        foreach (['nome', 'cargo_label', 'escolas_resumo', 'vinculos_resumo', 'email', 'status', 'acesso_ao_sistema', 'user.roles.name', 'updated_at'] as $coluna) {
            $this->assertTrue($table->getColumn($coluna)->isCopyable('valor'), "A coluna {$coluna} deve permitir cópia ao clicar.");
        }
    }

    public function test_dropdown_de_select_em_modal_fica_acima_da_sobreposicao(): void
    {
        $styles = file_get_contents(
            resource_path('views/filament/pages/partials/pessoas-responsive-table-styles.blade.php'),
        );

        $this->assertIsString($styles);
        $this->assertStringContainsString(
            'body:has(.pe-pessoas-page) .fi-dropdown-panel:not(.fi-select-dropdown-portal)',
            $styles,
        );
        $this->assertStringNotContainsString(
            'body:has(.pe-pessoas-page) .fi-dropdown-panel {',
            $styles,
        );
    }

    public function test_lista_exibe_os_nomes_das_escolas_da_pessoa(): void
    {
        $usuario = $this->usuarioComPermissaoListar();
        $setor = $this->criarSetor('Setor escolas da listagem');
        $escolaPrincipal = $this->criarEscola('Escola Principal', $setor);
        $escolaAdicional = $this->criarEscola('Escola Adicional', $setor);
        $servidor = $this->criarServidor('Servidor em duas escolas', $escolaPrincipal, $setor);

        foreach ([$escolaPrincipal, $escolaAdicional] as $indice => $escola) {
            Professor::query()->create([
                'servidor_id' => $servidor->id,
                'id_escola' => $escola->id,
                'matricula' => 'ESCOLAS-00'.($indice + 1),
                'turno' => 'manha',
                'nome' => $servidor->nome,
                'email' => $servidor->email,
                'ativo' => true,
            ]);
        }

        $this->actingAs($usuario);

        $this->assertSame(
            'Escola Adicional, Escola Principal',
            ServidorResource::escolasLabel($servidor->fresh()),
        );
    }

    public function test_filtros_de_cargo_quantidade_de_matriculas_e_turno(): void
    {
        $usuario = $this->usuarioComPermissaoListar();
        $setor = $this->criarSetor('Setor dos filtros');
        $escola = $this->criarEscola('Escola dos filtros', $setor);

        $professor = $this->criarServidor('Pessoa Professora', $escola, $setor);
        PessoaMatricula::query()->create([
            'servidor_id' => $professor->id,
            'matricula' => 'FILTRO-PROF',
            'turno' => 'manha',
        ]);
        Professor::query()->create([
            'servidor_id' => $professor->id,
            'id_escola' => $escola->id,
            'matricula' => 'FILTRO-PROF',
            'turno' => 'manha',
            'nome' => $professor->nome,
            'email' => $professor->email,
            'ativo' => true,
        ]);

        $gestora = $this->criarServidor('Pessoa Gestora', $escola, $setor);
        foreach ([
            ['FILTRO-GEST-M', 'manha'],
            ['FILTRO-GEST-T', 'tarde'],
        ] as [$matricula, $turno]) {
            PessoaMatricula::query()->create([
                'servidor_id' => $gestora->id,
                'matricula' => $matricula,
                'turno' => $turno,
            ]);
        }
        $funcaoGestora = FuncaoAdministrativa::query()->create([
            'codigo' => 'direcao-filtro-listagem',
            'nome' => 'Direção para filtro',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => true,
            'tem_relacao_turma' => true,
            'direcao_escolar' => true,
            'coordenacao_pedagogica' => false,
            'secretaria_escolar' => false,
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $gestora->id,
            'funcao_administrativa_id' => $funcaoGestora->id,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'data_inicio' => now()->toDateString(),
        ]);

        $manutencao = $this->criarServidor('Pessoa Manutenção', $escola, $setor);
        PessoaMatricula::query()->create([
            'servidor_id' => $manutencao->id,
            'matricula' => 'FILTRO-MAN',
            'turno' => 'integral',
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $manutencao->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::manutencaoPadrao()->id,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'data_inicio' => now()->toDateString(),
        ]);

        $semCargo = $this->criarServidor('Pessoa Sem Cargo', $escola, $setor);

        $tresMatriculas = $this->criarServidor('Pessoa com Três Matrículas', $escola, $setor);
        foreach (range(1, 3) as $indice) {
            PessoaMatricula::query()->create([
                'servidor_id' => $tresMatriculas->id,
                'matricula' => "FILTRO-TRES-{$indice}",
                'turno' => 'manha',
            ]);
        }

        $componente = Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->assertTableFilterExists('cargo')
            ->assertTableFilterExists('quantidade_matriculas')
            ->assertTableFilterExists('turno_matricula')
            ->assertTableFilterExists('setor_id')
            ->assertTableFilterExists('periodo_cadastro')
            ->filterTable('quantidade_matriculas', 'uma')
            ->assertCanSeeTableRecords([$professor, $manutencao])
            ->assertCanNotSeeTableRecords([$gestora, $tresMatriculas, $semCargo]);

        $filtroQuantidade = $componente->instance()->getTable()->getFilter('quantidade_matriculas');
        $this->assertSame([
            'uma' => 'Uma matrícula',
            'duas' => 'Duas matrículas',
            'tres_ou_mais' => 'Três ou mais matrículas',
            'sem' => 'Sem matrícula',
        ], $filtroQuantidade->getOptions());
        $this->assertSame('Todas as quantidades', $filtroQuantidade->getPlaceholder());

        $filtroCargo = $componente->instance()->getTable()->getFilter('cargo');
        $this->assertSame([
            ServidorResource::CARGO_PROFESSOR => 'Professor',
            ServidorEquipeGestoraForm::CARGO_DIRETOR => 'Diretor',
            ServidorEquipeGestoraForm::CARGO_COORDENADOR => 'Coordenador',
            ServidorEquipeGestoraForm::CARGO_SECRETARIO => 'Secretário',
            ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA => 'Assessoria Pedagógica',
            ServidorResource::CARGO_MANUTENCAO => 'Manutenção',
            ServidorResource::CARGO_OBRAS => 'Obras',
            'sem_cargo' => 'Sem cargo ativo',
        ], $filtroCargo->getOptions());

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->filterTable('quantidade_matriculas', 'duas')
            ->assertCanSeeTableRecords([$gestora])
            ->assertCanNotSeeTableRecords([$professor, $manutencao, $tresMatriculas, $semCargo]);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->filterTable('quantidade_matriculas', 'tres_ou_mais')
            ->assertCanSeeTableRecords([$tresMatriculas])
            ->assertCanNotSeeTableRecords([$professor, $gestora, $manutencao, $semCargo]);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->filterTable('cargo', [ServidorResource::CARGO_PROFESSOR])
            ->assertCanSeeTableRecords([$professor])
            ->assertCanNotSeeTableRecords([$gestora, $manutencao, $semCargo]);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->filterTable('cargo', [ServidorEquipeGestoraForm::CARGO_DIRETOR])
            ->assertCanSeeTableRecords([$gestora])
            ->assertCanNotSeeTableRecords([$professor, $manutencao, $semCargo]);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->filterTable('cargo', [ServidorResource::CARGO_MANUTENCAO])
            ->filterTable('turno_matricula', ['integral'])
            ->assertCanSeeTableRecords([$manutencao])
            ->assertCanNotSeeTableRecords([$professor, $gestora, $semCargo]);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->searchTable('FILTRO-MAN')
            ->assertCanSeeTableRecords([$manutencao])
            ->assertCanNotSeeTableRecords([$professor, $gestora, $semCargo]);
    }

    public function test_consulta_do_filtro_de_cargo_separa_funcoes_da_equipe_gestora_e_assessoria(): void
    {
        $setor = $this->criarSetor('Setor dos cargos separados');
        $escola = $this->criarEscola('Escola dos cargos separados', $setor);

        $servidoresPorCargo = [
            ServidorEquipeGestoraForm::CARGO_DIRETOR => [$this->criarServidor('Diretor do filtro', $escola, $setor), FuncaoAdministrativa::direcaoPadrao()],
            ServidorEquipeGestoraForm::CARGO_COORDENADOR => [$this->criarServidor('Coordenador do filtro', $escola, $setor), FuncaoAdministrativa::coordenacaoPadrao()],
            ServidorEquipeGestoraForm::CARGO_SECRETARIO => [$this->criarServidor('Secretário do filtro', $escola, $setor), FuncaoAdministrativa::secretariaPadrao()],
            ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA => [$this->criarServidor('Assessoria do filtro', $escola, $setor), FuncaoAdministrativa::assessoriaPedagogicaPadrao()],
        ];

        foreach ($servidoresPorCargo as [$servidor, $funcao]) {
            ServidorFuncaoAdministrativa::query()->create([
                'servidor_id' => $servidor->id,
                'funcao_administrativa_id' => $funcao->id,
                'id_escola' => $funcao->ehAssessoriaPedagogica() ? null : $escola->id,
                'setor_id' => $funcao->ehAssessoriaPedagogica() ? null : $setor->id,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                'origem' => 'teste',
                'data_inicio' => now()->toDateString(),
            ]);
        }

        $metodoFiltro = new \ReflectionMethod(ServidorResource::class, 'aplicarFiltroCargos');

        foreach ($servidoresPorCargo as $cargo => [$servidor]) {
            $resultado = $metodoFiltro
                ->invoke(null, Servidor::query(), [$cargo])
                ->pluck('id')
                ->all();

            $this->assertSame([$servidor->id], $resultado, "O filtro {$cargo} deve retornar somente o cargo selecionado.");
        }
    }

    public function test_busca_e_contagem_de_matriculas_respeitam_escopo_escolar_e_permissao_de_usuario(): void
    {
        $setorA = $this->criarSetor('Setor escopo A');
        $setorB = $this->criarSetor('Setor escopo B');
        $escolaA = $this->criarEscola('Escola escopo A', $setorA);
        $escolaB = $this->criarEscola('Escola escopo B', $setorB);
        $contaAlvo = User::factory()->create();
        $roleReservada = Role::query()->firstOrCreate([
            'name' => 'Nível Reservado da Pessoa',
            'guard_name' => 'web',
        ]);
        $contaAlvo->assignRole($roleReservada);

        $pessoa = $this->criarServidor('Pessoa com matrícula por escopo', $escolaA, $setorA, $contaAlvo->id);
        $matriculaVisivel = PessoaMatricula::query()->create([
            'servidor_id' => $pessoa->id,
            'matricula' => 'MATRICULA-VISIVEL',
            'turno' => 'manha',
        ]);
        $matriculaOculta = PessoaMatricula::query()->create([
            'servidor_id' => $pessoa->id,
            'matricula' => 'MATRICULA-OCULTA',
            'turno' => 'tarde',
        ]);

        foreach ([
            [$matriculaVisivel, $escolaA, 'manha'],
            [$matriculaOculta, $escolaB, 'tarde'],
        ] as [$matricula, $escola, $turno]) {
            Professor::query()->create([
                'servidor_id' => $pessoa->id,
                'professor_matricula_id' => $matricula->id,
                'id_escola' => $escola->id,
                'matricula' => $matricula->matricula,
                'turno' => $turno,
                'nome' => $pessoa->nome,
                'email' => $pessoa->email,
                'ativo' => true,
            ]);
        }

        $restrito = User::factory()->create([
            'id_escola' => $escolaA->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $restrito->givePermissionTo($this->garantirPermissao('Listar Pessoas'));

        Livewire::actingAs($restrito)
            ->test(ManageServidores::class)
            ->assertTableFilterHidden('nivel_acesso')
            ->filterTable('quantidade_matriculas', 'uma')
            ->assertCanSeeTableRecords([$pessoa]);

        Livewire::actingAs($restrito)
            ->test(ManageServidores::class)
            ->filterTable('quantidade_matriculas', 'duas')
            ->assertCanNotSeeTableRecords([$pessoa]);

        Livewire::actingAs($restrito)
            ->test(ManageServidores::class)
            ->searchTable('MATRICULA-VISIVEL')
            ->assertCanSeeTableRecords([$pessoa]);

        Livewire::actingAs($restrito)
            ->test(ManageServidores::class)
            ->searchTable('MATRICULA-OCULTA')
            ->assertCanNotSeeTableRecords([$pessoa]);

        Livewire::actingAs($restrito)
            ->test(ManageServidores::class)
            ->searchTable('Nível Reservado da Pessoa')
            ->assertCanNotSeeTableRecords([$pessoa]);

        $this->actingAs($restrito);
        $this->assertSame('Escola escopo A', ServidorResource::escolasLabel($pessoa->fresh()));
    }

    public function test_header_novo_servidor(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas', 'Criar Pessoas']);

        $actions = Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->instance()
            ->getCachedHeaderActions();

        $createAction = collect($actions)->first(
            fn (Action $action): bool => $action->getName() === 'create',
        );

        $this->assertInstanceOf(Action::class, $createAction);
        $this->assertSame('Novo servidor', $createAction->getLabel());
        $this->assertFalse($createAction->isModalSlideOver());
        $this->assertSame('6xl', $createAction->getModalWidth());
        $this->assertTrue($createAction->isModalHeaderSticky());
        $this->assertStringContainsString(
            'pessoa-modal-window',
            (string) ($createAction->getExtraModalWindowAttributes()['class'] ?? ''),
        );
    }

    public function test_create_modal_exibe_campos_de_servidor_sem_acesso(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas', 'Criar Pessoas']);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->mountAction('create')
            ->assertSeeLivewire(PessoaForm::class)
            ->assertDontSee('email_approved')
            ->assertDontSee('roles_adicionais')
            ->assertDontSee('usar_permissoes_extras')
            ->assertDontSee('password');
    }

    public function test_formulario_personalizado_renderiza_no_modo_criacao(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas', 'Criar Pessoas']);

        Livewire::actingAs($usuario)
            ->test(PessoaForm::class, ['pessoaId' => null])
            ->assertOk()
            ->assertSet('pessoaId', null);
    }

    public function test_edit_servidor_abre_modal_com_registros(): void
    {
        $usuario = $this->usuarioHubAdmin([
            'Listar Pessoas',
            'Editar Pessoas',
            'Gerenciar Vínculos Estruturais de Pessoas',
        ]);

        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Edit', $setor);
        $servidor = $this->criarServidor('Servidor Editável', $escola, $setor);

        Professor::query()->create([
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'EDIT-001',
            'turno' => 'tarde',
            'nome' => $servidor->nome,
            'email' => 'edit@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->mountTableAction('edit', $servidor)
            ->assertSeeLivewire(PessoaForm::class)
            ->assertDontSee('email_approved')
            ->assertDontSee('roles_adicionais')
            ->assertDontSee('usar_permissoes_extras')
            ->assertDontSee('password');
    }

    public function test_formulario_personalizado_renderiza_no_modo_edicao(): void
    {
        $usuario = $this->usuarioHubAdmin([
            'Listar Pessoas',
            'Editar Pessoas',
            'Gerenciar Vínculos Estruturais de Pessoas',
        ]);
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Formulário', $setor);
        $servidor = $this->criarServidor('Pessoa Formulário', $escola, $setor);

        Professor::query()->create([
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'FORM-001',
            'turno' => 'manha',
            'nome' => $servidor->nome,
            'email' => $servidor->email,
            'ativo' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(PessoaForm::class, ['pessoaId' => $servidor->id])
            ->assertOk()
            ->assertSet('pessoaId', $servidor->id)
            ->assertSet('nome', 'Pessoa Formulário');
    }

    public function test_formulario_converte_duas_matriculas_em_um_vinculo_gestor_sem_perder_estado(): void
    {
        $usuario = $this->usuarioHubAdmin([
            'Listar Pessoas',
            'Editar Pessoas',
            'Gerenciar Vínculos Estruturais de Pessoas',
        ]);
        Role::query()->firstOrCreate(['name' => 'Equipe Gestora', 'guard_name' => 'web']);
        $setorManha = $this->criarSetor('Setor Manhã');
        $setorTarde = $this->criarSetor('Setor Tarde');
        $setorGestao = $this->criarSetor('Setor Gestão');
        $escolaManha = $this->criarEscola('Escola Form Manhã', $setorManha);
        $escolaTarde = $this->criarEscola('Escola Form Tarde', $setorTarde);
        $escolaGestora = $this->criarEscola('Escola Form Gestão', $setorGestao);
        $servidor = $this->criarServidor('Pessoa Duas Matrículas', $escolaManha, $setorManha);
        $funcaoProfessor = FuncaoAdministrativa::professorPadrao();

        foreach ([
            [$escolaManha, 'FORM-MANHA', 'manha'],
            [$escolaTarde, 'FORM-TARDE', 'tarde'],
        ] as [$escola, $numero, $turno]) {
            $matricula = PessoaMatricula::query()->create([
                'servidor_id' => $servidor->id,
                'matricula' => $numero,
                'turno' => $turno,
            ]);
            $vinculo = ServidorFuncaoAdministrativa::query()->create([
                'servidor_id' => $servidor->id,
                'funcao_administrativa_id' => $funcaoProfessor->id,
                'matricula' => $numero,
                'id_escola' => $escola->id,
                'setor_id' => $escola->setor_id,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                'origem' => 'professor',
            ]);
            Professor::query()->create([
                'servidor_id' => $servidor->id,
                'professor_matricula_id' => $matricula->id,
                'servidor_funcao_administrativa_id' => $vinculo->id,
                'id_escola' => $escola->id,
                'matricula' => $numero,
                'turno' => $turno,
                'nome' => $servidor->nome,
                'email' => $servidor->email,
                'ativo' => true,
            ]);
        }

        $componente = Livewire::actingAs($usuario)->test(PessoaForm::class, ['pessoaId' => $servidor->id]);
        $chaves = array_keys($componente->get('matriculas'));

        $this->assertCount(2, $chaves);
        foreach ($chaves as $chave) {
            $componente
                ->assertSeeHtml('id="pessoa-form-matricula-panel-'.$chave.'"')
                ->assertSeeHtml('id="pessoa-form-matricula-'.$chave.'"')
                ->assertSeeHtml('id="pessoa-form-turno-'.$chave.'"');
        }

        $componente
            ->set('cargo', ServidorResource::CARGO_EQUIPE_GESTORA)
            ->call('escolaGestoraAlterada', $escolaGestora->id)
            ->set('cargosGestores', ['diretor'])
            ->set('portaria', '678/2026')
            ->set('cargaHoraria', 20)
            ->set('jornada', true)
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertDispatched('pessoa-form-salvo')
            ->assertSet("matriculas.{$chaves[0]}.matricula", 'FORM-MANHA')
            ->assertSet("matriculas.{$chaves[1]}.matricula", 'FORM-TARDE');

        $this->assertSame(2, PessoaMatricula::query()->where('servidor_id', $servidor->id)->count());
        $this->assertSame(1, $servidor->fresh()->vinculosAtivos()
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
            ->count());
        $this->assertSame($escolaGestora->id, $servidor->fresh()->id_escola);
    }

    public function test_formulario_saneia_turno_legado_ao_reduzir_tres_matriculas_para_duas(): void
    {
        $usuario = $this->usuarioHubAdmin([
            'Listar Pessoas',
            'Editar Pessoas',
            'Gerenciar Vínculos Estruturais de Pessoas',
        ]);
        $setor = $this->criarSetor('Setor Saneamento de Matrículas');
        $escola = $this->criarEscola('Escola Saneamento de Matrículas', $setor);
        $servidor = $this->criarServidor('Pessoa com Três Matrículas', $escola, $setor);
        $matriculaTarde = PessoaMatricula::query()->create([
            'servidor_id' => $servidor->id,
            'matricula' => '1082435',
            'turno' => 'tarde',
        ]);
        $matriculaManha = PessoaMatricula::query()->create([
            'servidor_id' => $servidor->id,
            'matricula' => '925253',
            'turno' => 'manha',
        ]);
        $matriculaSemTurno = PessoaMatricula::query()->create([
            'servidor_id' => $servidor->id,
            'matricula' => '1082453',
            'turno' => '',
        ]);

        $componente = Livewire::actingAs($usuario)
            ->test(PessoaForm::class, ['pessoaId' => $servidor->id]);
        $estadoAntesDaRemocao = $componente->get('matriculas');

        $componente
            ->assertCount('matriculas', 3)
            ->call('removerMatricula', 'm'.$matriculaManha->id)
            ->assertCount('matriculas', 2)
            ->assertSet('matriculas.m'.$matriculaTarde->id.'.turno', 'tarde')
            ->assertSet('matriculas.m'.$matriculaSemTurno->id.'.turno', 'manha')
            // Simula um update atrasado do Livewire reintroduzindo o estado anterior.
            ->set('matriculas', $estadoAntesDaRemocao)
            ->assertCount('matriculas', 3)
            ->set('cargaHoraria', 20)
            ->set('jornada', true)
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertDispatched('pessoa-form-salvo');

        $this->assertDatabaseMissing('professor_matriculas', ['id' => $matriculaManha->id]);
        $this->assertDatabaseHas('professor_matriculas', [
            'id' => $matriculaSemTurno->id,
            'turno' => 'manha',
        ]);
        $this->assertSame(2, PessoaMatricula::query()->where('servidor_id', $servidor->id)->count());
    }

    public function test_botao_cancelar_fecha_modal_no_cliente_sem_requisicao_livewire_do_formulario(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas', 'Criar Pessoas']);

        Livewire::actingAs($usuario)
            ->test(PessoaForm::class, ['pessoaId' => null])
            ->assertSeeHtml('x-on:click="$dispatch(\'close-modal\'')
            ->assertDontSeeHtml('wire:click="cancelar"');
    }

    public function test_eventos_do_formulario_fecham_o_modal_personalizado(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas', 'Criar Pessoas']);
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Eventos', $setor);

        $componente = Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->mountAction('create')
            ->assertSet('mountedActions.0.name', 'create');

        $componente
            ->dispatch('pessoa-form-cancelado')
            ->assertSet('mountedActions', []);

        $novaPessoa = $this->criarServidor('Pessoa criada no evento', $escola, $setor);

        $componente
            ->mountAction('create')
            ->dispatch('pessoa-form-salvo')
            ->assertSet('mountedActions', [])
            ->assertCanSeeTableRecords([$novaPessoa]);
    }

    public function test_arquiva_pessoa_e_preserva_registro_profissional(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas', 'Excluir Pessoas']);
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Del', $setor);
        $servidor = $this->criarServidor('Para Excluir', $escola, $setor);

        $professor = Professor::query()->create([
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'DEL-001',
            'turno' => 'manha',
            'nome' => $servidor->nome,
            'email' => 'del@edu.umuarama.pr.gov.br',
            'ativo' => true,
        ]);

        app(ServidorService::class)->excluirPessoa($servidor->fresh());

        $this->assertSoftDeleted('servidores', ['id' => $servidor->id]);
        $this->assertDatabaseHas('professores', [
            'id' => $professor->id,
            'servidor_id' => $servidor->id,
            'ativo' => false,
        ]);
    }

    public function test_cargo_motorista_nao_pode_mais_ser_atribuido_e_registros_historicos_sao_preservados(): void
    {
        $usuario = $this->usuarioHubAdmin([
            'Listar Pessoas',
            'Criar Pessoas',
            'Gerenciar Vínculos Estruturais de Pessoas',
        ]);
        $this->actingAs($usuario);

        $this->assertArrayNotHasKey(
            ServidorResource::CARGO_MOTORISTA,
            ServidorResource::cargoOptions(),
        );
        Livewire::actingAs($usuario)
            ->test(PessoaForm::class, ['pessoaId' => null])
            ->assertDontSeeHtml('value="motorista"');

        $motorista = Servidor::query()->create([
            'nome' => 'Motorista da rede',
            'cpf' => '98765432100',
            'email' => null,
            'telefone' => '(44) 99999-0000',
            'status' => Servidor::STATUS_ATIVO,
            'matricula' => 'MOT-123',
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $motorista->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::motoristaPadrao()->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'legado',
            'data_inicio' => now()->subYear()->toDateString(),
        ]);

        $this->assertNull($motorista->user_id);
        $this->assertNull($motorista->id_escola);
        $this->assertNull($motorista->setor_id);
        $this->assertNull($motorista->email);
        $this->assertSame('MOT-123', $motorista->matricula);
        $this->assertTrue(ServidorResource::ehMotorista($motorista));
        $this->assertSame('Motorista', ServidorResource::cargoLabel($motorista));
    }

    public function test_listagem_nao_exibe_atalho_de_acesso_por_pessoa(): void
    {
        $usuario = $this->usuarioComPermissaoListar();

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->assertTableActionDoesNotExist('gerenciarAcesso');
    }

    public function test_visualizacao_usa_a_mesma_estrutura_de_abas_do_formulario(): void
    {
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Visualização', $setor);
        $servidor = $this->criarServidor('Pessoa Visualização', $escola, $setor);

        $schema = ServidorResource::infolistDetalhesCompletos($servidor);

        $this->assertCount(1, $schema);
        $this->assertInstanceOf(Tabs::class, $schema[0]);
        $this->assertSame('Ficha da pessoa', $schema[0]->getLabel());
        $this->assertSame(['Dados pessoais'], collect($schema[0]->getDefaultChildComponents())
            ->map(fn ($tab): string => (string) $tab->getLabel())
            ->all());
    }

    public function test_visualizacao_exibe_somente_as_turmas_vinculadas_ao_coordenador(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas']);
        $this->actingAs($usuario);
        $setor = $this->criarSetor('Coordenação');
        $escola = $this->criarEscola('Escola Coordenação', $setor);
        $servidor = $this->criarServidor('Pessoa Coordenadora', $escola, $setor);
        $funcao = FuncaoAdministrativa::coordenacaoPadrao();
        $vinculo = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcao->id,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'portaria' => '123/2026',
        ]);
        $serie = Serie::query()->create(['codigo' => 'COORD-SERIE', 'nome' => '2º Ano']);
        $turmaVinculada = Turma::query()->create([
            'codigo' => 'COORD-A', 'nome' => 'Turma A', 'turno' => 'manha',
            'id_serie' => $serie->id, 'id_escola' => $escola->id,
        ]);
        Turma::query()->create([
            'codigo' => 'COORD-B', 'nome' => 'Turma B', 'turno' => 'manha',
            'id_serie' => $serie->id, 'id_escola' => $escola->id,
        ]);
        ServidorFuncaoTurma::query()->create([
            'servidor_funcao_administrativa_id' => $vinculo->id,
            'turma_id' => $turmaVinculada->id,
            'status' => ServidorFuncaoTurma::STATUS_ATIVO,
        ]);

        $grupos = ServidorResource::gruposTurmasCoordenacao($servidor->fresh());
        $schema = ServidorResource::infolistDetalhesCompletos($servidor->fresh());

        $this->assertSame(['2º Ano - Turma A'], $grupos[0]['turmas']);
        $this->assertSame(['Dados pessoais', 'Turmas'], collect($schema[0]->getDefaultChildComponents())
            ->map(fn ($tab): string => (string) $tab->getLabel())
            ->all());
    }

    public function test_visualizacao_de_diretor_e_secretario_nao_exibe_aba_de_turmas(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas']);
        $this->actingAs($usuario);
        $setor = $this->criarSetor('Gestão');
        $escola = $this->criarEscola('Escola Gestão', $setor);

        foreach ([FuncaoAdministrativa::direcaoPadrao(), FuncaoAdministrativa::secretariaPadrao()] as $indice => $funcao) {
            $servidor = $this->criarServidor("Pessoa Gestora {$indice}", $escola, $setor);
            ServidorFuncaoAdministrativa::query()->create([
                'servidor_id' => $servidor->id,
                'funcao_administrativa_id' => $funcao->id,
                'id_escola' => $escola->id,
                'setor_id' => $setor->id,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                'portaria' => '456/2026',
            ]);

            $schema = ServidorResource::infolistDetalhesCompletos($servidor->fresh());
            $this->assertSame(['Dados pessoais'], collect($schema[0]->getDefaultChildComponents())
                ->map(fn ($tab): string => (string) $tab->getLabel())
                ->all());
        }
    }

    public function test_visualizacao_abre_com_vinculo_de_turma_e_componente(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas']);
        $this->actingAs($usuario);
        $setor = $this->criarSetor('Pedagogico');
        $escola = $this->criarEscola('Escola Visualizacao', $setor);
        $servidor = $this->criarServidor('Pessoa Visualizacao', $escola, $setor);
        $professor = Professor::query()->create([
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'VIEW-001',
            'turno' => 'manha',
            'nome' => $servidor->nome,
            'email' => $servidor->email,
            'ativo' => true,
        ]);
        $serie = Serie::query()->create(['codigo' => 'VIEW-SERIE', 'nome' => '1 Ano']);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'VIEW-COMP',
            'nome' => 'Matematica',
        ]);
        $turma = Turma::query()->create([
            'codigo' => 'VIEW-TURMA',
            'nome' => 'Turma A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        TurmaComponenteProfessor::query()->create([
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $grupos = ServidorResource::gruposTurmasComponentes($servidor->fresh());

        $this->assertCount(1, $grupos);
        $this->assertSame('Escola Visualizacao', $grupos[0]['escola']);
        $this->assertSame(['VIEW-001'], $grupos[0]['matriculas']);
        $this->assertSame('1 Ano - Turma A', $grupos[0]['turmas'][0]['nome']);
        $this->assertSame(['Matematica'], $grupos[0]['turmas'][0]['componentes']);

        Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->mountTableAction('view', $servidor)
            ->assertHasNoErrors()
            ->assertDontSee('Escolas / lotações')
            ->assertDontSee('Vínculos funcionais');
    }

    private function usuarioComPermissaoListar(): User
    {
        return $this->usuarioHubAdmin(['Listar Pessoas']);
    }

    /** @param array<int, string> $permissoes */
    private function usuarioHubAdmin(array $permissoes): User
    {
        $permissoesCriadas = collect($permissoes)
            ->map(fn (string $permissao): Permission => $this->garantirPermissao($permissao))
            ->all();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->syncPermissions($permissoesCriadas);

        // Escopo global para listar/editar pessoas no hub de testes.
        $roleAdmin = Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $usuario->assignRole($roleAdmin);

        return $usuario;
    }

    private function garantirPermissao(string $nome): Permission
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Permission::query()->firstOrCreate([
            'name' => $nome,
            'guard_name' => 'web',
        ]);
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);
    }

    private function criarEscola(string $nome, Setor $setor): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }

    private function criarServidor(string $nome, Escola $escola, Setor $setor, ?int $userId = null): Servidor
    {
        return Servidor::query()->create([
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@edu.umuarama.pr.gov.br',
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'user_id' => $userId,
            'status' => Servidor::STATUS_ATIVO,
        ]);
    }
}
