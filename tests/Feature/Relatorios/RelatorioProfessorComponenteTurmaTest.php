<?php

namespace Tests\Feature\Relatorios;

use App\Filament\Admin\Pages\Relatorios\RelatorioProfessorComponenteTurma;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class RelatorioProfessorComponenteTurmaTest extends TestCase
{
    use RefreshDatabase;

    public function test_exportacao_gera_download_com_dados_de_professor_e_turma(): void
    {
        $escola = Escola::query()->create([
            'codigo' => 'ESC-REL-01',
            'nome' => 'Escola Relatorio',
        ]);

        $serie = Serie::query()->create([
            'codigo' => 'SER-REL-01',
            'nome' => '1 Ano',
        ]);

        $turma = Turma::query()->create([
            'codigo' => 'TUR-REL-01',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'POR-REL-01',
            'nome' => 'Lingua Portuguesa',
        ]);

        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-001',
            'nome' => 'Professor Relatorio',
            'email' => 'professor@example.com',
        ]);

        TurmaComponenteProfessor::query()->create([
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
        ]);

        Permission::findOrCreate('Listar Relatórios: Professor por Componente e Turma', 'web');
        Permission::findOrCreate('Exportar Relatórios', 'web');

        $usuario->givePermissionTo([
            'Listar Relatórios: Professor por Componente e Turma',
            'Exportar Relatórios',
        ]);

        $this->actingAs($usuario);

        $page = app(RelatorioProfessorComponenteTurma::class);
        $response = $page->exportarRelatorioGeral();

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString(
            'relatorio_professor_componente_turma_',
            (string) $response->headers->get('content-disposition')
        );
    }
}
