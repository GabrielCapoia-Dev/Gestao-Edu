<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\AvaliacoesProfessor;
use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AvaliacaoProfessorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_visualiza_apenas_avaliacoes_pendentes_dos_componentes_que_leciona(): void
    {
        Permission::findOrCreate('Responder Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => '1o Semestre', 'status' => true]);

        $escola = $this->criarEscola('Escola Base');
        $serie = $this->criarSerie('SER-BASE', '1o Ano');
        $turma = $this->criarTurma($escola, $serie, 'Turma A');

        $componenteMatematica = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-MAT',
            'nome' => 'Matematica',
        ]);

        $componenteHistoria = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-HIS',
            'nome' => 'Historia',
        ]);

        $userProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $userProfessor->givePermissionTo('Responder Avaliações');

        $professor = Professor::query()->create([
            'user_id' => $userProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-001',
            'nome' => 'Professor Matematica',
            'email' => 'matematica@edu.umuarama.pr.gov.br',
        ]);

        $turma->componentes()->attach($componenteMatematica->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pautaMatematica = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta de Matematica',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componenteMatematica->id,
            'status' => true,
        ]);
        $pautaMatematica->alternativas()->attach($alternativa->id);

        $pautaHistoria = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta de Historia',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componenteHistoria->id,
            'status' => true,
        ]);
        $pautaHistoria->alternativas()->attach($alternativa->id);

        $avaliacaoVisivel = $this->criarAvaliacao('Avaliacao Matematica', $tipo, $periodo);
        $avaliacaoVisivel->pautas()->attach($pautaMatematica->id);
        $avaliacaoVisivel->turmas()->attach($turma->id);
        $this->sincronizarEscopoAvaliacao($avaliacaoVisivel, [$serie->id], [$componenteMatematica->id], [$escola->id]);

        $avaliacaoNaoVisivelComponente = $this->criarAvaliacao('Avaliacao Historia', $tipo, $periodo);
        $avaliacaoNaoVisivelComponente->pautas()->attach($pautaHistoria->id);
        $avaliacaoNaoVisivelComponente->turmas()->attach($turma->id);
        $this->sincronizarEscopoAvaliacao($avaliacaoNaoVisivelComponente, [$serie->id], [$componenteHistoria->id], [$escola->id]);

        $avaliacaoInativa = Avaliacao::query()->create([
            'nome' => 'Avaliacao Inativa',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'status' => Avaliacao::STATUS_INATIVA,
        ]);
        $avaliacaoInativa->pautas()->attach($pautaMatematica->id);
        $avaliacaoInativa->turmas()->attach($turma->id);
        $this->sincronizarEscopoAvaliacao($avaliacaoInativa, [$serie->id], [$componenteMatematica->id], [$escola->id]);

        $this->actingAs($userProfessor)
            ->get(route('filament.admin.pages.avaliacoes-professor'))
            ->assertOk()
            ->assertSee('Avaliacao Matematica')
            ->assertDontSee('Avaliacao Historia')
            ->assertDontSee('Avaliacao Inativa');
    }

    public function test_professor_filtra_por_serie_e_visualiza_turmas_agrupadas(): void
    {
        Permission::findOrCreate('Responder Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Serie', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Serie', 'status' => true]);

        $escola = $this->criarEscola('Escola Serie');
        $serie = $this->criarSerie('SER-AGR', '1o Ano');
        $outraSerie = $this->criarSerie('SER-FORA', '2o Ano');
        $turmaA = $this->criarTurma($escola, $serie, 'A');
        $turmaB = $this->criarTurma($escola, $serie, 'B');
        $turmaFora = $this->criarTurma($escola, $outraSerie, 'C');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-SER',
            'nome' => 'Lingua Portuguesa',
        ]);

        $userProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $userProfessor->givePermissionTo('Responder Avaliações');

        $professor = Professor::query()->create([
            'user_id' => $userProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-SER',
            'nome' => 'Professor Serie',
            'email' => 'serie@edu.umuarama.pr.gov.br',
        ]);

        foreach ([$turmaA, $turmaB] as $turma) {
            $turma->componentes()->attach($componente->id, [
                'professor_id' => $professor->id,
                'tem_professor' => true,
            ]);
        }

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pautaSerie = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta do primeiro ano',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pautaSerie->alternativas()->attach($alternativa->id);

        $pautaOutraSerie = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta do segundo ano',
            'serie_id' => $outraSerie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pautaOutraSerie->alternativas()->attach($alternativa->id);

        $avaliacao = $this->criarAvaliacao('Avaliacao por Serie', $tipo, $periodo);
        $avaliacao->pautas()->attach([$pautaSerie->id, $pautaOutraSerie->id]);
        $avaliacao->turmas()->attach([$turmaA->id, $turmaB->id, $turmaFora->id]);
        $this->sincronizarEscopoAvaliacao($avaliacao, [$serie->id, $outraSerie->id], [$componente->id], [$escola->id]);

        Aluno::query()->create([
            'nome' => 'Aluno Turma A',
            'cgm' => 'CGM-SER-A',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaA->id,
        ]);

        Aluno::query()->create([
            'nome' => 'Aluno Turma B',
            'cgm' => 'CGM-SER-B',
            'data_nascimento' => '2015-01-02',
            'id_turma' => $turmaB->id,
        ]);

        Livewire::actingAs($userProfessor)
            ->test(AvaliacoesProfessor::class)
            ->set('avaliacao', $avaliacao->id)
            ->assertSee('1o Ano')
            ->assertDontSee('2o Ano')
            ->set('serie', $serie->id)
            ->assertSee('Turma A')
            ->assertSee('Turma B')
            ->assertDontSee('Turma C')
            ->call('definirVisualizacao', 'alunos')
            ->assertSee('Aluno Turma A')
            ->assertSee('Aluno Turma B');
    }

    public function test_professor_usa_override_de_alternativas_por_pauta(): void
    {
        Permission::findOrCreate('Responder Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Override', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Override', 'status' => true]);

        $escola = $this->criarEscola('Escola Override');
        $serie = $this->criarSerie('SER-OVR', '2o Ano');
        $turma = $this->criarTurma($escola, $serie, 'Turma B');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-OVR',
            'nome' => 'Ciencias',
        ]);

        $userProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $userProfessor->givePermissionTo('Responder Avaliações');

        $professor = Professor::query()->create([
            'user_id' => $userProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-002',
            'nome' => 'Professor Ciencias',
            'email' => 'ciencias@edu.umuarama.pr.gov.br',
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $alternativaPadrao = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Padrao',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $alternativaOverride = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Override',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Participacao em aula',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativaPadrao->id);

        $avaliacao = $this->criarAvaliacao('Avaliacao Override', $tipo, $periodo);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach($turma->id);
        $this->sincronizarEscopoAvaliacao($avaliacao, [$serie->id], [$componente->id], [$escola->id]);

        DB::table('avaliacao_pauta_alternativa')->insert([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'alternativa_id' => $alternativaOverride->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Override',
            'cgm' => 'CGM-OVR-001',
            'data_nascimento' => '2015-02-01',
            'id_turma' => $turma->id,
        ]);

        Livewire::actingAs($userProfessor)
            ->test(AvaliacoesProfessor::class)
            ->set('avaliacao', $avaliacao->id)
            ->set('turma', $turma->id)
            ->set("respostas.{$pauta->id}.{$aluno->id}.alternativa_id", $alternativaOverride->id)
            ->call('salvarRespostas');

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
            'professor_id' => $professor->id,
            'alternativa_id' => $alternativaOverride->id,
        ]);

        $this->assertDatabaseMissing('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
            'alternativa_id' => $alternativaPadrao->id,
        ]);
    }

    public function test_professor_autosalva_informacoes_complementares_por_aluno(): void
    {
        Permission::findOrCreate('Responder Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Tipo Complementar', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Complementar', 'status' => true]);

        $escola = $this->criarEscola('Escola Complementar');
        $serie = $this->criarSerie('SER-CMP', '3o Ano');
        $turma = $this->criarTurma($escola, $serie, 'Turma C');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-CMP',
            'nome' => 'Portugues',
        ]);

        $userProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $userProfessor->givePermissionTo('Responder Avaliações');

        $professor = Professor::query()->create([
            'user_id' => $userProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-003',
            'nome' => 'Professor Portugues',
            'email' => 'portugues@edu.umuarama.pr.gov.br',
        ]);

        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Leitura e interpretacao',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = $this->criarAvaliacao('Avaliacao Complementar', $tipo, $periodo);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach($turma->id);
        $this->sincronizarEscopoAvaliacao($avaliacao, [$serie->id], [$componente->id], [$escola->id]);

        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Complementar',
            'cgm' => 'CGM-CMP-001',
            'data_nascimento' => '2015-03-01',
            'id_turma' => $turma->id,
        ]);

        Livewire::actingAs($userProfessor)
            ->test(AvaliacoesProfessor::class)
            ->set('avaliacao', $avaliacao->id)
            ->set('turma', $turma->id)
            ->set("respostas.{$pauta->id}.{$aluno->id}.alternativa_id", $alternativa->id)
            ->set("informacoesComplementares.{$aluno->id}", 'Aluno evoluiu na comunicacao oral.')
            ->call('salvarRespostas');

        $this->assertDatabaseHas('avaliacao_informacoes_complementares', [
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
            'professor_id' => $professor->id,
            'informacoes_complementares' => 'Aluno evoluiu na comunicacao oral.',
        ]);
    }

    private function criarAvaliacao(string $nome, TipoAvaliacao $tipo, PeriodoAvaliacao $periodo): Avaliacao
    {
        return Avaliacao::query()->create([
            'nome' => $nome,
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 5)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)) . '@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
    }

    private function criarSerie(string $codigo, string $nome): Serie
    {
        return Serie::query()->create([
            'codigo' => $codigo,
            'nome' => $nome,
        ]);
    }

    private function criarTurma(Escola $escola, Serie $serie, string $nome): Turma
    {
        return Turma::query()->create([
            'codigo' => 'TUR' . strtoupper(substr(md5($nome . microtime()), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function sincronizarEscopoAvaliacao(Avaliacao $avaliacao, array $seriesIds, array $componentesIds, array $escolasIds): void
    {
        $avaliacao->series()->sync($seriesIds);
        $avaliacao->componentes()->sync($componentesIds);
        $avaliacao->escolas()->sync($escolasIds);
    }
}
