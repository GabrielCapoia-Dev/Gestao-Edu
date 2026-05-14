<?php

namespace Tests\Feature\Series;

use App\Filament\Admin\Resources\Series\Pages\ManageSeries;
use App\Models\ComponenteCurricular;
use App\Models\Permission;
use App\Models\Serie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SerieBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_bulk_action_adds_multiple_curricular_components_without_removing_existing_links(): void
    {
        $admin = $this->userWithPermissions([
            'Listar Séries',
            'Editar Séries',
        ]);

        $serieA = Serie::query()->create([
            'codigo' => 'SER-A',
            'nome' => 'Série A',
        ]);
        $serieB = Serie::query()->create([
            'codigo' => 'SER-B',
            'nome' => 'Série B',
        ]);

        $portugues = ComponenteCurricular::query()->create([
            'codigo' => 'PORT',
            'nome' => 'Português',
        ]);
        $matematica = ComponenteCurricular::query()->create([
            'codigo' => 'MAT',
            'nome' => 'Matemática',
        ]);
        $historia = ComponenteCurricular::query()->create([
            'codigo' => 'HIST',
            'nome' => 'História',
        ]);

        $serieA->componentesCurriculares()->attach($portugues->id);

        Livewire::actingAs($admin)
            ->test(ManageSeries::class)
            ->mountTableBulkAction('adicionar_componentes_curriculares', [$serieA, $serieB])
            ->setTableBulkActionData([
                'componentes_curriculares' => [
                    $matematica->id,
                    $historia->id,
                ],
            ])
            ->callMountedTableBulkAction()
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('serie_componente_curricular', [
            'serie_id' => $serieA->id,
            'componente_curricular_id' => $portugues->id,
        ]);
        $this->assertDatabaseHas('serie_componente_curricular', [
            'serie_id' => $serieA->id,
            'componente_curricular_id' => $matematica->id,
        ]);
        $this->assertDatabaseHas('serie_componente_curricular', [
            'serie_id' => $serieA->id,
            'componente_curricular_id' => $historia->id,
        ]);
        $this->assertDatabaseHas('serie_componente_curricular', [
            'serie_id' => $serieB->id,
            'componente_curricular_id' => $matematica->id,
        ]);
        $this->assertDatabaseHas('serie_componente_curricular', [
            'serie_id' => $serieB->id,
            'componente_curricular_id' => $historia->id,
        ]);
        $this->assertDatabaseCount('serie_componente_curricular', 5);
    }

    private function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create([
            'email_approved' => true,
        ]);

        $user->givePermissionTo($permissions);

        return $user;
    }
}
