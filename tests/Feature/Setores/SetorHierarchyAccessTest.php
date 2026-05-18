<?php

namespace Tests\Feature\Setores;

use App\Models\Contrato;
use App\Models\EmpresaContratada;
use App\Models\Escola;
use App\Models\Role;
use App\Models\Setor;
use App\Models\User;
use App\Services\UserSetorAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SetorHierarchyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_setor_mantem_path_depth_descendentes_e_bloqueia_ciclo(): void
    {
        $root = Setor::create(['nome' => 'Raiz', 'ativo' => true, 'status' => 'Ativo', 'is_default_root' => true]);
        $area = Setor::create(['nome' => 'Area', 'parent_id' => $root->id, 'ativo' => true, 'status' => 'Ativo']);
        $unidade = Setor::create(['nome' => 'Unidade', 'parent_id' => $area->id, 'ativo' => true, 'status' => 'Ativo']);

        $this->assertSame("/{$root->id}/", $root->fresh()->path);
        $this->assertSame("/{$root->id}/{$area->id}/", $area->fresh()->path);
        $this->assertSame(2, $unidade->fresh()->depth);
        $this->assertEqualsCanonicalizing([$area->id, $unidade->id], $area->selfAndDescendantIds());
        $this->assertTrue($area->isAncestorOf($unidade));

        $this->expectException(ValidationException::class);
        $area->update(['parent_id' => $unidade->id]);
    }

    public function test_user_setor_access_resolve_raiz_intermediario_folha_global_e_fallback_legado(): void
    {
        $root = Setor::create(['nome' => 'Raiz', 'ativo' => true, 'status' => 'Ativo', 'is_default_root' => true]);
        $area = Setor::create(['nome' => 'Area', 'parent_id' => $root->id, 'ativo' => true, 'status' => 'Ativo']);
        $folha = Setor::create(['nome' => 'Folha', 'parent_id' => $area->id, 'ativo' => true, 'status' => 'Ativo']);
        $fora = Setor::create(['nome' => 'Fora', 'parent_id' => $root->id, 'ativo' => true, 'status' => 'Ativo']);

        $access = app(UserSetorAccessService::class);

        $areaUser = User::factory()->create(['setor_id' => $area->id]);
        $this->assertSame([$area->id, $folha->id], $access->visibleSetorIds($areaUser));
        $this->assertTrue($access->canAccessSetor($areaUser, $folha->id));
        $this->assertFalse($access->canAccessSetor($areaUser, $fora->id));

        $escola = Escola::create(['nome' => 'Escola A', 'codigo' => 'EA', 'setor_id' => $folha->id, 'ativo' => true]);
        $schoolUser = User::factory()->create(['id_escola' => $escola->id, 'setor_id' => null]);
        $this->assertSame([$folha->id], $access->visibleSetorIds($schoolUser));

        $legacyRole = Role::create(['name' => 'Legado', 'guard_name' => 'web', 'setor_id' => $fora->id]);
        $legacyUser = User::factory()->create(['setor_id' => null]);
        $legacyUser->assignRole($legacyRole);
        $this->assertSame([$fora->id], $access->visibleSetorIds($legacyUser));

        Permission::findOrCreate(UserSetorAccessService::GLOBAL_SCOPE_PERMISSION, 'web');
        $globalUser = User::factory()->create(['setor_id' => $fora->id]);
        $globalUser->givePermissionTo(UserSetorAccessService::GLOBAL_SCOPE_PERMISSION);
        $this->assertEqualsCanonicalizing([$root->id, $area->id, $folha->id, $fora->id], $access->visibleSetorIds($globalUser));
    }

    public function test_empresas_e_contratos_respeitam_escopo_hierarquico(): void
    {
        $root = Setor::create(['nome' => 'Raiz', 'ativo' => true, 'status' => 'Ativo', 'is_default_root' => true]);
        $area = Setor::create(['nome' => 'Area', 'parent_id' => $root->id, 'ativo' => true, 'status' => 'Ativo']);
        $folha = Setor::create(['nome' => 'Folha', 'parent_id' => $area->id, 'ativo' => true, 'status' => 'Ativo']);
        $fora = Setor::create(['nome' => 'Fora', 'parent_id' => $root->id, 'ativo' => true, 'status' => 'Ativo']);

        $user = User::factory()->create(['setor_id' => $area->id]);
        Auth::login($user);

        $empresaVisivel = EmpresaContratada::create([
            'nome' => 'Empresa Visivel',
            'cnpj' => '12.345.678/0001-90',
            'setor_id' => $folha->id,
            'ativo' => true,
        ]);

        Auth::logout();
        EmpresaContratada::create([
            'nome' => 'Empresa Fora',
            'cnpj' => '98.765.432/0001-10',
            'setor_id' => $fora->id,
            'ativo' => true,
        ]);
        Auth::login($user);

        $this->assertSame(['Empresa Visivel'], EmpresaContratada::query()->doSetorDoUsuario($user)->pluck('nome')->all());

        $contrato = Contrato::create([
            'id_empresa_contratada' => $empresaVisivel->id,
            'numero_contrato' => 'CT-001',
            'data_inicio' => now()->toDateString(),
            'ativo' => true,
        ]);

        $this->assertSame($folha->id, $contrato->fresh()->setor_id);

        $this->expectException(ValidationException::class);
        Contrato::create([
            'id_empresa_contratada' => $empresaVisivel->id,
            'setor_id' => $fora->id,
            'numero_contrato' => 'CT-002',
            'data_inicio' => now()->toDateString(),
            'ativo' => true,
        ]);
    }
}
