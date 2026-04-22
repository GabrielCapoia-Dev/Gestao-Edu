<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\GestaoAvaliacoes;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GestaoAvaliacoesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_criacao_com_tipo_periodo_series_componentes_e_todas_as_escolas_elegiveis(): void
    {
        $usuario = $this->criarUsuarioComPermissoes();

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => '1o Semestre', 'status' => true]);

        $serie1 = Serie::query()->create(['codigo' => 'SER-1', 'nome' => '1o Ano']);
        $serie2 = Serie::query()->create(['codigo' => 'SER-2', 'nome' => '2o Ano']);

        $componenteMat = ComponenteCurricular::query()->create(['codigo' => 'COMP-MAT', 'nome' => 'Matematica']);
        $componenteHis = ComponenteCurricular::query()->create(['codigo' => 'COMP-HIS', 'nome' => 'Historia']);

        $serie1->componentesCurriculares()->sync([$componenteMat->id]);
        $serie2->componentesCurriculares()->sync([$componenteHis->id]);

        $escolaA = Escola::query()->create([
            'codigo' => 'ESC-A',
            'nome' => 'Escola A',
            'email' => 'escola.a@teste.local',
            'telefone' => '(44) 99999-1111',
        ]);
        $escolaB = Escola::query()->create([
            'codigo' => 'ESC-B',
            'nome' => 'Escola B',
            'email' => 'escola.b@teste.local',
            'telefone' => '(44) 99999-2222',
        ]);

        $turmaA = Turma::query()->create([
            'codigo' => 'TUR-A',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie1->id,
            'id_escola' => $escolaA->id,
        ]);
        $turmaB = Turma::query()->create([
            'codigo' => 'TUR-B',
            'nome' => 'B',
            'turno' => 'manha',
            'id_serie' => $serie1->id,
            'id_escola' => $escolaB->id,
        ]);

        $turmaA->componentes()->attach($componenteMat->id, ['professor_id' => null, 'tem_professor' => false]);
        $turmaB->componentes()->attach($componenteMat->id, ['professor_id' => null, 'tem_professor' => false]);

        Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pautaMat = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Sabe contar ate 10',
            'serie_id' => $serie1->id,
            'componente_curricular_id' => $componenteMat->id,
            'status' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(GestaoAvaliacoes::class)
            ->set('form.nome', 'Avaliacao Matematica')
            ->set('form.tipo_avaliacao_id', $tipo->id)
            ->set('form.periodo_avaliacao_id', $periodo->id)
            ->set('form.data_inicio', now()->toDateString())
            ->set('form.data_fim', now()->addDays(7)->toDateString())
            ->set('form.status', Avaliacao::STATUS_ATIVA)
            ->set('form.series_ids', [$serie1->id])
            ->set('form.componentes_ids', [$componenteMat->id])
            ->set('form.escolas_ids', ['todas'])
            ->call('salvarAvaliacao');

        $avaliacao = Avaliacao::query()->where('nome', 'Avaliacao Matematica')->firstOrFail();

        $this->assertDatabaseHas('avaliacao_pauta', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pautaMat->id,
        ]);

        $this->assertDatabaseHas('avaliacao_turma', [
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turmaA->id,
        ]);
        $this->assertDatabaseHas('avaliacao_turma', [
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turmaB->id,
        ]);

        $this->assertDatabaseHas('avaliacao_serie', [
            'avaliacao_id' => $avaliacao->id,
            'serie_id' => $serie1->id,
        ]);
        $this->assertDatabaseHas('avaliacao_componente', [
            'avaliacao_id' => $avaliacao->id,
            'componente_curricular_id' => $componenteMat->id,
        ]);
        $this->assertDatabaseHas('avaliacao_escola', [
            'avaliacao_id' => $avaliacao->id,
            'escola_id' => $escolaA->id,
        ]);
        $this->assertDatabaseHas('avaliacao_escola', [
            'avaliacao_id' => $avaliacao->id,
            'escola_id' => $escolaB->id,
        ]);
    }

    public function test_criacao_salva_override_de_alternativas_por_pauta(): void
    {
        $usuario = $this->criarUsuarioComPermissoes();

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Tipo Override', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Teste', 'status' => true]);

        $serie = Serie::query()->create(['codigo' => 'SER-O', 'nome' => '3o Ano']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-O', 'nome' => 'Artes']);
        $serie->componentesCurriculares()->sync([$componente->id]);

        $escola = Escola::query()->create([
            'codigo' => 'ESC-O',
            'nome' => 'Escola Override',
            'email' => 'escola.override@teste.local',
            'telefone' => '(44) 99999-3333',
        ]);

        $turma = Turma::query()->create([
            'codigo' => 'TUR-O',
            'nome' => 'C',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $turma->componentes()->attach($componente->id, ['professor_id' => null, 'tem_professor' => false]);

        $alternativaPadrao = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Padrao',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $alternativaOverride = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Override',
            'tem_observacao' => true,
            'status' => true,
        ]);

        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Expressa criatividade',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(GestaoAvaliacoes::class)
            ->set('form.nome', 'Avaliacao Override')
            ->set('form.tipo_avaliacao_id', $tipo->id)
            ->set('form.periodo_avaliacao_id', $periodo->id)
            ->set('form.data_inicio', now()->toDateString())
            ->set('form.data_fim', now()->addDays(7)->toDateString())
            ->set('form.status', Avaliacao::STATUS_ATIVA)
            ->set('form.series_ids', [$serie->id])
            ->set('form.componentes_ids', [$componente->id])
            ->set('form.escolas_ids', [(string) $escola->id])
            ->set("form.pautas_override_habilitado.{$pauta->id}", true)
            ->set("form.alternativas_override.{$pauta->id}", [$alternativaOverride->id])
            ->call('salvarAvaliacao');

        $avaliacao = Avaliacao::query()->where('nome', 'Avaliacao Override')->firstOrFail();

        $this->assertDatabaseHas('avaliacao_pauta_alternativa', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'alternativa_id' => $alternativaOverride->id,
        ]);

        $this->assertDatabaseMissing('avaliacao_pauta_alternativa', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'alternativa_id' => $alternativaPadrao->id,
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
}
