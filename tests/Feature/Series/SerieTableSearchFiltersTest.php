<?php

namespace Tests\Feature\Series;

use App\Filament\Admin\Resources\Series\Pages\ManageSeries;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SerieTableSearchFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_listagem_pesquisa_componente_e_filtra_series_com_turmas(): void
    {
        Permission::findOrCreate('Listar Séries');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-SERIE-BUSCA',
            'nome' => 'Componente Serie Exclusivo',
        ]);

        $serieComTurma = Serie::query()->create([
            'codigo' => 'SER-COM-TURMA',
            'nome' => 'Serie Com Turma',
        ]);
        $serieComTurma->componentesCurriculares()->attach($componente->id);

        $serieSemTurma = Serie::query()->create([
            'codigo' => 'SER-SEM-TURMA',
            'nome' => 'Serie Sem Turma',
        ]);

        $escola = Escola::query()->create([
            'codigo' => 'ESC-SERIE-BUSCA',
            'nome' => 'Escola Serie Busca',
            'ativo' => true,
        ]);

        Turma::query()->create([
            'codigo' => 'TUR-SERIE-BUSCA',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serieComTurma->id,
            'id_escola' => $escola->id,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Séries');

        Livewire::actingAs($usuario)
            ->test(ManageSeries::class)
            ->searchTable('Componente Serie Exclusivo')
            ->assertCanSeeTableRecords([$serieComTurma])
            ->assertCanNotSeeTableRecords([$serieSemTurma]);

        Livewire::actingAs($usuario)
            ->test(ManageSeries::class)
            ->filterTable('componente_curricular_id', $componente->id)
            ->filterTable('possui_turmas', true)
            ->assertCanSeeTableRecords([$serieComTurma])
            ->assertCanNotSeeTableRecords([$serieSemTurma]);
    }
}
