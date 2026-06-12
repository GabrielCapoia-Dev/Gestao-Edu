<?php

namespace Tests\Feature\Professores;

use App\Filament\Admin\Resources\Professors\Pages\ManageProfessors;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
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

    public function test_listagem_pesquisa_componente_e_filtra_por_turno_e_turma(): void
    {
        Permission::findOrCreate('Listar Professores');

        $escola = $this->criarEscola('Escola Professores Busca');
        $serie = Serie::query()->create([
            'codigo' => 'SER-PROF-BUSCA',
            'nome' => 'Serie Professores Busca',
        ]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-PROF-BUSCA',
            'nome' => 'Componente Exclusivo Busca',
        ]);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-PROF-BUSCA',
            'nome' => 'Turma Exclusiva',
            'turno' => 'tarde',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);

        $professorVinculado = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-BUSCA-1',
            'turno' => 'tarde',
            'nome' => 'Professor Vinculado',
            'email' => 'professor.vinculado@edu.umuarama.pr.gov.br',
        ]);
        $professorSemVinculo = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-BUSCA-2',
            'turno' => 'manha',
            'nome' => 'Professor Sem Vinculo',
            'email' => 'professor.sem.vinculo@edu.umuarama.pr.gov.br',
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professorVinculado->id,
            'tem_professor' => true,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Professores');

        Livewire::actingAs($usuario)
            ->test(ManageProfessors::class)
            ->searchTable('Componente Exclusivo')
            ->assertCanSeeTableRecords([$professorVinculado])
            ->assertCanNotSeeTableRecords([$professorSemVinculo]);

        Livewire::actingAs($usuario)
            ->test(ManageProfessors::class)
            ->searchTable('Manhã')
            ->assertCanSeeTableRecords([$professorSemVinculo])
            ->assertCanNotSeeTableRecords([$professorVinculado]);

        Livewire::actingAs($usuario)
            ->test(ManageProfessors::class)
            ->filterTable('turno', 'tarde')
            ->filterTable('turma_id', $turma->id)
            ->assertCanSeeTableRecords([$professorVinculado])
            ->assertCanNotSeeTableRecords([$professorSemVinculo]);
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
