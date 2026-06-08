<?php

namespace Tests\Feature\Professores;

use App\Filament\Admin\Resources\Professors\Pages\ManageProfessors;
use App\Models\Escola;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProfessorResourceEscolaOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_de_criacao_lista_apenas_escolas_ativas_ordenadas_por_nome(): void
    {
        Permission::findOrCreate('Listar Professores');
        Permission::findOrCreate('Criar Professores');

        $historica = $this->criarEscola('CMEI - Sao Paulo Apostolo', ativo: false);
        $ativaB = $this->criarEscola('CMEI - Tarsila do Amaral');
        $ativaA = $this->criarEscola('CMEI - Sao Francisco de Assis');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Professores', 'Criar Professores']);

        Livewire::actingAs($usuario)
            ->test(ManageProfessors::class)
            ->mountAction('create')
            ->assertSchemaComponentExists('id_escola', null, function ($component) use ($ativaA, $ativaB, $historica): bool {
                $this->assertInstanceOf(Select::class, $component);

                $options = $component->getOptions();

                $this->assertSame([
                    $ativaA->id => $ativaA->nome,
                    $ativaB->id => $ativaB->nome,
                ], $options);
                $this->assertArrayNotHasKey($historica->id, $options);

                return true;
            });
    }

    public function test_modal_de_criacao_exibe_opcoes_de_turno_do_professor(): void
    {
        Permission::findOrCreate('Listar Professores');
        Permission::findOrCreate('Criar Professores');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo(['Listar Professores', 'Criar Professores']);

        Livewire::actingAs($usuario)
            ->test(ManageProfessors::class)
            ->mountAction('create')
            ->assertSchemaComponentExists('turno', null, function ($component): bool {
                $this->assertInstanceOf(Select::class, $component);

                $this->assertSame([
                    'manha' => 'Manhã',
                    'tarde' => 'Tarde',
                    'integral' => 'Integral',
                ], $component->getOptions());

                return true;
            });
    }

    private function criarEscola(string $nome, bool $ativo = true): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome.microtime()), 0, 8)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => $ativo,
        ]);
    }
}
