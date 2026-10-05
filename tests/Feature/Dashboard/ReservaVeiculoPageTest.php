<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Admin\Resources\ReservasVeiculos\Pages\ManageReservasVeiculos;
use App\Filament\Admin\Resources\ReservasVeiculos\ReservaVeiculoResource;
use App\Models\Enums\ListaPermissoes;
use App\Models\Escola;
use App\Models\Permission;
use App\Models\User;
use App\Models\VeiculoTransporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReservaVeiculoPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessoria_cria_reserva_pela_action_da_pagina(): void
    {
        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        foreach ([
            ListaPermissoes::ListarReservasVeiculos,
            ListaPermissoes::CriarReservasVeiculos,
            ListaPermissoes::EditarReservasVeiculos,
            ListaPermissoes::CancelarReservasVeiculos,
            ListaPermissoes::GerenciarFrotaVeiculos,
        ] as $permissao) {
            Permission::findOrCreate($permissao->label(), 'web');
            $usuario->givePermissionTo($permissao->label());
        }

        $escola = Escola::query()->create([
            'codigo' => 'ESC-PAGE-RESERVA',
            'nome' => 'CMEI da Reserva',
            'ativo' => true,
        ]);
        $veiculo = VeiculoTransporte::query()->create([
            'placa' => 'JKL1M23',
            'identificacao' => 'Carro da Assessoria',
            'capacidade_passageiros' => 5,
            'ativo' => true,
        ]);

        $componente = Livewire::actingAs($usuario)
            ->test(ManageReservasVeiculos::class)
            ->assertActionVisible('nova_reserva')
            ->assertActionVisible('gerenciar_frota')
            ->mountAction('nova_reserva');

        $componente
            ->assertFormFieldDoesNotExist('repetir_a_cada')
            ->assertFormFieldDoesNotExist('unidade_repeticao');

        $componente
            ->setActionData([
                'data_inicial' => today()->addDay()->toDateString(),
                'repeticao' => 'diaria',
                'fim_repeticao' => 'data',
                'data_final' => today()->addDays(2)->toDateString(),
                'hora_inicio' => '09:00',
                'hora_fim' => '11:00',
                'tipo_local' => 'escola',
                'escola_ids' => [$escola->id],
                'local_outro' => null,
                'atividade' => 'Acompanhamento no CMEI',
                'veiculo_transporte_id' => $veiculo->id,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('reservas_veiculos', 2);
        $this->assertDatabaseHas('reservas_veiculos', [
            'usuario_id' => $usuario->id,
            'escola_id' => $escola->id,
            'veiculo_transporte_id' => $veiculo->id,
            'local_nome' => 'CMEI da Reserva',
            'atividade' => 'Acompanhamento no CMEI',
            'status' => 'ativa',
        ]);
        $this->assertFalse(ReservaVeiculoResource::shouldRegisterNavigation());
    }
}
