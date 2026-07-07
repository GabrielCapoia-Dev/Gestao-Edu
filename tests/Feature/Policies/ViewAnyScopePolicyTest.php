<?php

namespace Tests\Feature\Policies;

use App\Filament\Admin\Resources\EmpresaContratadas\EmpresaContratadaResource;
use App\Filament\Admin\Resources\Escolas\EscolaResource;
use App\Filament\Admin\Resources\Contratos\ContratoResource;
use App\Filament\Admin\Resources\Professors\ProfessorResource;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Setors\SetorResource;
use App\Filament\Admin\Resources\TipoManutencaos\TipoManutencaoResource;
use App\Filament\Admin\Resources\Turmas\TurmaResource;
use App\Models\ComponenteCurricular;
use App\Models\Contrato;
use App\Models\EmpresaContratada;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Policies\ContratoPolicy;
use App\Policies\EmpresaContratadaPolicy;
use App\Policies\EscolaPolicy;
use App\Policies\ProfessorPolicy;
use App\Policies\ServidorPolicy;
use App\Policies\SetorPolicy;
use App\Policies\TipoManutencaoPolicy;
use App\Policies\TurmaPolicy;
use App\Services\ServidorService;
use App\Services\UserService;
use App\Services\UserSetorAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ViewAnyScopePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'Listar Escolas',
            'Listar Setores',
            'Listar Contratos',
            'Listar Servidores',
            'Listar Empresa Contratada',
            'Listar Turmas',
            'Listar Professores',
            UserSetorAccessService::GLOBAL_SCOPE_PERMISSION,
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_view_any_respeita_permissoes_de_listagem(): void
    {
        $semPermissao = User::factory()->create();
        $comPermissaoEscolas = User::factory()->create();
        $comPermissaoSetores = User::factory()->create();
        $comPermissaoEmpresas = User::factory()->create();
        $comPermissaoTurmas = User::factory()->create();
        $comPermissaoProfessores = User::factory()->create();

        $comPermissaoEscolas->givePermissionTo('Listar Escolas');
        $comPermissaoSetores->givePermissionTo('Listar Setores');
        $comPermissaoEmpresas->givePermissionTo('Listar Empresa Contratada');
        $comPermissaoTurmas->givePermissionTo('Listar Turmas');
        $comPermissaoProfessores->givePermissionTo('Listar Professores');

        $this->assertFalse(app(EscolaPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(SetorPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(EmpresaContratadaPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(TurmaPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(ProfessorPolicy::class)->viewAny($semPermissao));
        $this->assertTrue(app(EscolaPolicy::class)->viewAny($comPermissaoEscolas));
        $this->assertTrue(app(SetorPolicy::class)->viewAny($comPermissaoSetores));
        $this->assertTrue(app(EmpresaContratadaPolicy::class)->viewAny($comPermissaoEmpresas));
        $this->assertTrue(app(TurmaPolicy::class)->viewAny($comPermissaoTurmas));
        $this->assertTrue(app(ProfessorPolicy::class)->viewAny($comPermissaoProfessores));
    }

    public function test_policy_scope_restringe_escolas_por_setor_visivel(): void
    {
        [$area, $folha, $fora] = $this->criarHierarquiaSetores();

        $escolaArea = $this->criarEscola('ESC-AREA', 'Escola Area', $area);
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);
        $this->criarEscola('ESC-FORA', 'Escola Fora', $fora);
        $this->criarEscola('ESC-INATIVA', 'Escola Inativa', $area, false);

        $user = User::factory()->create(['setor_id' => $area->id]);
        $user->givePermissionTo('Listar Escolas');

        $ids = app(EscolaPolicy::class)
            ->applyViewAnyScope($user, Escola::query())
            ->orderBy('codigo')
            ->pluck('id')
            ->all();

        $this->assertSame([$escolaArea->id, $escolaFolha->id], $ids);
    }

    public function test_policy_scope_restringe_setores_por_hierarquia_visivel(): void
    {
        [$area, $folha] = $this->criarHierarquiaSetores();

        $user = User::factory()->create(['setor_id' => $area->id]);
        $user->givePermissionTo('Listar Setores');

        $ids = app(SetorPolicy::class)
            ->applyViewAnyScope($user, Setor::query())
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$area->id, $folha->id], $ids);
    }

    public function test_policy_scope_global_mantem_todos_os_registros_ativos(): void
    {
        [$area, $folha, $fora] = $this->criarHierarquiaSetores();
        $rootId = (int) Setor::query()->where('is_default_root', true)->value('id');

        $escolaArea = $this->criarEscola('ESC-AREA', 'Escola Area', $area);
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);
        $escolaFora = $this->criarEscola('ESC-FORA', 'Escola Fora', $fora);
        $this->criarEscola('ESC-INATIVA', 'Escola Inativa', $fora, false);

        $global = User::factory()->create(['setor_id' => $area->id]);
        $global->givePermissionTo([
            'Listar Escolas',
            'Listar Setores',
            UserSetorAccessService::GLOBAL_SCOPE_PERMISSION,
        ]);

        $this->assertEqualsCanonicalizing(
            [$escolaArea->id, $escolaFolha->id, $escolaFora->id],
            app(EscolaPolicy::class)->applyViewAnyScope($global, Escola::query())->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$rootId, $area->id, $folha->id, $fora->id],
            app(SetorPolicy::class)->applyViewAnyScope($global, Setor::query())->pluck('id')->all(),
        );
    }

    public function test_policy_scope_admin_mantem_todos_os_registros_ativos(): void
    {
        [$area, $folha, $fora] = $this->criarHierarquiaSetores();
        $rootId = (int) Setor::query()->where('is_default_root', true)->value('id');

        $escolaArea = $this->criarEscola('ESC-AREA', 'Escola Area', $area);
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);
        $escolaFora = $this->criarEscola('ESC-FORA', 'Escola Fora', $fora);

        $adminRole = Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create(['setor_id' => $area->id]);
        $admin->assignRole($adminRole);

        $this->assertEqualsCanonicalizing(
            [$escolaArea->id, $escolaFolha->id, $escolaFora->id],
            app(EscolaPolicy::class)->applyViewAnyScope($admin, Escola::query())->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$rootId, $area->id, $folha->id, $fora->id],
            app(SetorPolicy::class)->applyViewAnyScope($admin, Setor::query())->pluck('id')->all(),
        );
    }

    public function test_resource_query_equivale_ao_escopo_anterior(): void
    {
        [$area, $folha] = $this->criarHierarquiaSetores();

        $this->criarEscola('ESC-AREA', 'Escola Area', $area);
        $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);

        $user = User::factory()->create(['setor_id' => $area->id]);
        $user->givePermissionTo(['Listar Escolas', 'Listar Setores']);

        Auth::login($user);

        $access = app(UserSetorAccessService::class);

        $this->assertSame(
            $access->applySetorScope(Escola::query()->where('ativo', true), $user)->orderBy('id')->pluck('id')->all(),
            EscolaResource::getEloquentQuery()->orderBy('id')->pluck('id')->all(),
        );

        $this->assertSame(
            $access->applySetorScope(Setor::query()->where('ativo', true), $user, 'id')->orderBy('id')->pluck('id')->all(),
            SetorResource::getEloquentQuery()->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_policy_scope_restringe_contratos_por_setor_visivel_e_fallback_legado_da_empresa(): void
    {
        [$area, $folha, $fora] = $this->criarHierarquiaSetores();

        $empresaFolha = $this->criarEmpresa('Empresa Folha', '12.345.678/0001-90', $folha);
        $empresaFora = $this->criarEmpresa('Empresa Fora', '98.765.432/0001-10', $fora);

        $contratoDireto = $this->criarContrato('CT-DIRETO', $empresaFolha, $folha);
        $contratoLegadoId = DB::table('contratos')->insertGetId([
            'id_empresa_contratada' => $empresaFolha->id,
            'setor_id' => null,
            'numero_contrato' => 'CT-LEGADO',
            'data_inicio' => now()->toDateString(),
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->criarContrato('CT-FORA', $empresaFora, $fora);

        $user = User::factory()->create(['setor_id' => $area->id]);
        $user->givePermissionTo('Listar Contratos');

        $ids = app(ContratoPolicy::class)
            ->applyViewAnyScope($user, Contrato::query())
            ->orderBy('numero_contrato')
            ->pluck('id')
            ->all();

        $this->assertSame([$contratoDireto->id, $contratoLegadoId], $ids);
    }

    public function test_resource_query_de_contratos_equivale_ao_escopo_da_policy(): void
    {
        [$area, $folha] = $this->criarHierarquiaSetores();

        $empresa = $this->criarEmpresa('Empresa Folha', '12.345.678/0001-90', $folha);
        $this->criarContrato('CT-001', $empresa, $folha);

        $user = User::factory()->create(['setor_id' => $area->id]);
        $user->givePermissionTo('Listar Contratos');

        Auth::login($user);

        $this->assertSame(
            app(ContratoPolicy::class)
                ->applyViewAnyScope($user, Contrato::query()->with(['setor', 'empresaContratada']))
                ->orderBy('id')
                ->pluck('id')
                ->all(),
            ContratoResource::getEloquentQuery()->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_policy_scope_de_servidores_delega_para_service_existente(): void
    {
        [$area, $folha, $fora] = $this->criarHierarquiaSetores();
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);
        $escolaFora = $this->criarEscola('ESC-FORA', 'Escola Fora', $fora);

        $servidorArea = $this->criarServidor('Servidor Area', $area);
        $servidorEscolaFolha = $this->criarServidor('Servidor Escola Folha', null, $escolaFolha);
        $this->criarServidor('Servidor Fora', $fora, $escolaFora);

        $user = User::factory()->create(['setor_id' => $area->id]);
        $user->givePermissionTo('Listar Servidores');

        $this->assertSame(
            app(ServidorService::class)->aplicarEscopoVisibilidade(Servidor::query(), $user)->orderBy('nome')->pluck('id')->all(),
            app(ServidorPolicy::class)->applyViewAnyScope($user, Servidor::query())->orderBy('nome')->pluck('id')->all(),
        );

        $this->assertSame(
            [$servidorArea->id, $servidorEscolaFolha->id],
            app(ServidorPolicy::class)->applyViewAnyScope($user, Servidor::query())->orderBy('nome')->pluck('id')->all(),
        );
    }

    public function test_resource_query_de_servidores_equivale_ao_escopo_da_policy(): void
    {
        [$area, $folha] = $this->criarHierarquiaSetores();
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);

        $this->criarServidor('Servidor Escola Folha', null, $escolaFolha);

        $user = User::factory()->create(['setor_id' => $area->id]);
        $user->givePermissionTo('Listar Servidores');

        Auth::login($user);

        $this->assertSame(
            app(ServidorPolicy::class)->applyViewAnyScope($user, Servidor::query())->orderBy('id')->pluck('id')->all(),
            ServidorResource::getEloquentQuery()->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_policy_scope_e_resource_de_tipo_manutencao_listam_apenas_ativos(): void
    {
        $ativo = TipoManutencao::create(['nome' => 'Ativo', 'ativo' => true]);
        TipoManutencao::create(['nome' => 'Inativo', 'ativo' => false]);

        $user = User::factory()->create();
        Auth::login($user);

        $this->assertSame(
            [$ativo->id],
            app(TipoManutencaoPolicy::class)->applyViewAnyScope($user, TipoManutencao::query())->pluck('id')->all(),
        );

        $this->assertSame(
            [$ativo->id],
            TipoManutencaoResource::getEloquentQuery()->pluck('id')->all(),
        );
    }

    public function test_policy_scope_de_empresas_contratadas_restringe_por_setor_visivel(): void
    {
        [$area, $folha, $fora] = $this->criarHierarquiaSetores();

        $empresaFolha = $this->criarEmpresa('Empresa Folha', '12.345.678/0001-90', $folha);
        $empresaInativaFolha = $this->criarEmpresa('Empresa Inativa Folha', '12.345.678/0001-91', $folha, false);
        $this->criarEmpresa('Empresa Fora', '98.765.432/0001-10', $fora);

        $user = User::factory()->create(['setor_id' => $area->id]);
        $user->givePermissionTo('Listar Empresa Contratada');

        $this->assertSame(
            app(UserSetorAccessService::class)
                ->applySetorScope(EmpresaContratada::query(), $user)
                ->orderBy('nome')
                ->pluck('id')
                ->all(),
            app(EmpresaContratadaPolicy::class)
                ->applyViewAnyScope($user, EmpresaContratada::query())
                ->orderBy('nome')
                ->pluck('id')
                ->all(),
        );

        $this->assertSame(
            [$empresaFolha->id, $empresaInativaFolha->id],
            app(EmpresaContratadaPolicy::class)
                ->applyViewAnyScope($user, EmpresaContratada::query())
                ->orderBy('nome')
                ->pluck('id')
                ->all(),
        );
    }

    public function test_resource_query_de_empresas_contratadas_equivale_ao_escopo_anterior(): void
    {
        [$area, $folha] = $this->criarHierarquiaSetores();

        $this->criarEmpresa('Empresa Folha', '12.345.678/0001-90', $folha);

        $user = User::factory()->create(['setor_id' => $area->id]);
        $user->givePermissionTo('Listar Empresa Contratada');

        Auth::login($user);

        $this->assertSame(
            app(UserSetorAccessService::class)
                ->applySetorScope(EmpresaContratada::query(), $user)
                ->orderBy('id')
                ->pluck('id')
                ->all(),
            EmpresaContratadaResource::getEloquentQuery()
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        );
    }

    public function test_policy_scope_de_turmas_equivale_ao_service_para_usuario_de_escola(): void
    {
        [$area, $folha, $fora] = $this->criarHierarquiaSetores();
        $serie = $this->criarSerie();
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);
        $escolaFora = $this->criarEscola('ESC-FORA', 'Escola Fora', $fora);

        $turmaFolha = $this->criarTurma('TUR-FOLHA', $escolaFolha, $serie);
        $this->criarTurma('TUR-FORA', $escolaFora, $serie);

        $user = User::factory()->create([
            'setor_id' => $area->id,
            'id_escola' => $escolaFolha->id,
        ]);
        $user->givePermissionTo('Listar Turmas');

        $expected = Turma::query();
        app(UserService::class)->aplicarFiltroPorEscolaDoUsuarioEmTurma($expected, $user);

        $this->assertSame(
            $expected->orderBy('codigo')->pluck('id')->all(),
            app(TurmaPolicy::class)
                ->applyViewAnyScope($user, Turma::query())
                ->orderBy('codigo')
                ->pluck('id')
                ->all(),
        );

        $this->assertSame(
            [$turmaFolha->id],
            app(TurmaPolicy::class)
                ->applyViewAnyScope($user, Turma::query())
                ->orderBy('codigo')
                ->pluck('id')
                ->all(),
        );
    }

    public function test_policy_scope_de_turmas_equivale_ao_service_para_professor_vinculado(): void
    {
        [, $folha, $fora] = $this->criarHierarquiaSetores();
        $serie = $this->criarSerie();
        $componente = $this->criarComponenteCurricular();
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);
        $escolaFora = $this->criarEscola('ESC-FORA', 'Escola Fora', $fora);

        $turmaVinculada = $this->criarTurma('TUR-VINCULADA', $escolaFolha, $serie);
        $this->criarTurma('TUR-FORA', $escolaFora, $serie);

        $user = User::factory()->create();
        $user->givePermissionTo('Listar Turmas');
        $professor = $this->criarProfessor('Professor Vinculado', $escolaFolha, true, $user);
        $this->vincularProfessorTurma($turmaVinculada, $componente, $professor);

        $expected = Turma::query();
        app(UserService::class)->aplicarFiltroPorEscolaDoUsuarioEmTurma($expected, $user);

        $this->assertSame(
            $expected->orderBy('codigo')->pluck('id')->all(),
            app(TurmaPolicy::class)
                ->applyViewAnyScope($user, Turma::query())
                ->orderBy('codigo')
                ->pluck('id')
                ->all(),
        );

        $this->assertSame(
            [$turmaVinculada->id],
            app(TurmaPolicy::class)
                ->applyViewAnyScope($user, Turma::query())
                ->orderBy('codigo')
                ->pluck('id')
                ->all(),
        );
    }

    public function test_resource_query_de_turmas_equivale_ao_escopo_da_policy(): void
    {
        [, $folha] = $this->criarHierarquiaSetores();
        $serie = $this->criarSerie();
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);

        $this->criarTurma('TUR-FOLHA', $escolaFolha, $serie);

        $user = User::factory()->create(['id_escola' => $escolaFolha->id]);
        $user->givePermissionTo('Listar Turmas');

        Auth::login($user);

        $this->assertSame(
            app(TurmaPolicy::class)
                ->applyViewAnyScope($user, Turma::query())
                ->orderBy('id')
                ->pluck('id')
                ->all(),
            TurmaResource::getEloquentQuery()
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        );
    }

    public function test_policy_scope_de_professores_lista_ativos_e_respeita_escola(): void
    {
        [, $folha, $fora] = $this->criarHierarquiaSetores();
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);
        $escolaFora = $this->criarEscola('ESC-FORA', 'Escola Fora', $fora);

        $professorAtivoFolha = $this->criarProfessor('Professor Ativo Folha', $escolaFolha);
        $this->criarProfessor('Professor Inativo Folha', $escolaFolha, false);
        $this->criarProfessor('Professor Ativo Fora', $escolaFora);

        $user = User::factory()->create(['id_escola' => $escolaFolha->id]);
        $user->givePermissionTo('Listar Professores');

        $expected = Professor::query()->where('ativo', true);
        app(UserService::class)->aplicarFiltroPorEscolaDoUsuarioEmTurma($expected, $user);

        $this->assertSame(
            $expected->orderBy('nome')->pluck('id')->all(),
            app(ProfessorPolicy::class)
                ->applyViewAnyScope($user, Professor::query())
                ->orderBy('nome')
                ->pluck('id')
                ->all(),
        );

        $this->assertSame(
            [$professorAtivoFolha->id],
            app(ProfessorPolicy::class)
                ->applyViewAnyScope($user, Professor::query())
                ->orderBy('nome')
                ->pluck('id')
                ->all(),
        );
    }

    public function test_resource_query_de_professores_equivale_ao_escopo_da_policy(): void
    {
        [, $folha] = $this->criarHierarquiaSetores();
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);

        $this->criarProfessor('Professor Ativo Folha', $escolaFolha);

        $user = User::factory()->create(['id_escola' => $escolaFolha->id]);
        $user->givePermissionTo('Listar Professores');

        Auth::login($user);

        $this->assertSame(
            app(ProfessorPolicy::class)
                ->applyViewAnyScope($user, Professor::query())
                ->orderBy('id')
                ->pluck('id')
                ->all(),
            ProfessorResource::getEloquentQuery()
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        );
    }

    public function test_policy_scope_admin_mantem_comportamento_atual_dos_novos_dominios(): void
    {
        [, $folha, $fora] = $this->criarHierarquiaSetores();
        $serie = $this->criarSerie();
        $escolaFolha = $this->criarEscola('ESC-FOLHA', 'Escola Folha', $folha);
        $escolaFora = $this->criarEscola('ESC-FORA', 'Escola Fora', $fora);

        $empresaFolha = $this->criarEmpresa('Empresa Folha', '12.345.678/0001-90', $folha);
        $empresaFora = $this->criarEmpresa('Empresa Fora', '98.765.432/0001-10', $fora);
        $turmaFolha = $this->criarTurma('TUR-FOLHA', $escolaFolha, $serie);
        $turmaFora = $this->criarTurma('TUR-FORA', $escolaFora, $serie);
        $professorFolha = $this->criarProfessor('Professor Folha', $escolaFolha);
        $professorFora = $this->criarProfessor('Professor Fora', $escolaFora);
        $this->criarProfessor('Professor Inativo', $escolaFora, false);

        $adminRole = Role::findOrCreate('Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $this->assertEqualsCanonicalizing(
            [$empresaFolha->id, $empresaFora->id],
            app(EmpresaContratadaPolicy::class)->applyViewAnyScope($admin, EmpresaContratada::query())->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$turmaFolha->id, $turmaFora->id],
            app(TurmaPolicy::class)->applyViewAnyScope($admin, Turma::query())->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$professorFolha->id, $professorFora->id],
            app(ProfessorPolicy::class)->applyViewAnyScope($admin, Professor::query())->pluck('id')->all(),
        );
    }

    /**
     * @return array{0: Setor, 1: Setor, 2: Setor}
     */
    private function criarHierarquiaSetores(): array
    {
        $root = Setor::create([
            'nome' => 'Raiz',
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
        ]);

        $area = Setor::create([
            'nome' => 'Area',
            'parent_id' => $root->id,
            'ativo' => true,
            'status' => 'Ativo',
        ]);

        $folha = Setor::create([
            'nome' => 'Folha',
            'parent_id' => $area->id,
            'ativo' => true,
            'status' => 'Ativo',
        ]);

        $fora = Setor::create([
            'nome' => 'Fora',
            'parent_id' => $root->id,
            'ativo' => true,
            'status' => 'Ativo',
        ]);

        return [$area, $folha, $fora];
    }

    private function criarEscola(string $codigo, string $nome, Setor $setor, bool $ativa = true): Escola
    {
        return Escola::create([
            'codigo' => $codigo,
            'nome' => $nome,
            'setor_id' => $setor->id,
            'ativo' => $ativa,
        ]);
    }

    private function criarEmpresa(string $nome, string $cnpj, Setor $setor, bool $ativa = true): EmpresaContratada
    {
        return EmpresaContratada::create([
            'nome' => $nome,
            'cnpj' => $cnpj,
            'setor_id' => $setor->id,
            'ativo' => $ativa,
        ]);
    }

    private function criarContrato(string $numero, EmpresaContratada $empresa, Setor $setor): Contrato
    {
        return Contrato::create([
            'id_empresa_contratada' => $empresa->id,
            'setor_id' => $setor->id,
            'numero_contrato' => $numero,
            'data_inicio' => now()->toDateString(),
            'ativo' => true,
        ]);
    }

    private function criarServidor(string $nome, ?Setor $setor = null, ?Escola $escola = null): Servidor
    {
        return Servidor::create([
            'nome' => $nome,
            'matricula' => 'MAT-'.str_replace(' ', '-', strtoupper($nome)),
            'setor_id' => $setor?->id,
            'id_escola' => $escola?->id,
            'status' => Servidor::STATUS_ATIVO,
        ]);
    }

    private function criarSerie(string $codigo = 'SER-001', string $nome = 'Serie Teste'): Serie
    {
        return Serie::create([
            'codigo' => $codigo,
            'nome' => $nome,
        ]);
    }

    private function criarTurma(string $codigo, Escola $escola, Serie $serie): Turma
    {
        return Turma::create([
            'codigo' => $codigo,
            'nome' => $codigo,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarComponenteCurricular(string $codigo = 'COMP-001', string $nome = 'Componente Teste'): ComponenteCurricular
    {
        return ComponenteCurricular::create([
            'codigo' => $codigo,
            'nome' => $nome,
        ]);
    }

    private function criarProfessor(
        string $nome,
        Escola $escola,
        bool $ativo = true,
        ?User $user = null,
    ): Professor {
        return Professor::create([
            'id_escola' => $escola->id,
            'user_id' => $user?->id,
            'matricula' => 'PROF-'.str_replace(' ', '-', strtoupper($nome)),
            'turno' => 'manha',
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@edu.umuarama.pr.gov.br',
            'telefone' => null,
            'ativo' => $ativo,
        ]);
    }

    private function vincularProfessorTurma(
        Turma $turma,
        ComponenteCurricular $componente,
        Professor $professor,
    ): TurmaComponenteProfessor {
        return TurmaComponenteProfessor::create([
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);
    }
}
