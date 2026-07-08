<?php

namespace Tests\Feature\Merenda;

use App\Models\Enums\StatusPedidoMerenda;
use App\Models\PedidoMerenda;
use App\Models\Setor;
use App\Models\User;
use App\Policies\PedidoMerendaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PedidoMerendaAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('Listar Pedidos: Merenda', 'web');
        Permission::findOrCreate('Criar Pedidos: Merenda', 'web');
    }

    public function test_view_any_exige_permissao_de_listagem(): void
    {
        $semPermissao = User::factory()->create();
        $comPermissao = User::factory()->create();
        $comPermissao->givePermissionTo('Listar Pedidos: Merenda');

        $policy = app(PedidoMerendaPolicy::class);

        $this->assertFalse($policy->viewAny($semPermissao));
        $this->assertTrue($policy->viewAny($comPermissao));
    }

    public function test_create_bloqueia_gestor_geral(): void
    {
        $gestorGeral = User::factory()->create(['id_escola' => null]);
        $gestorGeral->givePermissionTo('Criar Pedidos: Merenda');

        $gestorEscolar = User::factory()->create(['id_escola' => 1]);
        $gestorEscolar->givePermissionTo('Criar Pedidos: Merenda');

        $policy = app(PedidoMerendaPolicy::class);

        $this->assertFalse($policy->create($gestorGeral));
        $this->assertTrue($policy->create($gestorEscolar));
    }

    public function test_view_respeita_escopo_de_setor(): void
    {
        $setorA = Setor::query()->create(['nome' => 'Setor A', 'ativo' => true]);
        $setorB = Setor::query()->create(['nome' => 'Setor B', 'ativo' => true]);

        $usuario = User::factory()->create(['setor_id' => $setorA->id]);
        $usuario->givePermissionTo('Listar Pedidos: Merenda');

        $pedidoVisivel = PedidoMerenda::query()->create([
            'setor_id' => $setorA->id,
            'status' => StatusPedidoMerenda::Aguardando,
            'criado_por_id' => $usuario->id,
        ]);

        $pedidoOculto = PedidoMerenda::query()->create([
            'setor_id' => $setorB->id,
            'status' => StatusPedidoMerenda::Aguardando,
            'criado_por_id' => $usuario->id,
        ]);

        $policy = app(PedidoMerendaPolicy::class);

        $this->assertTrue($policy->view($usuario, $pedidoVisivel));
        $this->assertFalse($policy->view($usuario, $pedidoOculto));
    }
}