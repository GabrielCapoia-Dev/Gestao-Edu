<?php

namespace Tests\Feature\Avaliacoes;

use App\Livewire\Avaliacoes\AvaliacaoTurmaWorkspace;
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
        $outraEscola = $this->criarEscola('Outra Escola Serie');
        $serie = $this->criarSerie('SER-AGR', '1o Ano');
        $outraSerie = $this->criarSerie('SER-FORA', '2o Ano');
        $turmaA = $this->criarTurma($escola, $serie, 'A');
        $turmaB = $this->criarTurma($escola, $serie, 'B');
        $turmaOutraEscola = $this->criarTurma($outraEscola, $serie, 'C');
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

        foreach ([$turmaA, $turmaB, $turmaOutraEscola] as $turma) {
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
        $avaliacao->turmas()->attach([$turmaA->id, $turmaB->id, $turmaOutraEscola->id, $turmaFora->id]);
        $this->sincronizarEscopoAvaliacao($avaliacao, [$serie->id, $outraSerie->id], [$componente->id], [$escola->id, $outraEscola->id]);

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

        Aluno::query()->create([
            'nome' => 'Aluno Outra Escola',
            'cgm' => 'CGM-SER-C',
            'data_nascimento' => '2015-01-03',
            'id_turma' => $turmaOutraEscola->id,
        ]);

        Livewire::actingAs($userProfessor)
            ->test(AvaliacaoTurmaWorkspace::class, $this->workspaceProfessorParams())
            ->set('avaliacao', $avaliacao->id)
            ->assertSee('Escola Serie')
            ->assertSee('Outra Escola Serie')
            ->assertSee('1o Ano')
            ->assertDontSee('2o Ano')
            ->set('serieEscola', $escola->id.':'.$serie->id)
            ->assertSet('turmasExpandidas', [])
            ->assertSee('Turma A')
            ->assertSee('Turma B')
            ->assertDontSee('Turma C')
            ->call('definirVisualizacao', 'alunos')
            ->call('alternarTurma', $turmaA->id)
            ->call('alternarTurma', $turmaB->id)
            ->assertSee('Aluno Turma A')
            ->assertSee('Aluno Turma B')
            ->assertDontSee('Aluno Outra Escola')
            ->set('serieEscola', $outraEscola->id.':'.$serie->id)
            ->assertSee('Turma C')
            ->assertDontSee('Turma A');
    }

    public function test_professor_visualiza_apenas_avaliacoes_e_turmas_das_escolas_e_turmas_vinculadas(): void
    {
        Permission::findOrCreate('Responder AvaliaÃ§Ãµes');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Escopo', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Escopo', 'status' => true]);

        $escolaVinculada = $this->criarEscola('Escola Vinculada');
        $escolaFora = $this->criarEscola('Escola Fora');
        $serie = $this->criarSerie('SER-ESCOPO', '3o Ano');
        $turmaVinculada = $this->criarTurma($escolaVinculada, $serie, 'A');
        $turmaMesmaEscolaSemVinculo = $this->criarTurma($escolaVinculada, $serie, 'B');
        $turmaOutraEscola = $this->criarTurma($escolaFora, $serie, 'C');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-ESC',
            'nome' => 'Geografia',
        ]);

        $userProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
            'id_escola' => $escolaVinculada->id,
        ]);
        $userProfessor->givePermissionTo('Responder AvaliaÃ§Ãµes');
        $userProfessor->escolas()->sync([$escolaVinculada->id]);

        $professor = Professor::query()->create([
            'user_id' => $userProfessor->id,
            'id_escola' => $escolaVinculada->id,
            'matricula' => 'PROF-ESC',
            'nome' => 'Professor Escopo',
            'email' => 'escopo@edu.umuarama.pr.gov.br',
        ]);

        $turmaVinculada->componentes()->attach($componente->id, [
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
            'texto' => 'Pauta de escopo',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacaoVinculada = $this->criarAvaliacao('Avaliacao Vinculada', $tipo, $periodo);
        $avaliacaoVinculada->pautas()->attach($pauta->id);
        $avaliacaoVinculada->turmas()->attach($turmaVinculada->id);
        $this->sincronizarEscopoAvaliacao($avaliacaoVinculada, [$serie->id], [$componente->id], [$escolaVinculada->id]);

        $avaliacaoMesmaEscolaSemVinculo = $this->criarAvaliacao('Avaliacao Sem Vinculo', $tipo, $periodo);
        $avaliacaoMesmaEscolaSemVinculo->pautas()->attach($pauta->id);
        $avaliacaoMesmaEscolaSemVinculo->turmas()->attach($turmaMesmaEscolaSemVinculo->id);
        $this->sincronizarEscopoAvaliacao($avaliacaoMesmaEscolaSemVinculo, [$serie->id], [$componente->id], [$escolaVinculada->id]);

        $avaliacaoOutraEscola = $this->criarAvaliacao('Avaliacao Outra Escola', $tipo, $periodo);
        $avaliacaoOutraEscola->pautas()->attach($pauta->id);
        $avaliacaoOutraEscola->turmas()->attach($turmaOutraEscola->id);
        $this->sincronizarEscopoAvaliacao($avaliacaoOutraEscola, [$serie->id], [$componente->id], [$escolaFora->id]);

        Aluno::query()->create([
            'nome' => 'Aluno Vinculado',
            'cgm' => 'CGM-ESC-001',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaVinculada->id,
        ]);

        Livewire::actingAs($userProfessor)
            ->test(AvaliacaoTurmaWorkspace::class, $this->workspaceProfessorParams())
            ->assertSee('Avaliacao Vinculada')
            ->assertDontSee('Avaliacao Sem Vinculo')
            ->assertDontSee('Avaliacao Outra Escola')
            ->set('avaliacao', $avaliacaoVinculada->id)
            ->assertSee('Escola Vinculada')
            ->assertDontSee('Escola Fora')
            ->set('serieEscola', $escolaVinculada->id . ':' . $serie->id)
            ->assertSee('Turma A')
            ->assertDontSee('Turma B')
            ->assertDontSee('Turma C');
    }

    public function test_usuario_com_permissao_de_listagem_fica_limitado_a_avaliacoes_da_sua_escola(): void
    {
        Permission::findOrCreate('Listar AvaliaÃ§Ãµes');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Coordenacao', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Coordenacao', 'status' => true]);

        $escolaPermitida = $this->criarEscola('Escola Permitida');
        $escolaFora = $this->criarEscola('Escola Fora Coordenacao');
        $serie = $this->criarSerie('SER-COORD', '4o Ano');
        $turmaPermitida = $this->criarTurma($escolaPermitida, $serie, 'A');
        $turmaFora = $this->criarTurma($escolaFora, $serie, 'B');

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta coordenacao',
            'serie_id' => $serie->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacaoPermitida = $this->criarAvaliacao('Avaliacao Escola Permitida', $tipo, $periodo);
        $avaliacaoPermitida->pautas()->attach($pauta->id);
        $avaliacaoPermitida->turmas()->attach($turmaPermitida->id);
        $this->sincronizarEscopoAvaliacao($avaliacaoPermitida, [$serie->id], [], [$escolaPermitida->id]);

        $avaliacaoFora = $this->criarAvaliacao('Avaliacao Escola Fora', $tipo, $periodo);
        $avaliacaoFora->pautas()->attach($pauta->id);
        $avaliacaoFora->turmas()->attach($turmaFora->id);
        $this->sincronizarEscopoAvaliacao($avaliacaoFora, [$serie->id], [], [$escolaFora->id]);

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
            'id_escola' => $escolaPermitida->id,
        ]);
        $user->givePermissionTo('Listar AvaliaÃ§Ãµes');
        $user->escolas()->sync([$escolaPermitida->id]);

        Livewire::actingAs($user)
            ->test(AvaliacaoTurmaWorkspace::class, $this->workspaceProfessorParams(false))
            ->assertSee('Avaliacao Escola Permitida')
            ->assertDontSee('Avaliacao Escola Fora')
            ->set('avaliacao', $avaliacaoPermitida->id)
            ->assertSee('Escola Permitida')
            ->assertDontSee('Escola Fora Coordenacao');
    }

    public function test_avaliacao_em_massa_respeita_turma_alvo_e_nao_sobrescreve_respostas_com_observacao(): void
    {
        Permission::findOrCreate('Responder Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Massa', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Massa', 'status' => true]);

        $escola = $this->criarEscola('Escola Massa');
        $serie = $this->criarSerie('SER-MAS', '1o Ano');
        $turmaA = $this->criarTurma($escola, $serie, 'A');
        $turmaB = $this->criarTurma($escola, $serie, 'B');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-MAS',
            'nome' => 'Matematica Massa',
        ]);

        $userProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $userProfessor->givePermissionTo('Responder Avaliações');

        $professor = Professor::query()->create([
            'user_id' => $userProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-MAS',
            'nome' => 'Professor Massa',
            'email' => 'massa@edu.umuarama.pr.gov.br',
        ]);

        foreach ([$turmaA, $turmaB] as $turma) {
            $turma->componentes()->attach($componente->id, [
                'professor_id' => $professor->id,
                'tem_professor' => true,
            ]);
        }

        $alternativaSim = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Sim',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $alternativaNao = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Nao',
            'tem_observacao' => true,
            'status' => true,
        ]);

        $alternativaParcial = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Parcial',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta massa',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach([$alternativaSim->id, $alternativaNao->id, $alternativaParcial->id]);

        $avaliacao = $this->criarAvaliacao('Avaliacao Massa', $tipo, $periodo);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach([$turmaA->id, $turmaB->id]);
        $this->sincronizarEscopoAvaliacao($avaliacao, [$serie->id], [$componente->id], [$escola->id]);

        $alunoPreenchido = Aluno::query()->create([
            'nome' => 'Aluno preenchido',
            'cgm' => 'CGM-MAS-001',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaA->id,
        ]);

        $alunoVazio = Aluno::query()->create([
            'nome' => 'Aluno vazio',
            'cgm' => 'CGM-MAS-002',
            'data_nascimento' => '2015-01-02',
            'id_turma' => $turmaA->id,
        ]);

        $alunoComRespostaSemObservacao = Aluno::query()->create([
            'nome' => 'Aluno com resposta sem observacao',
            'cgm' => 'CGM-MAS-004',
            'data_nascimento' => '2015-01-04',
            'id_turma' => $turmaA->id,
        ]);

        $alunoOutraTurma = Aluno::query()->create([
            'nome' => 'Aluno outra turma',
            'cgm' => 'CGM-MAS-003',
            'data_nascimento' => '2015-01-03',
            'id_turma' => $turmaB->id,
        ]);

        $observacaoManual = str_repeat('a', 1600);
        $observacaoLimitada = str_repeat('a', 1500);

        Livewire::actingAs($userProfessor)
            ->test(AvaliacaoTurmaWorkspace::class, $this->workspaceProfessorParams())
            ->set('avaliacao', $avaliacao->id)
            ->set('serieEscola', $escola->id.':'.$serie->id)
            ->set("respostas.{$pauta->id}.{$alunoPreenchido->id}.alternativa_id", $alternativaNao->id)
            ->set("respostas.{$pauta->id}.{$alunoPreenchido->id}.observacao", $observacaoManual)
            ->set("respostas.{$pauta->id}.{$alunoComRespostaSemObservacao->id}.alternativa_id", $alternativaParcial->id)
            ->set('turmaEmMassaGlobal', $turmaA->id)
            ->set('avaliacaoEmMassaGlobal', $alternativaSim->id)
            ->call('aplicarEmMassaNaSerie');

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaA->id,
            'aluno_id' => $alunoPreenchido->id,
            'alternativa_id' => $alternativaNao->id,
            'observacao' => $observacaoLimitada,
        ]);

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaA->id,
            'aluno_id' => $alunoVazio->id,
            'alternativa_id' => $alternativaSim->id,
        ]);

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaA->id,
            'aluno_id' => $alunoComRespostaSemObservacao->id,
            'alternativa_id' => $alternativaSim->id,
        ]);

        $this->assertDatabaseMissing('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turmaB->id,
            'aluno_id' => $alunoOutraTurma->id,
        ]);
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
            ->test(AvaliacaoTurmaWorkspace::class, $this->workspaceProfessorParams())
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

        $informacoesComplementares = str_repeat('b', 1600);
        $informacoesLimitadas = str_repeat('b', 1500);

        Livewire::actingAs($userProfessor)
            ->test(AvaliacaoTurmaWorkspace::class, $this->workspaceProfessorParams())
            ->set('avaliacao', $avaliacao->id)
            ->set('turma', $turma->id)
            ->set("respostas.{$pauta->id}.{$aluno->id}.alternativa_id", $alternativa->id)
            ->set("informacoesComplementares.{$componente->id}.{$aluno->id}", $informacoesComplementares)
            ->call('salvarRespostas');

        $this->assertDatabaseHas('avaliacao_informacoes_complementares', [
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professor->id,
            'informacoes_complementares' => $informacoesLimitadas,
        ]);
    }

    public function test_workspace_do_professor_mantem_botao_de_validar_pendencias(): void
    {
        Permission::findOrCreate('Responder AvaliaÃ§Ãµes');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Tipo Validacao Professor', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Validacao Professor', 'status' => true]);
        $escola = $this->criarEscola('Escola Validacao Professor');
        $serie = $this->criarSerie('SER-VAL-PROF', 'Infantil 3');
        $turma = $this->criarTurma($escola, $serie, 'Turma Validacao');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-VAL-PROF',
            'nome' => 'Artes',
        ]);

        $userProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $userProfessor->givePermissionTo('Responder AvaliaÃ§Ãµes');

        $professor = Professor::query()->create([
            'user_id' => $userProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-VAL-PROF',
            'nome' => 'Professor Validacao',
            'email' => 'validacao.professor@edu.umuarama.pr.gov.br',
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
            'texto' => 'Pauta validacao professor',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = $this->criarAvaliacao('Avaliacao Validacao Professor', $tipo, $periodo);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach($turma->id);
        $this->sincronizarEscopoAvaliacao($avaliacao, [$serie->id], [$componente->id], [$escola->id]);

        Aluno::query()->create([
            'nome' => 'Aluno Validacao Professor',
            'cgm' => 'CGM-VAL-PROF-001',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);

        Livewire::actingAs($userProfessor)
            ->test(AvaliacaoTurmaWorkspace::class, $this->workspaceProfessorParams())
            ->set('avaliacao', $avaliacao->id)
            ->set('serieEscola', $escola->id . ':' . $serie->id)
            ->assertSee('Validar pend');
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

    private function workspaceProfessorParams(bool $canEdit = true): array
    {
        return [
            'modo' => 'professor',
            'canEdit' => $canEdit,
        ];
    }
}
