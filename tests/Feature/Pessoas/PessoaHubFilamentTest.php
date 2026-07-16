<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
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
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\ServidorService;
use Filament\Actions\Action;
use Filament\Schemas\Components\Tabs;
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

    public function test_resource_exibe_entrada_pessoas_no_menu(): void
    {
        $this->assertSame('Pessoas', ServidorResource::getNavigationLabel());
        $this->assertSame('Pessoa', ServidorResource::getModelLabel());
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

    public function test_header_nova_pessoa(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas', 'Criar Pessoas']);

        $actions = Livewire::actingAs($usuario)
            ->test(ManageServidores::class)
            ->instance()
            ->getCachedHeaderActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(Action::class, $actions[0]);
        $this->assertSame('create', $actions[0]->getName());
        $this->assertSame('Nova pessoa', $actions[0]->getLabel());
        $this->assertFalse($actions[0]->isModalSlideOver());
        $this->assertSame('6xl', $actions[0]->getModalWidth());
        $this->assertTrue($actions[0]->isModalHeaderSticky());
        $this->assertStringContainsString(
            'pessoa-modal-window',
            (string) ($actions[0]->getExtraModalWindowAttributes()['class'] ?? ''),
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

    public function test_exclui_pessoa_sem_avaliacoes_e_limpa_lotacoes(): void
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

        $this->assertDatabaseMissing('servidores', ['id' => $servidor->id]);
        $this->assertDatabaseMissing('professores', ['id' => $professor->id]);
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
    }

    public function test_visualizacao_abre_com_vinculo_de_turma_e_componente(): void
    {
        $usuario = $this->usuarioHubAdmin(['Listar Pessoas']);
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
