<?php

namespace Tests\Feature\Filament;

use App\Filament\Admin\Actions\VincularSetorBulkAction;
use App\Models\Contrato;
use App\Models\EmpresaContratada;
use App\Models\Escola;
use App\Models\PedidoMerenda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VincularSetorBulkActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_visibilidade_em_massa_usa_ability_sem_registro_da_policy(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionsByModel = [
            Escola::class => 'Editar Escolas',
            EmpresaContratada::class => 'Editar Empresa Contratada',
            Contrato::class => 'Editar Contratos',
            PedidoMerenda::class => 'Editar Pedidos: Merenda',
        ];

        $user = User::factory()->create();

        foreach ($permissionsByModel as $model => $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        Auth::login($user);

        foreach (array_keys($permissionsByModel) as $model) {
            $action = VincularSetorBulkAction::make(
                ability: 'update',
                arguments: $model,
            );

            $this->assertTrue($action->isVisible());
        }
    }

    public function test_partial_de_itens_recebe_identificador_da_secao(): void
    {
        $html = view('components.pedidos-merenda.table-partial-items', [
            'itens' => collect(),
            'paginacao' => [
                'de' => 0,
                'ate' => 0,
                'total' => 0,
                'paginaAtual' => 1,
                'totalPaginas' => 1,
            ],
            'secao' => 'parcial',
        ])->render();

        $this->assertStringContainsString("mudarPagina('parcial', 1)", $html);
    }
}
