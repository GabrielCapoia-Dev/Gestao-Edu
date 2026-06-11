<?php

namespace Tests\Feature\Setores;

use App\Filament\Admin\Resources\Setors\Pages\ManageSetors;
use App\Models\Enums\SetorAccessCapability;
use App\Models\Escola;
use App\Models\Pedido;
use App\Models\Setor;
use App\Models\SetorAcesso;
use App\Models\TipoManutencao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\PedidoService;
use App\Services\SetorPedidoAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SetorPedidoAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'Listar Pedidos',
            'Editar Pedidos',
            'Encaminhar Pedidos para Setor',
            'Acessar Escopo Global de Setores',
            'Listar Setores',
            'Editar Setores',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_proprio_setor_tem_acesso_basico_mas_nao_encaminhamento(): void
    {
        $setor = $this->setor('Administrativo');
        $user = User::factory()->create(['setor_id' => $setor->id]);
        $access = app(SetorPedidoAccessService::class);

        $this->assertTrue($access->can($user, SetorAccessCapability::LISTAR, $setor->id));
        $this->assertTrue($access->can($user, SetorAccessCapability::EDITAR, $setor->id));
        $this->assertTrue($access->can($user, SetorAccessCapability::CANCELAR, $setor->id));
        $this->assertFalse($access->can($user, SetorAccessCapability::ENCAMINHAR, $setor->id));
    }

    public function test_pai_filho_e_setor_paralelo_exigem_vinculo_explicito(): void
    {
        $root = $this->setor('Geral');
        $administrativo = $this->setor('Administrativo', $root);
        $obras = $this->setor('Obras', $root);
        $servicos = $this->setor('Servicos Publicos', $root);
        $user = User::factory()->create(['setor_id' => $administrativo->id]);
        $access = app(SetorPedidoAccessService::class);

        $this->assertFalse($access->can($user, SetorAccessCapability::LISTAR, $root->id));
        $this->assertFalse($access->can($user, SetorAccessCapability::LISTAR, $obras->id));

        $access->syncCapability($administrativo, SetorAccessCapability::LISTAR, [
            $obras->id,
            $servicos->id,
        ]);
        $access->syncCapability($administrativo, SetorAccessCapability::ENCAMINHAR, [
            $obras->id,
        ]);

        $this->assertTrue($access->can($user, SetorAccessCapability::LISTAR, $obras->id));
        $this->assertTrue($access->can($user, SetorAccessCapability::LISTAR, $servicos->id));
        $this->assertFalse($access->can($user, SetorAccessCapability::EDITAR, $obras->id));
        $this->assertFalse($access->can($user, SetorAccessCapability::CANCELAR, $obras->id));
        $this->assertTrue($access->can($user, SetorAccessCapability::ENCAMINHAR, $obras->id));

        $access->syncCapability($administrativo, SetorAccessCapability::LISTAR, [$obras->id]);

        $this->assertTrue($access->can($user, SetorAccessCapability::LISTAR, $obras->id));
        $this->assertFalse($access->can($user, SetorAccessCapability::LISTAR, $servicos->id));
    }

    public function test_matriz_controla_listagem_edicao_cancelamento_e_encaminhamento(): void
    {
        $administrativo = $this->setor('Administrativo');
        $obras = $this->setor('Obras');
        $servicos = $this->setor('Servicos Publicos');
        $escola = Escola::create([
            'codigo' => 'ESC-1',
            'nome' => 'Escola',
            'setor_id' => $administrativo->id,
            'ativo' => true,
        ]);
        $pedido = $this->pedido($obras, $administrativo, $escola);
        $user = User::factory()->create(['setor_id' => $administrativo->id]);
        $user->givePermissionTo([
            'Listar Pedidos',
            'Editar Pedidos',
            'Encaminhar Pedidos para Setor',
        ]);
        $access = app(SetorPedidoAccessService::class);
        $service = app(PedidoService::class);

        $access->syncCapability($administrativo, SetorAccessCapability::LISTAR, [$obras->id]);

        $this->assertSame([$pedido->id], $service->queryTabela($user)->pluck('id')->all());
        $this->assertFalse($service->podeGerenciarRegistro($pedido, $user));
        $this->assertFalse($service->podeCancelarRegistro($pedido, $user));

        $access->syncCapability($administrativo, SetorAccessCapability::EDITAR, [$obras->id]);
        $access->syncCapability($administrativo, SetorAccessCapability::CANCELAR, [$obras->id]);
        $access->syncCapability($administrativo, SetorAccessCapability::ENCAMINHAR, [$servicos->id]);

        $this->assertTrue($service->podeGerenciarRegistro($pedido, $user));
        $this->assertTrue($service->podeCancelarRegistro($pedido, $user));

        TipoStatus::create([
            'nome' => 'Encaminhado ao Setor',
            'ativo' => true,
            'finaliza_pedido' => false,
            'cancela_pedido' => false,
        ]);

        $service->encaminharParaSetor($pedido, $servicos, $user);

        $this->assertTrue($pedido->fresh()->setor->is($servicos));
        $this->assertSame('Encaminhado ao Setor', $pedido->fresh()->tipoStatus->nome);
    }

    public function test_cancelamento_exige_capacidade_especifica(): void
    {
        $administrativo = $this->setor('Administrativo');
        $obras = $this->setor('Obras');
        $escola = Escola::create([
            'codigo' => 'ESC-4',
            'nome' => 'Escola 4',
            'setor_id' => $administrativo->id,
            'ativo' => true,
        ]);
        $pedido = $this->pedido($obras, $administrativo, $escola);
        $user = User::factory()->create(['setor_id' => $administrativo->id]);
        $user->givePermissionTo('Editar Pedidos');
        $access = app(SetorPedidoAccessService::class);
        $service = app(PedidoService::class);

        TipoStatus::create([
            'nome' => 'Cancelado',
            'ativo' => true,
            'finaliza_pedido' => false,
            'cancela_pedido' => true,
        ]);

        $this->assertFalse($service->cancelarPedido($pedido, $user, 'Sem acesso.'));

        $access->syncCapability($administrativo, SetorAccessCapability::CANCELAR, [$obras->id]);

        $this->assertTrue($service->cancelarPedido($pedido, $user, 'Cancelamento autorizado.'));
        $this->assertSame('Cancelado', $pedido->fresh()->tipoStatus->nome);
    }

    public function test_setor_de_origem_continua_listando_pedido_encaminhado_sem_poder_editar(): void
    {
        $administrativo = $this->setor('Administrativo');
        $obras = $this->setor('Obras');
        $escola = Escola::create([
            'codigo' => 'ESC-2',
            'nome' => 'Escola 2',
            'setor_id' => $administrativo->id,
            'ativo' => true,
        ]);
        $pedido = $this->pedido($obras, $administrativo, $escola);
        $user = User::factory()->create(['setor_id' => $administrativo->id]);
        $user->givePermissionTo(['Listar Pedidos', 'Editar Pedidos']);
        $service = app(PedidoService::class);

        $this->assertSame([$pedido->id], $service->queryTabela($user)->pluck('id')->all());
        $this->assertFalse($service->podeGerenciarRegistro($pedido, $user));
    }

    public function test_escopo_global_ignora_matriz_mas_permissoes_gerais_continuam_obrigatorias(): void
    {
        $administrativo = $this->setor('Administrativo');
        $obras = $this->setor('Obras');
        $escola = Escola::create([
            'codigo' => 'ESC-3',
            'nome' => 'Escola 3',
            'setor_id' => $administrativo->id,
            'ativo' => true,
        ]);
        $pedido = $this->pedido($obras, $administrativo, $escola);
        $user = User::factory()->create(['setor_id' => $administrativo->id]);
        $user->givePermissionTo('Acessar Escopo Global de Setores');
        $service = app(PedidoService::class);

        $this->assertFalse($service->podeListarRegistro($pedido, $user));
        $this->assertFalse($service->podeGerenciarRegistro($pedido, $user));

        $user->givePermissionTo(['Listar Pedidos', 'Editar Pedidos']);

        $this->assertTrue($service->podeListarRegistro($pedido, $user));
        $this->assertTrue($service->podeGerenciarRegistro($pedido, $user));
    }

    public function test_regra_para_o_proprio_setor_e_rejeitada_no_modelo(): void
    {
        $setor = $this->setor('Administrativo');

        $this->expectException(ValidationException::class);

        SetorAcesso::create([
            'setor_origem_id' => $setor->id,
            'setor_alvo_id' => $setor->id,
            'pode_listar' => true,
        ]);
    }

    public function test_formulario_de_setor_persiste_os_quatro_multiselects(): void
    {
        $geral = $this->setor('Geral');
        $administrativo = $this->setor('Administrativo', $geral);
        $obras = $this->setor('Obras', $geral);
        $servicos = $this->setor('Servicos Publicos', $geral);
        $user = User::factory()->create([
            'setor_id' => $administrativo->id,
            'email_approved' => true,
        ]);
        $user->givePermissionTo([
            'Acessar Escopo Global de Setores',
            'Listar Setores',
            'Editar Setores',
        ]);

        Livewire::actingAs($user)
            ->test(ManageSetors::class)
            ->callTableAction('edit', $administrativo, [
                'nome' => $administrativo->nome,
                'parent_id' => $geral->id,
                'status' => 'Ativo',
                'sort_order' => 0,
                'is_default_root' => false,
                'ativo' => true,
                'setoresListaveis' => [$obras->id, $servicos->id],
                'setoresEditaveis' => [$obras->id],
                'setoresCancelaveis' => [$servicos->id],
                'setoresEncaminhaveis' => [$obras->id],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('setor_acessos', [
            'setor_origem_id' => $administrativo->id,
            'setor_alvo_id' => $obras->id,
            'pode_listar' => true,
            'pode_editar' => true,
            'pode_cancelar' => false,
            'pode_encaminhar' => true,
        ]);
        $this->assertDatabaseHas('setor_acessos', [
            'setor_origem_id' => $administrativo->id,
            'setor_alvo_id' => $servicos->id,
            'pode_listar' => true,
            'pode_editar' => false,
            'pode_cancelar' => true,
            'pode_encaminhar' => false,
        ]);
        $this->assertSame([$obras->id], $administrativo->fresh()->encaminha_pedido_para_setor_ids);
    }

    private function setor(string $nome, ?Setor $parent = null): Setor
    {
        return Setor::create([
            'nome' => $nome,
            'parent_id' => $parent?->id,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => $parent === null && ! Setor::query()->where('is_default_root', true)->exists(),
        ]);
    }

    private function pedido(Setor $setorAtual, Setor $setorOrigem, Escola $escola): Pedido
    {
        $solicitante = User::factory()->create([
            'setor_id' => $setorOrigem->id,
            'id_escola' => $escola->id,
        ]);
        $tipo = TipoManutencao::create([
            'nome' => 'Eletrica '.uniqid(),
            'ativo' => true,
        ]);
        $status = TipoStatus::create([
            'nome' => 'Em Aberto '.uniqid(),
            'ativo' => true,
            'finaliza_pedido' => false,
            'cancela_pedido' => false,
        ]);

        return Pedido::create([
            'tipo_manutencao_id' => $tipo->id,
            'tipo_status_id' => $status->id,
            'descricao_pedido' => 'Pedido para teste da matriz.',
            'nome_solicitante' => 'Teste',
            'solicitante_id' => $solicitante->id,
            'escola_id' => $escola->id,
            'setor_id' => $setorAtual->id,
            'setor_origem_id' => $setorOrigem->id,
            'data_solicitacao' => now(),
            'data_identificacao_problema' => now(),
            'ativo' => true,
        ]);
    }
}
