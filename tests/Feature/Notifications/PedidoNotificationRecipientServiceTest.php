<?php

namespace Tests\Feature\Notifications;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Pedido;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\User;
use App\Policies\NotificationPolicy;
use App\Services\PedidoNotificationRecipientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PedidoNotificationRecipientServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate(PedidoNotificationRecipientService::PERMISSAO_LISTAR_PEDIDOS, 'web');
    }

    public function test_destinatarios_exigem_listar_pedidos_e_vinculo_com_a_escola_do_pedido(): void
    {
        [$escolaA, $setorA] = $this->criarEscola('Escola Pedidos A');
        [$escolaB, $setorB] = $this->criarEscola('Escola Pedidos B');

        $vinculadoA = $this->criarUsuarioVinculado($escolaA, $setorA, 'Vinculado A');
        $vinculadoB = $this->criarUsuarioVinculado($escolaB, $setorB, 'Vinculado B');
        $semPermissao = $this->criarUsuarioVinculado($escolaA, $setorA, 'Sem Permissão');
        $semVinculo = User::factory()->create(['name' => 'Sem Vínculo', 'id_escola' => null]);

        foreach ([$vinculadoA, $vinculadoB, $semVinculo] as $user) {
            $user->givePermissionTo(PedidoNotificationRecipientService::PERMISSAO_LISTAR_PEDIDOS);
        }

        $pedido = new Pedido(['escola_id' => $escolaA->id]);
        $service = app(PedidoNotificationRecipientService::class);

        $this->assertSame(
            [$vinculadoA->id],
            $service->destinatarios($pedido)->pluck('id')->all(),
        );
        $this->assertFalse($service->podeReceber($vinculadoB, $pedido));
        $this->assertFalse($service->podeReceber($semPermissao, $pedido));
        $this->assertFalse($service->podeReceber($semVinculo, $pedido));
    }

    public function test_central_nao_exige_permissao_especifica_de_notificacoes_para_usuario_com_pedidos_da_escola(): void
    {
        [$escola, $setor] = $this->criarEscola('Escola Central');
        $vinculado = $this->criarUsuarioVinculado($escola, $setor, 'Usuário da Central');
        $semVinculo = User::factory()->create(['name' => 'Usuário sem Escola', 'id_escola' => null]);

        foreach ([$vinculado, $semVinculo] as $user) {
            $user->givePermissionTo(PedidoNotificationRecipientService::PERMISSAO_LISTAR_PEDIDOS);
        }

        $policy = app(NotificationPolicy::class);

        $this->assertTrue($policy->viewAny($vinculado));
        $this->assertFalse($policy->viewAny($semVinculo));
        $this->assertFalse($vinculado->hasPermissionTo('Visualizar Notificações'));
    }

    /** @return array{0: Escola, 1: Setor} */
    private function criarEscola(string $nome): array
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor '.$nome,
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);

        $escola = Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'ativo' => true,
        ]);

        return [$escola, $setor];
    }

    private function criarUsuarioVinculado(Escola $escola, Setor $setor, string $nome): User
    {
        $user = User::factory()->create(['name' => $nome]);
        $servidor = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $nome,
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::direcaoPadrao()->id,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'portaria' => 'PORT-'.strtoupper(substr(md5($nome), 0, 6)),
            'principal' => true,
            'data_inicio' => now()->toDateString(),
        ]);

        return $user;
    }
}
