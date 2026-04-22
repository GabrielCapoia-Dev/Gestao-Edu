<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\GestaoAvaliacoes;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GestaoAvaliacoesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_criacao_vincula_turmas_automaticamente_por_componentes_e_escola_especifica(): void
    {
        $usuario = $this->criarUsuarioComPermissoes();

        [$escolaCentro, $escolaJardim] = $this->criarEscolasBase();
        [$turmaCentro, $turmaJardim] = $this->criarTurmasBase($escolaCentro, $escolaJardim);

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-MAT',
            'nome' => 'Matematica',
        ]);

        $turmaCentro->componentes()->attach($componente->id, [
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $turmaJardim->componentes()->attach($componente->id, [
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $pauta = $this->criarPautaComAlternativa('Pauta de Matematica', $componente->id);

        Livewire::actingAs($usuario)
            ->test(GestaoAvaliacoes::class)
            ->set('form.nome', 'Avaliacao Escola Centro')
            ->set('form.data_inicio', now()->toDateString())
            ->set('form.data_fim', now()->addDays(7)->toDateString())
            ->set('form.status', Avaliacao::STATUS_ATIVA)
            ->set('form.escola_id', (string) $escolaCentro->id)
            ->set('form.componentes_ids', [$componente->id])
            ->set('form.pautas_ids', [$pauta->id])
            ->call('salvarAvaliacao');

        $avaliacao = Avaliacao::query()->where('nome', 'Avaliacao Escola Centro')->firstOrFail();

        $this->assertDatabaseHas('avaliacao_turma', [
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turmaCentro->id,
        ]);

        $this->assertDatabaseMissing('avaliacao_turma', [
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turmaJardim->id,
        ]);
    }

    public function test_criacao_com_todas_as_escolas_vincula_todas_as_turmas_dos_componentes(): void
    {
        $usuario = $this->criarUsuarioComPermissoes();

        [$escolaCentro, $escolaJardim] = $this->criarEscolasBase();
        [$turmaCentro, $turmaJardim] = $this->criarTurmasBase($escolaCentro, $escolaJardim);

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-CIE',
            'nome' => 'Ciencias',
        ]);

        $turmaCentro->componentes()->attach($componente->id, [
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $turmaJardim->componentes()->attach($componente->id, [
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $pauta = $this->criarPautaComAlternativa('Pauta de Ciencias', $componente->id);

        Livewire::actingAs($usuario)
            ->test(GestaoAvaliacoes::class)
            ->set('form.nome', 'Avaliacao Todas Escolas')
            ->set('form.data_inicio', now()->toDateString())
            ->set('form.data_fim', now()->addDays(7)->toDateString())
            ->set('form.status', Avaliacao::STATUS_ATIVA)
            ->set('form.escola_id', 'todas')
            ->set('form.componentes_ids', [$componente->id])
            ->set('form.pautas_ids', [$pauta->id])
            ->call('salvarAvaliacao');

        $avaliacao = Avaliacao::query()->where('nome', 'Avaliacao Todas Escolas')->firstOrFail();

        $this->assertDatabaseHas('avaliacao_turma', [
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turmaCentro->id,
        ]);

        $this->assertDatabaseHas('avaliacao_turma', [
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turmaJardim->id,
        ]);
    }

    private function criarUsuarioComPermissoes(): User
    {
        Permission::findOrCreate('Listar Avaliações');
        Permission::findOrCreate('Criar Avaliações');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $usuario->givePermissionTo([
            'Listar Avaliações',
            'Criar Avaliações',
        ]);

        return $usuario;
    }

    private function criarEscolasBase(): array
    {
        $escolaCentro = Escola::query()->create([
            'codigo' => 'ESC01',
            'nome' => 'Escola Centro',
            'email' => 'escola.centro@teste.local',
            'telefone' => '(44) 99999-1111',
        ]);

        $escolaJardim = Escola::query()->create([
            'codigo' => 'ESC02',
            'nome' => 'Escola Jardim',
            'email' => 'escola.jardim@teste.local',
            'telefone' => '(44) 99999-2222',
        ]);

        return [$escolaCentro, $escolaJardim];
    }

    private function criarTurmasBase(Escola $escolaCentro, Escola $escolaJardim): array
    {
        $serie = Serie::query()->create([
            'codigo' => 'SER-BASE',
            'nome' => 'Serie Base',
        ]);

        $turmaCentro = Turma::query()->create([
            'codigo' => 'TUR-CENTRO',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escolaCentro->id,
        ]);

        $turmaJardim = Turma::query()->create([
            'codigo' => 'TUR-JARDIM',
            'nome' => 'B',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escolaJardim->id,
        ]);

        return [$turmaCentro, $turmaJardim];
    }

    private function criarPautaComAlternativa(string $texto, int $componenteId): Pauta
    {
        $pauta = Pauta::query()->create([
            'texto' => $texto,
            'componente_curricular_id' => $componenteId,
            'status' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pauta->alternativas()->attach($alternativa->id);

        return $pauta;
    }
}

