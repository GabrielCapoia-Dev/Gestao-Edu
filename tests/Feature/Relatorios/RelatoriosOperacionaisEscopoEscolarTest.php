<?php

namespace Tests\Feature\Relatorios;

use App\Filament\Admin\Pages\Relatorios\RelatorioComponenteProfessorFaltando;
use App\Filament\Admin\Pages\Relatorios\RelatorioProfessorComponenteTurma;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RelatoriosOperacionaisEscopoEscolarTest extends TestCase
{
    use RefreshDatabase;

    public function test_relatorios_restringem_escola_e_preservam_acesso_global(): void
    {
        Permission::findOrCreate('Listar Relatórios: Professor por Componente e Turma', 'web');
        Permission::findOrCreate('Listar Relatórios: Componentes com Professores Faltando', 'web');

        [$escolaA, $vinculoA] = $this->criarVinculo('A');
        [, $vinculoB] = $this->criarVinculo('B');

        $restrito = User::factory()->create([
            'id_escola' => $escolaA->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $restrito->givePermissionTo([
            'Listar Relatórios: Professor por Componente e Turma',
            'Listar Relatórios: Componentes com Professores Faltando',
        ]);

        Livewire::actingAs($restrito)
            ->test(RelatorioProfessorComponenteTurma::class)
            ->assertCanSeeTableRecords([$vinculoA])
            ->assertCanNotSeeTableRecords([$vinculoB]);

        Livewire::actingAs($restrito)
            ->test(RelatorioComponenteProfessorFaltando::class)
            ->assertSet('totalEscolas', 1)
            ->assertSet('totalTurmas', 1)
            ->assertSet('totalTurmasFaltando', 1);

        $admin = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        $admin->givePermissionTo([
            'Listar Relatórios: Professor por Componente e Turma',
            'Listar Relatórios: Componentes com Professores Faltando',
        ]);

        Livewire::actingAs($admin)
            ->test(RelatorioProfessorComponenteTurma::class)
            ->assertCanSeeTableRecords([$vinculoA, $vinculoB]);

        Livewire::actingAs($admin)
            ->test(RelatorioComponenteProfessorFaltando::class)
            ->assertSet('totalEscolas', 2)
            ->assertSet('totalTurmas', 2)
            ->assertSet('totalTurmasFaltando', 2);
    }

    /** @return array{Escola, TurmaComponenteProfessor} */
    private function criarVinculo(string $sufixo): array
    {
        $escola = Escola::query()->create([
            'codigo' => "ESC-{$sufixo}",
            'nome' => "Escola {$sufixo}",
            'ativo' => true,
        ]);
        $serie = Serie::query()->create([
            'codigo' => "SER-{$sufixo}",
            'nome' => "Série {$sufixo}",
        ]);
        $turma = Turma::query()->create([
            'codigo' => "TUR-{$sufixo}",
            'nome' => $sufixo,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => "COMP-{$sufixo}",
            'nome' => "Componente {$sufixo}",
        ]);
        $vinculo = TurmaComponenteProfessor::query()->create([
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        return [$escola, $vinculo];
    }
}
