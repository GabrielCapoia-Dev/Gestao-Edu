<?php

namespace Tests\Feature\Policies;

use App\Filament\Admin\Resources\Escolas\EscolaResource;
use App\Filament\Admin\Resources\Contratos\ContratoResource;
use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Setors\SetorResource;
use App\Filament\Admin\Resources\TipoManutencaos\TipoManutencaoResource;
use App\Models\Contrato;
use App\Models\EmpresaContratada;
use App\Models\Escola;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\User;
use App\Policies\ContratoPolicy;
use App\Policies\EscolaPolicy;
use App\Policies\ServidorPolicy;
use App\Policies\SetorPolicy;
use App\Policies\TipoManutencaoPolicy;
use App\Services\ServidorService;
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

        $comPermissaoEscolas->givePermissionTo('Listar Escolas');
        $comPermissaoSetores->givePermissionTo('Listar Setores');

        $this->assertFalse(app(EscolaPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(SetorPolicy::class)->viewAny($semPermissao));
        $this->assertTrue(app(EscolaPolicy::class)->viewAny($comPermissaoEscolas));
        $this->assertTrue(app(SetorPolicy::class)->viewAny($comPermissaoSetores));
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

    private function criarEmpresa(string $nome, string $cnpj, Setor $setor): EmpresaContratada
    {
        return EmpresaContratada::create([
            'nome' => $nome,
            'cnpj' => $cnpj,
            'setor_id' => $setor->id,
            'ativo' => true,
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
}
