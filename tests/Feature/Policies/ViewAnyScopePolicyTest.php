<?php

namespace Tests\Feature\Policies;

use App\Filament\Admin\Resources\Escolas\EscolaResource;
use App\Filament\Admin\Resources\Setors\SetorResource;
use App\Models\Escola;
use App\Models\Role;
use App\Models\Setor;
use App\Models\User;
use App\Policies\EscolaPolicy;
use App\Policies\SetorPolicy;
use App\Services\UserSetorAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
}
