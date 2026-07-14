<?php

namespace Tests\Feature\Pessoas;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\PessoaMatricula;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditarEquipeGestoraCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_migra_secretario_legado_inequivoco_somente_no_modo_aplicar_e_sem_inventar_inicio(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roleLegada = Role::query()->create(['name' => 'Secretário', 'guard_name' => 'web']);
        $roleGestora = Role::query()->create(['name' => 'Equipe Gestora', 'guard_name' => 'web']);
        $setor = Setor::query()->create([
            'nome' => 'Setor Escolar',
            'ativo' => true,
            'status' => 'Ativo',
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);
        $escola = Escola::query()->create([
            'codigo' => 'ESC-LEGADO',
            'nome' => 'Escola Legado',
            'setor_id' => $setor->id,
            'email' => 'escola.legado@teste.local',
            'ativo' => true,
        ]);
        $usuario = User::factory()->create(['id_escola' => $escola->id]);
        $usuario->assignRole($roleLegada);
        $pessoa = Servidor::query()->create([
            'user_id' => $usuario->id,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'nome' => 'Secretária Legada',
            'email' => $usuario->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        PessoaMatricula::query()->create([
            'servidor_id' => $pessoa->id,
            'matricula' => 'SEC-001',
            'turno' => 'integral',
        ]);

        Artisan::call('equipe-gestora:sanear');

        $this->assertTrue($usuario->fresh()->hasRole($roleLegada));
        $this->assertFalse($pessoa->vinculosAtivos()
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->secretaria())
            ->exists());

        Artisan::call('equipe-gestora:sanear', ['--aplicar' => true]);

        $vinculo = ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->secretaria())
            ->firstOrFail();
        $this->assertSame(FuncaoAdministrativa::TIPO_SECRETARIA, $vinculo->funcaoAdministrativa->tipoEquipeGestora());
        $this->assertNull($vinculo->data_inicio);
        $this->assertTrue($usuario->fresh()->hasRole($roleGestora));
        $this->assertFalse($usuario->fresh()->hasRole($roleLegada));

        Artisan::call('equipe-gestora:sanear', ['--aplicar' => true]);

        $this->assertSame(1, ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $pessoa->id)
            ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->secretaria())
            ->count());
    }
}
