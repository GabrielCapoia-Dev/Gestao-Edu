<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\ListaPermissoes;
use App\Models\FuncaoAdministrativa;
use App\Models\Permission;
use App\Models\Pessoa;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use App\Models\VeiculoTransporte;
use App\Services\Dashboard\MotoristaTransporteService;
use App\Services\Dashboard\VeiculoTransporteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TransporteCadastrosServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_motorista_reutiliza_pessoa_existente_sem_duplicar_identidade(): void
    {
        $ator = $this->usuarioComPermissao();
        $pessoa = Servidor::query()->create([
            'nome' => 'Maria da Silva',
            'cpf' => '12345678901',
            'telefone' => null,
            'status' => Pessoa::STATUS_ATIVO,
        ]);

        $motorista = app(MotoristaTransporteService::class)->criar($ator, [
            'nome' => 'Maria da Silva',
            'cpf' => '123.456.789-01',
            'telefone' => '(11) 99999-0000',
        ]);

        $this->assertTrue($pessoa->is($motorista));
        $this->assertSame(1, Servidor::query()->where('cpf', '12345678901')->count());
        $this->assertSame('(11) 99999-0000', $motorista->telefone);
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::query()->motorista()->sole()->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'transporte',
        ]);
    }

    public function test_desativar_motorista_encerra_apenas_o_vinculo_funcional(): void
    {
        $ator = $this->usuarioComPermissao();
        $service = app(MotoristaTransporteService::class);
        $motorista = $service->criar($ator, [
            'nome' => 'João Motorista',
            'cpf' => '98765432100',
            'telefone' => null,
        ]);

        $service->desativar($ator, $motorista);

        $this->assertSame(Pessoa::STATUS_ATIVO, $motorista->fresh()->status);
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $motorista->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
            'origem' => 'transporte',
        ]);
    }

    public function test_funcao_de_motorista_inativa_nao_mantem_motorista_como_ativo(): void
    {
        $ator = $this->usuarioComPermissao();
        $service = app(MotoristaTransporteService::class);
        $motorista = $service->criar($ator, [
            'nome' => 'Motorista com função inativa',
            'cpf' => '14725836900',
            'telefone' => null,
        ]);

        FuncaoAdministrativa::query()->motorista()->sole()->update(['ativo' => false]);

        $resultado = $service->query($ator)->findOrFail($motorista->id);

        $this->assertFalse((bool) $resultado->motorista_ativo);
    }

    public function test_veiculo_normaliza_placa_e_nao_pode_ser_excluido(): void
    {
        $ator = $this->usuarioComPermissao();
        $service = app(VeiculoTransporteService::class);

        $veiculo = $service->criar($ator, [
            'placa' => 'abc-1d23',
            'identificacao' => 'Micro-ônibus 01',
            'capacidade_passageiros' => 28,
        ]);

        $this->assertSame('ABC1D23', $veiculo->placa);
        $this->assertTrue($veiculo->ativo);
        $this->assertFalse(Gate::forUser($ator)->allows('delete', $veiculo));

        $service->desativar($ator, $veiculo);

        $this->assertFalse($veiculo->fresh()->ativo);
    }

    public function test_cadastros_de_transporte_exigem_permissao_especifica(): void
    {
        $semPermissao = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(VeiculoTransporteService::class)->criar($semPermissao, [
            'placa' => 'ABC1D23',
            'identificacao' => null,
            'capacidade_passageiros' => 20,
        ]);
    }

    public function test_nao_aceita_placa_duplicada_ou_capacidade_invalida(): void
    {
        $ator = $this->usuarioComPermissao();
        $service = app(VeiculoTransporteService::class);

        $service->criar($ator, [
            'placa' => 'ABC1D23',
            'identificacao' => null,
            'capacidade_passageiros' => 20,
        ]);

        try {
            $service->criar($ator, [
                'placa' => 'ABC-1D23',
                'identificacao' => null,
                'capacidade_passageiros' => 20,
            ]);

            $this->fail('A placa duplicada deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('placa', $exception->errors());
        }

        try {
            $service->criar($ator, [
                'placa' => 'XYZ9Z99',
                'identificacao' => null,
                'capacidade_passageiros' => 0,
            ]);

            $this->fail('A capacidade inválida deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('capacidade_passageiros', $exception->errors());
        }
    }

    private function usuarioComPermissao(): User
    {
        $user = User::factory()->create();
        Permission::findOrCreate(ListaPermissoes::GerenciarTransporteDeEventos->label(), 'web');
        $user->givePermissionTo(ListaPermissoes::GerenciarTransporteDeEventos->label());

        return $user;
    }
}
