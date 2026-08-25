<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\Relatorios\DashboardAvaliacoes;
use App\Jobs\ProcessExportRequestJob;
use App\Livewire\Avaliacoes\AvaliacaoTurmaWorkspace;
use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\FuncaoAdministrativa;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorFuncaoTurma;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Services\Avaliacoes\AvaliacaoDashboardOnDemandQueryService;
use App\Services\Avaliacoes\TurmaAvaliacaoAlunoScopeService;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesAvaliacaoDocumentos;
use Tests\TestCase;

class DashboardAvaliacoesPageTest extends TestCase
{
    use CreatesAvaliacaoDocumentos;
    use RefreshDatabase;

    public function test_dashboard_lista_serie_do_escopo_mesmo_sem_alunos_ou_fatos(): void
    {
        $user = User::factory()->create(['email_approved' => true, 'email_verified_at' => now()]);
        $this->actingAs($user);

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer SRM', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo SRM', 'status' => true]);
        $serie = $this->criarSerie('SER-SRM-VAZIA', 'Sala de Recursos Multifuncionais');
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-SRM', 'nome' => 'SRM']);
        $escola = $this->criarEscola('Escola SRM');
        $user->escolas()->attach($escola->id);
        $turma = $this->criarTurma($escola, $serie, 'A', 'manha');
        $pauta = $this->criarPauta($tipo, $serie, $componente, 'Pauta SRM');

        $avaliacao = $this->criarAvaliacao('Avaliação SRM', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        $page = app(DashboardAvaliacoes::class);
        $filtrosPadrao = new \ReflectionMethod($page, 'filtrosPadrao');
        $page->filtros = $filtrosPadrao->invoke($page);
        $montarSeries = new \ReflectionMethod($page, 'montarPreenchimentoPorSeries');
        $series = collect($montarSeries->invoke($page, [$avaliacao->id]))->keyBy('nome');

        $this->assertSame(0, $series->get('Sala de Recursos Multifuncionais')['preenchimentos_esperados']);
        $this->assertSame(0, $series->get('Sala de Recursos Multifuncionais')['preenchimentos_pendentes']);
    }

    public function test_turma_integral_vazia_usa_alunos_da_turma_base_no_workspace_e_dashboard(): void
    {
        Queue::fake();
        Permission::findOrCreate('Acompanhar Avaliações');

        $user = User::factory()->create(['email_approved' => true, 'email_verified_at' => now()]);
        $user->givePermissionTo('Acompanhar Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Integral', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Integral', 'status' => true]);
        $serieBase = $this->criarSerie('SER-BASE-INT', '1º Ano');
        $serieIntegral = $this->criarSerie('SER-INT', '1º Ano - Integral');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-INT',
            'nome' => 'Recomposição - Matemática',
        ]);
        $escola = $this->criarEscola('Escola Integral');
        $user->escolas()->attach($escola->id);

        $turmaBase = $this->criarTurma($escola, $serieBase, 'A', 'integral');
        $turmaIntegral = $this->criarTurma($escola, $serieIntegral, 'A', 'integral');
        $aluno = $this->criarAluno($turmaBase, 'Aluno Integral', 'CGM-INT-001');

        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-INT',
            'nome' => 'Professor Integral',
            'email' => 'prof.integral@edu.umuarama.pr.gov.br',
        ]);
        $turmaIntegral->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta = $this->criarPauta($tipo, $serieIntegral, $componente, 'Pauta integral');
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = $this->criarAvaliacao('Avaliação Integral', $tipo, $periodo);
        $avaliacao->series()->sync([$serieBase->id, $serieIntegral->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->turmas()->sync([$turmaBase->id, $turmaIntegral->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        $workspace = Livewire::actingAs($user)
            ->test(AvaliacaoTurmaWorkspace::class, [
                'avaliacaoId' => $avaliacao->id,
                'turmaId' => $turmaIntegral->id,
                'escolaId' => $escola->id,
                'serieId' => $serieIntegral->id,
                'initialComponenteId' => $componente->id,
                'modo' => 'acompanhamento',
                'canEdit' => true,
            ]);

        $this->assertTrue($workspace->instance()->alunosDaTurma((int) $turmaIntegral->id)->contains('id', $aluno->id));

        $workspace->set("respostas.{$pauta->id}.{$aluno->id}.alternativa_id", $alternativa->id);

        $documento = AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $avaliacao->id)
            ->where('aluno_id', $aluno->id)
            ->firstOrFail();

        $this->assertSame($alternativa->id, (int) $documento->payload['pautas'][(string) $pauta->id]['alternativa_id']);
        $this->assertSame($professor->id, (int) $documento->payload['pautas'][(string) $pauta->id]['professor_id']);
        $this->assertSame(1, $documento->total_pautas_esperadas);

        $this->actingAs($user);
        $dashboard = app(DashboardAvaliacoes::class);
        $dashboard->mount();
        $dashboard->filtros['avaliacao_id'] = $avaliacao->id;
        $dashboard->carregarResumoDashboard();
        $dashboard->carregarAcompanhamentoDashboard();

        $linhaIntegral = collect($dashboard->acompanhamentoTurmas)
            ->firstWhere('turma_id', $turmaIntegral->id);

        $this->assertSame(1, $linhaIntegral['preenchimentos_esperados']);
        $this->assertSame(1, $linhaIntegral['preenchimentos_respondidos']);
        $this->assertSame(100.0, $linhaIntegral['percentual_preenchimento']);
        $this->assertDatabaseCount('avaliacao_dashboard_fatos', 0);
    }

    public function test_calculo_sob_demanda_resolve_regulares_integrais_e_srm_pela_regra_do_workspace(): void
    {
        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer completo', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo completo', 'status' => true]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-COMPLETO',
            'nome' => 'Componente completo',
        ]);
        $escola = $this->criarEscola('Escola completa');

        $series = collect([
            $this->criarSerie('SER-1-REG', '1º Ano'),
            $this->criarSerie('SER-2-REG', '2º Ano'),
            $this->criarSerie('SER-1-INT', '1º Ano - Integral'),
            $this->criarSerie('SER-2-INT', '2º Ano - Integral'),
            $this->criarSerie('srm_serie', 'Sala de Recursos Multifuncionais'),
        ]);

        $turmas = $series->mapWithKeys(fn (Serie $serie): array => [
            (int) $serie->id => $this->criarTurma($escola, $serie, 'A', 'integral'),
        ]);

        $alunoPrimeiro = $this->criarAluno($turmas->get((int) $series[0]->id), 'Aluno primeiro', 'CGM-COMP-001');
        $alunoSegundo = $this->criarAluno($turmas->get((int) $series[1]->id), 'Aluno segundo', 'CGM-COMP-002');
        $contraTurnoComum = $this->criarAluno(
            $turmas->get((int) $series[0]->id),
            'Contra-turno comum',
            'CGM-COMP-003',
            Aluno::TIPO_VINCULO_CONTRA_TURNO,
        );
        $alunoSrm = $this->criarAluno(
            $turmas->get((int) $series[4]->id),
            'Aluno SRM',
            'CGM-COMP-004',
            Aluno::TIPO_VINCULO_CONTRA_TURNO,
        );
        $principalIndevidoSrm = $this->criarAluno(
            $turmas->get((int) $series[4]->id),
            'Principal SRM',
            'CGM-COMP-005',
        );

        $pautas = $series->map(fn (Serie $serie): Pauta => $this->criarPauta(
            $tipo,
            $serie,
            $componente,
            'Pauta '.$serie->nome,
        ));

        $avaliacao = $this->criarAvaliacao('Avaliação completa', $tipo, $periodo);
        $avaliacao->series()->sync($series->pluck('id'));
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->turmas()->sync($turmas->pluck('id'));
        $avaliacao->pautas()->sync($pautas->pluck('id'));

        $escopos = app(TurmaAvaliacaoAlunoScopeService::class)->escoposPorTurma($turmas->values());

        $this->assertSame((int) $turmas->get((int) $series[0]->id)->id, $escopos[(int) $turmas->get((int) $series[2]->id)->id]['turma_origem_id']);
        $this->assertSame((int) $turmas->get((int) $series[1]->id)->id, $escopos[(int) $turmas->get((int) $series[3]->id)->id]['turma_origem_id']);
        $this->assertSame(Aluno::TIPO_VINCULO_CONTRA_TURNO, $escopos[(int) $turmas->get((int) $series[4]->id)->id]['tipo_vinculo']);

        $linhas = app(AvaliacaoDashboardOnDemandQueryService::class)
            ->esperados([$avaliacao->id])
            ->get(['at.turma_id', 'aln.id as aluno_id']);

        $this->assertCount(8, $linhas);
        $this->assertTrue($linhas->contains(fn (object $linha): bool => (int) $linha->turma_id === (int) $turmas->get((int) $series[2]->id)->id
            && (int) $linha->aluno_id === (int) $alunoPrimeiro->id));
        $this->assertTrue($linhas->contains(fn (object $linha): bool => (int) $linha->turma_id === (int) $turmas->get((int) $series[3]->id)->id
            && (int) $linha->aluno_id === (int) $alunoSegundo->id));
        $this->assertTrue($linhas->contains(fn (object $linha): bool => (int) $linha->aluno_id === (int) $contraTurnoComum->id));
        $this->assertTrue($linhas->contains(fn (object $linha): bool => (int) $linha->aluno_id === (int) $alunoSrm->id));
        $this->assertTrue($linhas->contains(fn (object $linha): bool => (int) $linha->aluno_id === (int) $principalIndevidoSrm->id));
        $this->assertDatabaseCount('avaliacao_dashboard_fatos', 0);
    }

    public function test_dashboard_carrega_indicadores_de_pendencia_apos_selecionar_avaliacao(): void
    {
        Permission::findOrCreate('Acompanhar Avaliações');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Acompanhar Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Dashboard', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Dashboard', 'status' => true]);
        $serie = $this->criarSerie('SER-DASH', '1o Ano');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-DASH',
            'nome' => 'Lingua Portuguesa',
        ]);

        $escolaManha = $this->criarEscola('Escola Manha');
        $escolaTarde = $this->criarEscola('Escola Tarde');
        $user->escolas()->attach([$escolaManha->id, $escolaTarde->id]);
        $turmaManha = $this->criarTurma($escolaManha, $serie, 'Turma A', 'manha');
        $turmaTarde = $this->criarTurma($escolaTarde, $serie, 'Turma B', 'tarde');

        $alunoManhaUm = $this->criarAluno($turmaManha, 'Aluno Manha 1', 'CGM-DASH-001');
        $alunoManhaDois = $this->criarAluno($turmaManha, 'Aluno Manha 2', 'CGM-DASH-002');
        $alunoManhaParcial = $this->criarAluno($turmaManha, 'Aluno Manha Parcial', 'CGM-DASH-004');
        $this->criarAluno($turmaTarde, 'Aluno Tarde 1', 'CGM-DASH-003');
        $this->criarAluno($turmaTarde, 'Aluno Manha 1 Contra Turno', 'CGM-DASH-001', Aluno::TIPO_VINCULO_CONTRA_TURNO);

        $alternativaSim = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Sim',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $alternativaNao = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Nao',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pautaUm = $this->criarPauta($tipo, $serie, $componente, 'Pauta dashboard 1');
        $pautaDois = $this->criarPauta($tipo, $serie, $componente, 'Pauta dashboard 2');
        $pautaUm->alternativas()->attach([$alternativaSim->id, $alternativaNao->id]);
        $pautaDois->alternativas()->attach([$alternativaSim->id, $alternativaNao->id]);

        $avaliacao = $this->criarAvaliacao('Avaliacao Dashboard', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escolaManha->id, $escolaTarde->id]);
        $avaliacao->turmas()->sync([$turmaManha->id, $turmaTarde->id]);
        $avaliacao->pautas()->sync([$pautaUm->id, $pautaDois->id]);

        $this->registrarResposta($avaliacao, $turmaManha, $alunoManhaUm, $pautaUm, $alternativaSim);
        $this->registrarResposta($avaliacao, $turmaManha, $alunoManhaUm, $pautaDois, $alternativaNao);
        $this->registrarResposta($avaliacao, $turmaManha, $alunoManhaDois, $pautaUm, $alternativaSim);
        $this->registrarResposta($avaliacao, $turmaManha, $alunoManhaDois, $pautaDois, $alternativaNao);
        $this->registrarResposta($avaliacao, $turmaManha, $alunoManhaParcial, $pautaUm, $alternativaSim);

        $component = Livewire::actingAs($user)
            ->test(DashboardAvaliacoes::class);

        $this->assertSame(0, $component->instance()->cards['preenchimentos_esperados']);
        $this->assertSame([], $component->instance()->tabelaEscolas);

        $component->set('filtros.avaliacao_id', $avaliacao->id);
        $component
            ->assertSee('Preenchimento geral')
            ->assertSee('por escola')
            ->assertSee('Preenchimento por componente')
            ->assertSee('Preenchimento por s');

        $cards = $component->instance()->cards;

        $this->assertSame(10, $cards['preenchimentos_esperados']);
        $this->assertSame(5, $cards['preenchimentos_respondidos']);
        $this->assertSame(5, $cards['preenchimentos_pendentes']);
        $this->assertEquals(50.0, $cards['percentual_preenchimento_geral']);
        $this->assertEquals(50.0, $cards['percentual_alunos_sem_resposta_pautas']);
        $this->assertEquals(0.0, $cards['percentual_turmas_preenchidas']);
        $this->assertSame(2, $cards['turmas_incompletas']);
        $this->assertEquals(0.0, $cards['percentual_escolas_preenchidas']);
        $this->assertEquals(83.3, $cards['percentual_turno_manha']);
        $this->assertEquals(0.0, $cards['percentual_turno_tarde']);
        $this->assertSame(1, $cards['turno_manha_alunos_pendentes']);
        $this->assertSame(3, $cards['turno_manha_alunos_total']);
        $this->assertSame(2, $cards['turno_tarde_alunos_pendentes']);
        $this->assertSame(2, $cards['turno_tarde_alunos_total']);

        $componentes = collect($component->instance()->preenchimentoPorComponentes)->keyBy('nome');

        $this->assertSame(10, $componentes->get('Lingua Portuguesa')['preenchimentos_esperados']);
        $this->assertSame(5, $componentes->get('Lingua Portuguesa')['preenchimentos_respondidos']);
        $this->assertSame(5, $componentes->get('Lingua Portuguesa')['preenchimentos_pendentes']);
        $this->assertEquals(50.0, $componentes->get('Lingua Portuguesa')['percentual_preenchimento']);

        $series = collect($component->instance()->preenchimentoPorSeries)->keyBy('nome');

        $this->assertSame(10, $series->get('1o Ano')['preenchimentos_esperados']);
        $this->assertSame(5, $series->get('1o Ano')['preenchimentos_respondidos']);
        $this->assertSame(5, $series->get('1o Ano')['preenchimentos_pendentes']);
        $this->assertEquals(50.0, $series->get('1o Ano')['percentual_preenchimento']);

        $graficoEscolas = collect($component->instance()->turmasIncompletasPorEscola)->keyBy('nome');

        $this->assertSame(1, $graficoEscolas->get('Escola Manha')['total']);
        $this->assertSame(1, $graficoEscolas->get('Escola Tarde')['total']);
        $this->assertEquals(100.0, $graficoEscolas->get('Escola Tarde')['percentual']);

        $tabelaEscolas = collect($component->instance()->tabelaEscolas)->keyBy('nome');

        $this->assertSame(1, $tabelaEscolas->get('Escola Manha')['turmas_incompletas']);
        $this->assertSame(0, $tabelaEscolas->get('Escola Manha')['turmas_preenchidas']);
        $this->assertSame(1, $tabelaEscolas->get('Escola Tarde')['turmas_incompletas']);
        $this->assertSame(0, $tabelaEscolas->get('Escola Tarde')['turmas_preenchidas']);

        $component->set('filtrosAcompanhamento.turno', 'manha');
        $this->assertSame(
            ['Turma A'],
            collect($component->instance()->acompanhamentoTurmas)->pluck('turma_nome')->values()->all()
        );

        $component->set('filtrosAcompanhamento', [
            'escola_id' => $escolaTarde->id,
            'serie_id' => null,
            'turno' => null,
            'componente_id' => null,
        ]);
        $this->assertSame(
            ['Turma B'],
            collect($component->instance()->acompanhamentoTurmas)->pluck('turma_nome')->values()->all()
        );
    }

    public function test_listagem_de_acompanhamento_tem_paginacao_configuravel(): void
    {
        Permission::findOrCreate('Acompanhar Avaliações');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Acompanhar Avaliações');

        $component = Livewire::actingAs($user)
            ->test(DashboardAvaliacoes::class);

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Paginacao', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Paginacao', 'status' => true]);
        $serie = $this->criarSerie('SER-PAG', '2o Ano');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-PAG',
            'nome' => 'Matematica',
        ]);
        $escola = $this->criarEscola('Escola Paginacao');
        $user->escolas()->attach($escola->id);
        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Sim',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta = $this->criarPauta($tipo, $serie, $componente, 'Pauta paginacao');
        $pauta->alternativas()->attach([$alternativa->id]);

        $avaliacao = $this->criarAvaliacao('Avaliacao Paginacao', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        $turmasIds = [];

        for ($i = 1; $i <= 7; $i++) {
            $turma = $this->criarTurma($escola, $serie, 'Turma Pag '.$i, 'manha');
            $aluno = $this->criarAluno($turma, 'Aluno Pag '.$i, 'CGM-PAG-00'.$i);
            $turmasIds[] = $turma->id;

            $this->registrarResposta($avaliacao, $turma, $aluno, $pauta, $alternativa);
        }

        $avaliacao->turmas()->sync($turmasIds);

        $component->set('filtros.avaliacao_id', $avaliacao->id);

        $this->assertSame(7, $component->instance()->acompanhamentoTurmasTotal);
        $this->assertSame(5, $component->instance()->acompanhamentoTurmasPorPagina);
        $this->assertSame(1, $component->instance()->acompanhamentoTurmasPagina);
        $this->assertCount(5, $component->instance()->acompanhamentoTurmas);
        $this->assertSame(5, $component->instance()->listagensPorPagina['tabelaEscolas']);

        $component
            ->set('tabelaEscolas', array_fill(0, 7, [
                'nome' => 'Escola',
                'esta_preenchida' => false,
                'percentual_pendentes' => 100.0,
                'preenchimentos_pendentes' => 1,
                'preenchimentos_respondidos' => 0,
                'preenchimentos_esperados' => 1,
                'percentual_preenchimento' => 0.0,
                'turmas_com_resposta' => 0,
                'turmas_esperadas' => 1,
                'turmas_preenchidas' => 0,
                'turmas_incompletas' => 1,
                'percentual_turmas_incompletas' => 100.0,
            ]))
            ->call('proximaPaginaListagem', 'tabelaEscolas');

        $this->assertSame(2, $component->instance()->listagensPaginas['tabelaEscolas']);

        $component->set('listagensPorPagina.tabelaEscolas', 10);

        $this->assertSame(10, $component->instance()->listagensPorPagina['tabelaEscolas']);
        $this->assertSame(1, $component->instance()->listagensPaginas['tabelaEscolas']);

        $component->call('proximaPaginaAcompanhamentoTurmas');

        $this->assertSame(2, $component->instance()->acompanhamentoTurmasPagina);
        $this->assertCount(2, $component->instance()->acompanhamentoTurmas);

        $component->set('acompanhamentoTurmasPorPagina', 25);

        $this->assertSame(25, $component->instance()->acompanhamentoTurmasPorPagina);
        $this->assertSame(1, $component->instance()->acompanhamentoTurmasPagina);
        $this->assertCount(7, $component->instance()->acompanhamentoTurmas);
    }

    public function test_usuario_vinculado_a_uma_escola_nao_ve_card_de_pendencia_por_escola(): void
    {
        Permission::findOrCreate('Acompanhar AvaliaÃ§Ãµes');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Acompanhar AvaliaÃ§Ãµes');

        $escola = $this->criarEscola('Escola Unica');
        $user->escolas()->attach($escola->id);

        $this->actingAs($user);

        $page = app(DashboardAvaliacoes::class);
        $page->mount();

        $this->assertFalse($page->getPodeVerPendenciaPorEscolaProperty());
    }

    public function test_acompanhamento_exige_permissao_especifica(): void
    {
        Permission::findOrCreate('Acompanhar Avaliações');
        Permission::findOrCreate('Listar Avaliações');

        $semPermissao = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($semPermissao)
            ->get(route('filament.admin.pages.dashboard-avaliacoes'))
            ->assertForbidden();

        $apenasListar = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $apenasListar->givePermissionTo('Listar Avaliações');

        $this->actingAs($apenasListar)
            ->get(route('filament.admin.pages.dashboard-avaliacoes'))
            ->assertForbidden();

        $comAcompanhamento = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $comAcompanhamento->givePermissionTo('Acompanhar Avaliações');

        $this->actingAs($comAcompanhamento)
            ->get(route('filament.admin.pages.dashboard-avaliacoes'))
            ->assertOk();
    }

    public function test_dashboard_abre_avaliacao_da_url_e_usuario_sem_vinculo_visualiza_todas_as_escolas(): void
    {
        Permission::findOrCreate('Acompanhar Avaliações');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Acompanhar Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Global', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Global', 'status' => true]);
        $serie = $this->criarSerie('SER-GLOBAL', '4o Ano');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-GLOBAL',
            'nome' => 'Geografia',
        ]);

        $escolaNorte = $this->criarEscola('Escola Norte');
        $escolaSul = $this->criarEscola('Escola Sul');
        $turmaNorte = $this->criarTurma($escolaNorte, $serie, 'Turma Norte', 'manha');
        $turmaSul = $this->criarTurma($escolaSul, $serie, 'Turma Sul', 'tarde');

        $this->criarAluno($turmaNorte, 'Aluno Norte', 'CGM-GLOBAL-001');
        $this->criarAluno($turmaSul, 'Aluno Sul', 'CGM-GLOBAL-002');

        $pauta = $this->criarPauta($tipo, $serie, $componente, 'Pauta global');
        $avaliacao = $this->criarAvaliacao('Avaliacao Global', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escolaNorte->id, $escolaSul->id]);
        $avaliacao->turmas()->sync([$turmaNorte->id, $turmaSul->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        $component = Livewire::withQueryParams(['avaliacao' => $avaliacao->id])
            ->actingAs($user)
            ->test(DashboardAvaliacoes::class);

        $this->assertSame($avaliacao->id, $component->instance()->filtros['avaliacao_id']);
        $this->assertFalse($component->instance()->dashboardCarregado);
        $this->assertSame(0, $component->instance()->cards['preenchimentos_esperados']);

        $component->call('carregarDashboardInicial');

        $this->assertTrue($component->instance()->dashboardCarregado);
        $this->assertSame(2, $component->instance()->cards['preenchimentos_esperados']);
        $this->assertSame(
            ['Escola Norte', 'Escola Sul'],
            collect($component->instance()->tabelaEscolas)->pluck('nome')->sort()->values()->all()
        );
        $this->assertArrayHasKey($escolaNorte->id, $component->instance()->escolasOptions);
        $this->assertArrayHasKey($escolaSul->id, $component->instance()->escolasOptions);
    }

    public function test_botao_atualizar_recalcula_documentos_sem_gerar_fatos_ou_jobs(): void
    {
        Queue::fake();
        Permission::findOrCreate('Acompanhar Avaliações');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Acompanhar Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Atualização', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Período Atualização', 'status' => true]);
        $serie = $this->criarSerie('SER-ATUALIZAR-DASH', '5o Ano');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-ATUALIZAR-DASH',
            'nome' => 'História',
        ]);
        $escola = $this->criarEscola('Escola Atualização Dashboard');
        $user->escolas()->attach($escola->id);
        $servidor = Servidor::query()->create([
            'user_id' => $user->id,
            'id_escola' => $escola->id,
            'matricula' => 'SERV-ATUALIZAR-DASH',
            'nome' => $user->name,
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        Professor::query()->create([
            'user_id' => $user->id,
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-ATUALIZAR-DASH',
            'nome' => $user->name,
            'email' => $user->email,
            'ativo' => true,
        ]);
        $turma = $this->criarTurma($escola, $serie, 'Turma Atualização', 'manha');
        $aluno = $this->criarAluno($turma, 'Aluno Atualização', 'CGM-ATUALIZAR-DASH');
        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Sim',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta = $this->criarPauta($tipo, $serie, $componente, 'Pauta atualização dashboard');
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = $this->criarAvaliacao('Avaliação Atualização Dashboard', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        $this->actingAs($user);
        $dashboard = app(DashboardAvaliacoes::class);
        $dashboard->mount();
        $dashboard->filtros['avaliacao_id'] = $avaliacao->id;
        $dashboard->carregarResumoDashboard();

        $this->assertSame(0, $dashboard->cards['preenchimentos_respondidos']);

        $this->registrarResposta($avaliacao, $turma, $aluno, $pauta, $alternativa);

        $this->assertSame(0, $dashboard->cards['preenchimentos_respondidos']);

        $dashboard->atualizarDadosRecentes(silencioso: true);

        $this->assertSame(1, $dashboard->cards['preenchimentos_respondidos']);
        $this->assertSame(1, collect($dashboard->acompanhamentoTurmas)->first()['preenchimentos_respondidos']);
        $this->assertDatabaseCount('avaliacao_dashboard_fatos', 0);
        $this->assertDatabaseCount('avaliacao_dashboard_pendencias', 0);
        $this->assertDatabaseCount('avaliacao_dashboard_escopo_pendencias', 0);
        Queue::assertNothingPushed();
    }

    public function test_usuario_vinculado_visualiza_apenas_escolas_permitidas_no_acompanhamento(): void
    {
        Permission::findOrCreate('Acompanhar Avaliações');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Acompanhar Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Escopo', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Escopo', 'status' => true]);
        $serie = $this->criarSerie('SER-ESCOPO', '3o Ano');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-ESCOPO',
            'nome' => 'Ciencias',
        ]);
        $escolaPermitida = $this->criarEscola('Escola Permitida');
        $escolaBloqueada = $this->criarEscola('Escola Bloqueada');
        $user->escolas()->attach($escolaPermitida->id);

        $turmaPermitida = $this->criarTurma($escolaPermitida, $serie, 'Turma Permitida', 'manha');
        $turmaBloqueada = $this->criarTurma($escolaBloqueada, $serie, 'Turma Bloqueada', 'tarde');
        $alunoPermitido = $this->criarAluno($turmaPermitida, 'Aluno Permitido', 'CGM-ESCOPO-001');
        $this->criarAluno($turmaBloqueada, 'Aluno Bloqueado', 'CGM-ESCOPO-002');

        $professorPermitido = Professor::query()->create([
            'id_escola' => $escolaPermitida->id,
            'matricula' => 'PROF-ESCOPO',
            'nome' => 'Professor Permitido',
            'email' => 'professor.escopo@edu.umuarama.pr.gov.br',
        ]);
        $turmaPermitida->componentes()->attach($componente->id, [
            'professor_id' => $professorPermitido->id,
            'tem_professor' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Sim',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta = $this->criarPauta($tipo, $serie, $componente, 'Pauta escopo escolar');
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = $this->criarAvaliacao('Avaliacao Escopo', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escolaPermitida->id, $escolaBloqueada->id]);
        $avaliacao->turmas()->sync([$turmaPermitida->id, $turmaBloqueada->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        $this->registrarResposta($avaliacao, $turmaPermitida, $alunoPermitido, $pauta, $alternativa, $professorPermitido);

        $component = Livewire::actingAs($user)
            ->test(DashboardAvaliacoes::class)
            ->set('filtros.avaliacao_id', $avaliacao->id);

        $escolasOptions = $component->instance()->escolasOptions;
        $this->assertArrayHasKey($escolaPermitida->id, $escolasOptions);
        $this->assertArrayNotHasKey($escolaBloqueada->id, $escolasOptions);

        $this->assertSame(1, $component->instance()->cards['preenchimentos_esperados']);
        $this->assertSame(1, $component->instance()->cards['preenchimentos_respondidos']);
        $this->assertSame(['Escola Permitida'], collect($component->instance()->tabelaEscolas)->pluck('nome')->all());

        $acompanhamento = collect($component->instance()->acompanhamentoTurmas);
        $this->assertSame(['Turma Permitida'], $acompanhamento->pluck('turma_nome')->unique()->values()->all());
        $this->assertSame('Todos os professores', $acompanhamento->first()['professor_nome']);
        $this->assertSame('concluido', $acompanhamento->first()['status']);

        $component->set('filtros.professores_ids', [$professorPermitido->id]);

        $this->assertSame(1, $component->instance()->cards['preenchimentos_esperados']);
        $this->assertSame(['Todos os professores'], collect($component->instance()->acompanhamentoTurmas)->pluck('professor_nome')->unique()->values()->all());

        $component->set('filtros.escolas_ids', [$escolaBloqueada->id]);

        $this->assertSame([], $component->instance()->filtros['escolas_ids']);
        $this->assertSame(1, $component->instance()->cards['preenchimentos_esperados']);
        $this->assertSame(['Escola Permitida'], collect($component->instance()->tabelaEscolas)->pluck('nome')->all());
        $this->assertSame(['Turma Permitida'], collect($component->instance()->acompanhamentoTurmas)->pluck('turma_nome')->unique()->values()->all());
    }

    public function test_acompanhamento_consolidado_nao_depende_do_professor_registrado_na_resposta(): void
    {
        $permissaoAcompanharAvaliacoes = 'Acompanhar Avalia'."\u{00E7}\u{00F5}".'es';
        Permission::findOrCreate($permissaoAcompanharAvaliacoes);

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo($permissaoAcompanharAvaliacoes);

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Consolidado', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Consolidado', 'status' => true]);
        $serie = $this->criarSerie('SER-CONSOLIDADO', '4o Ano');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-CONSOLIDADO',
            'nome' => 'Arte',
        ]);
        $escola = $this->criarEscola('Escola Consolidado');
        $user->escolas()->attach($escola->id);

        $turma = $this->criarTurma($escola, $serie, 'Turma Consolidado', 'tarde');
        $aluno = $this->criarAluno($turma, 'Aluno Consolidado', 'CGM-CONSOLIDADO-001');
        $professorAtual = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-CONSOLIDADO',
            'nome' => 'Professor Atual',
            'email' => 'professor.consolidado@edu.umuarama.pr.gov.br',
        ]);
        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professorAtual->id,
            'tem_professor' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Sim',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta = $this->criarPauta($tipo, $serie, $componente, 'Pauta consolidada');
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = $this->criarAvaliacao('Avaliacao Consolidada', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        $this->registrarResposta($avaliacao, $turma, $aluno, $pauta, $alternativa);

        $component = Livewire::actingAs($user)
            ->test(DashboardAvaliacoes::class);

        $component->set('filtros.avaliacao_id', $avaliacao->id);

        $linha = collect($component->instance()->acompanhamentoTurmas)->first();

        $this->assertSame(1, $linha['preenchimentos_esperados']);
        $this->assertSame(1, $linha['preenchimentos_respondidos']);
        $this->assertSame(100.0, $linha['percentual_preenchimento']);
        $this->assertSame('concluido', $linha['status']);
    }

    public function test_dashboard_abre_workspace_no_escopo_e_resposta_salva_atualiza_status_da_linha(): void
    {
        Permission::findOrCreate('Acompanhar AvaliaÃ§Ãµes');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Acompanhar AvaliaÃ§Ãµes');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Workspace', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Workspace', 'status' => true]);
        $serie = $this->criarSerie('SER-WORK', 'Infantil 4');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-WORK',
            'nome' => 'Corpo, gesto e movimento',
        ]);
        $escola = $this->criarEscola('Escola Workspace');
        $user->escolas()->attach($escola->id);

        $turma = $this->criarTurma($escola, $serie, 'A', 'manha');
        $alunoUm = $this->criarAluno($turma, 'Aluno Workspace 1', 'CGM-WORK-001');
        $alunoDois = $this->criarAluno($turma, 'Aluno Workspace 2', 'CGM-WORK-002');

        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-WORK',
            'nome' => 'Professor Workspace',
            'email' => 'prof.workspace@edu.umuarama.pr.gov.br',
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

        $pauta = $this->criarPauta($tipo, $serie, $componente, 'Pauta workspace');
        $pauta->alternativas()->attach([$alternativa->id]);

        $avaliacao = $this->criarAvaliacao('Avaliacao Workspace', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        $workspace = Livewire::actingAs($user)
            ->test(AvaliacaoTurmaWorkspace::class, [
                'avaliacaoId' => $avaliacao->id,
                'turmaId' => $turma->id,
                'escolaId' => $escola->id,
                'serieId' => $serie->id,
                'initialComponenteId' => $componente->id,
                'modo' => 'acompanhamento',
                'canEdit' => true,
            ])
            ->assertSet('visualizacao', 'pautas')
            ->assertSet('componenteWorkspaceId', (string) $componente->id)
            ->set("respostas.{$pauta->id}.{$alunoUm->id}.alternativa_id", $alternativa->id)
            ->set("respostas.{$pauta->id}.{$alunoDois->id}.alternativa_id", $alternativa->id);

        foreach ([$alunoUm, $alunoDois] as $aluno) {
            $documento = AvaliacaoAlunoDocumento::query()
                ->where('avaliacao_id', $avaliacao->id)
                ->where('aluno_id', $aluno->id)
                ->firstOrFail();
            $resposta = $documento->payload['pautas'][(string) $pauta->id] ?? [];

            $this->assertSame($turma->id, (int) $documento->turma_id);
            $this->assertSame($alternativa->id, (int) ($resposta['alternativa_id'] ?? 0));
            $this->assertSame($professor->id, (int) ($resposta['professor_id'] ?? 0));
        }

        $workspace->assertSet('modo', 'acompanhamento');
    }

    public function test_workspace_do_acompanhamento_bloqueia_abertura_fora_do_escopo_e_respeita_prefiltro_de_componente(): void
    {
        Permission::findOrCreate('Acompanhar AvaliaÃ§Ãµes');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Acompanhar AvaliaÃ§Ãµes');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Prefiltro', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Prefiltro', 'status' => true]);
        $serie = $this->criarSerie('SER-PREF', 'Infantil 5');
        $componenteUm = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-PREF-1',
            'nome' => 'Escuta e fala',
        ]);
        $componenteDois = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-PREF-2',
            'nome' => 'Traços e cores',
        ]);
        $escolaPermitida = $this->criarEscola('Escola Prefiltro');
        $escolaBloqueada = $this->criarEscola('Escola Fora do Escopo');
        $user->escolas()->attach($escolaPermitida->id);

        $turmaPermitida = $this->criarTurma($escolaPermitida, $serie, 'B', 'manha');
        $turmaBloqueada = $this->criarTurma($escolaBloqueada, $serie, 'C', 'manha');
        $alunoPermitido = $this->criarAluno($turmaPermitida, 'Aluno Prefiltro', 'CGM-PREF-001');

        $professor = Professor::query()->create([
            'id_escola' => $escolaPermitida->id,
            'matricula' => 'PROF-PREF',
            'nome' => 'Professor Prefiltro',
            'email' => 'prof.prefiltro@edu.umuarama.pr.gov.br',
        ]);
        $turmaPermitida->componentes()->attach($componenteUm->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);
        $turmaPermitida->componentes()->attach($componenteDois->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pautaUm = $this->criarPauta($tipo, $serie, $componenteUm, 'Pauta componente um');
        $pautaDois = $this->criarPauta($tipo, $serie, $componenteDois, 'Pauta componente dois');
        $pautaUm->alternativas()->attach([$alternativa->id]);
        $pautaDois->alternativas()->attach([$alternativa->id]);

        $avaliacao = $this->criarAvaliacao('Avaliacao Prefiltro', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componenteUm->id, $componenteDois->id]);
        $avaliacao->escolas()->sync([$escolaPermitida->id, $escolaBloqueada->id]);
        $avaliacao->turmas()->sync([$turmaPermitida->id, $turmaBloqueada->id]);
        $avaliacao->pautas()->sync([$pautaUm->id, $pautaDois->id]);

        $dashboard = Livewire::actingAs($user)
            ->test(DashboardAvaliacoes::class);

        $dashboard->set('filtros.avaliacao_id', $avaliacao->id);

        $dashboard
            ->call(
                'abrirWorkspaceAcompanhamento',
                $avaliacao->id,
                $turmaBloqueada->id,
                $escolaBloqueada->id,
                $serie->id,
                $componenteUm->id,
                0
            )
            ->assertSet('workspaceAcompanhamentoAberto', false)
            ->assertSet('workspaceAcompanhamentoLinha', null);

        $workspace = Livewire::actingAs($user)
            ->test(AvaliacaoTurmaWorkspace::class, [
                'avaliacaoId' => $avaliacao->id,
                'turmaId' => $turmaPermitida->id,
                'escolaId' => $escolaPermitida->id,
                'serieId' => $serie->id,
                'initialComponenteId' => $componenteUm->id,
                'modo' => 'acompanhamento',
                'canEdit' => true,
            ])
            ->assertSee('Por pautas')
            ->assertSee('Por alunos')
            ->assertDontSee('AvaliaÃ§Ã£o em massa')
            ->call('definirVisualizacao', 'alunos')
            ->assertSet('visualizacao', 'alunos');

        $this->assertCount(1, $workspace->instance()->getPautasDisponiveisProperty());

        $workspace
            ->set('avaliacaoEmMassaGlobal', $alternativa->id)
            ->call('aplicarEmMassaNaSerie');

        $documento = AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $avaliacao->id)
            ->where('aluno_id', $alunoPermitido->id)
            ->firstOrFail();

        $this->assertSame($alternativa->id, (int) ($documento->payload['pautas'][(string) $pautaUm->id]['alternativa_id'] ?? 0));
        $this->assertArrayNotHasKey((string) $pautaDois->id, $documento->payload['pautas']);

        $workspace->set('componenteWorkspaceId', '');

        $this->assertCount(2, $workspace->instance()->getPautasDisponiveisProperty());

        $workspace
            ->set('avaliacaoEmMassaGlobal', $alternativa->id)
            ->call('aplicarEmMassaNaSerie');

        $documento->refresh();

        $this->assertSame($alternativa->id, (int) ($documento->payload['pautas'][(string) $pautaDois->id]['alternativa_id'] ?? 0));
        $this->assertSame($professor->id, (int) ($documento->payload['pautas'][(string) $pautaDois->id]['professor_id'] ?? 0));
    }

    public function test_workspace_do_acompanhamento_carrega_somente_o_escopo_solicitado_com_todos_os_componentes(): void
    {
        Queue::fake();

        Permission::findOrCreate('Acompanhar AvaliaÃ§Ãµes');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Acompanhar AvaliaÃ§Ãµes');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Todos Componentes', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Todos Componentes', 'status' => true]);
        $serie = $this->criarSerie('SER-TODOS', 'Infantil 2');
        $componenteUm = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-TODOS-1',
            'nome' => 'Corpo e movimento',
        ]);
        $componenteDois = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-TODOS-2',
            'nome' => 'Escuta e fala',
        ]);
        $escola = $this->criarEscola('Escola Todos Componentes');
        $user->escolas()->attach($escola->id);

        $turma = $this->criarTurma($escola, $serie, 'A', 'manha');
        $this->criarAluno($turma, 'Aluno Todos Componentes', 'CGM-TODOS-001');

        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-TODOS',
            'nome' => 'Professor Todos Componentes',
            'email' => 'prof.todos@edu.umuarama.pr.gov.br',
        ]);
        $turma->componentes()->attach($componenteUm->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);
        $turma->componentes()->attach($componenteDois->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pautaUm = $this->criarPauta($tipo, $serie, $componenteUm, 'Pauta todos 1');
        $pautaDois = $this->criarPauta($tipo, $serie, $componenteDois, 'Pauta todos 2');
        $pautaUm->alternativas()->attach([$alternativa->id]);
        $pautaDois->alternativas()->attach([$alternativa->id]);

        $avaliacao = $this->criarAvaliacao('Avaliacao Todos Componentes', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componenteUm->id, $componenteDois->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->pautas()->sync([$pautaUm->id, $pautaDois->id]);

        $turmaForaDoWorkspace = $this->criarTurma($escola, $serie, 'B', 'tarde');
        $avaliacao->turmas()->sync([$turma->id, $turmaForaDoWorkspace->id]);

        $avaliacaoForaDoWorkspace = $this->criarAvaliacao('Avaliacao Fora do Workspace', $tipo, $periodo);
        $avaliacaoForaDoWorkspace->series()->sync([$serie->id]);
        $avaliacaoForaDoWorkspace->componentes()->sync([$componenteUm->id]);
        $avaliacaoForaDoWorkspace->escolas()->sync([$escola->id]);
        $avaliacaoForaDoWorkspace->turmas()->sync([$turma->id]);
        $avaliacaoForaDoWorkspace->pautas()->sync([$pautaUm->id]);

        $workspace = Livewire::actingAs($user)
            ->test(AvaliacaoTurmaWorkspace::class, [
                'avaliacaoId' => $avaliacao->id,
                'turmaId' => $turma->id,
                'escolaId' => $escola->id,
                'serieId' => $serie->id,
                'initialComponenteId' => null,
                'modo' => 'acompanhamento',
                'canEdit' => true,
            ])
            ->assertSet('componenteWorkspaceId', '')
            ->assertSee('Por pautas')
            ->assertSee('Por alunos')
            ->assertDontSee('Validar pend')
            ->assertSee('Corpo e movimento - Professor Todos Componentes')
            ->assertSee('Escuta e fala - Professor Todos Componentes')
            ->assertDontSee('Pauta todos 1')
            ->assertDontSee('Pauta todos 2');

        $this->assertCount(2, $workspace->instance()->getPautasDisponiveisProperty());
        $this->assertCount(1, $workspace->instance()->getAvaliacoesDisponiveisProperty());
        $this->assertCount(1, $workspace->instance()->getAvaliacaoAtualProperty()->turmas);

        $workspace
            ->call('alternarComponente', $turma->id, $componenteUm->id)
            ->assertSee('Pauta todos 1')
            ->call('alternarComponente', $turma->id, $componenteDois->id)
            ->assertSee('Pauta todos 2')
            ->call('alternarPauta', $turma->id, $pautaUm->id)
            ->assertSet('pautasExpandidas', [$turma->id.':'.$pautaUm->id])
            ->call('alternarPauta', $turma->id, $pautaDois->id)
            ->assertSet('pautasExpandidas', [$turma->id.':'.$pautaDois->id])
            ->call('definirVisualizacao', 'alunos')
            ->assertSet('pautasExpandidas', []);

        $aluno = Aluno::query()->where('id_turma', $turma->id)->firstOrFail();

        $workspace
            ->call('alternarAluno', $turma->id, $aluno->id)
            ->assertSet('alunosExpandidos', [$turma->id.':'.$aluno->id])
            ->call('definirVisualizacao', 'pautas')
            ->assertSet('alunosExpandidos', []);
    }

    public function test_exportar_parecer_no_acompanhamento_enfileira_pdf_da_turma_quando_ha_diretor_e_coordenacao(): void
    {
        Queue::fake();

        $dados = $this->criarCenarioExportacaoParecerAcompanhamento();
        $component = $dados['component'];
        $linha = $dados['linha'];

        $this->assertTrue($linha['parecer_exportavel']);
        $this->assertSame('', $linha['parecer_exportavel_motivo']);

        $component->call(
            'exportarParecerTurma',
            $linha['avaliacao_id'],
            $linha['turma_id'],
            $linha['escola_id'],
            $linha['serie_id'],
            $linha['componente_id'],
            $linha['professor_id']
        )->assertNotified('Exportação enviada para a fila');

        $exportRequest = ExportRequest::query()->firstOrFail();

        $this->assertSame('avaliacao_documento', $exportRequest->type);
        $this->assertSame('pdf', $exportRequest->format);
        $this->assertSame($linha['avaliacao_id'], $exportRequest->filters['avaliacao_id']);
        $this->assertSame('turma', $exportRequest->filters['escopo']);
        $this->assertSame($linha['turma_id'], $exportRequest->filters['turma_id']);

        $documento = AvaliacaoAlunoDocumento::query()
            ->where('avaliacao_id', $linha['avaliacao_id'])
            ->where('turma_id', $linha['turma_id'])
            ->firstOrFail();
        $this->assertNotEmpty($documento->responsaveis_snapshot);
        $this->assertNotNull($documento->responsaveis_snapshot_em);

        $component->assertRedirect();

        Queue::assertPushed(ProcessExportRequestJob::class, 1);
    }

    public function test_exportar_parecer_no_acompanhamento_bloqueia_quando_falta_gestor_obrigatorio(): void
    {
        Queue::fake();

        $dados = $this->criarCenarioExportacaoParecerAcompanhamento(comCoordenacao: false);
        $component = $dados['component'];
        $linha = $dados['linha'];

        $this->assertFalse($linha['parecer_exportavel']);
        $this->assertSame('A turma não possui coordenação ativa e vigente.', $linha['parecer_exportavel_motivo']);

        $component->call(
            'exportarParecerTurma',
            $linha['avaliacao_id'],
            $linha['turma_id'],
            $linha['escola_id'],
            $linha['serie_id'],
            $linha['componente_id'],
            $linha['professor_id']
        )->assertNotified('A turma não possui coordenação ativa e vigente.');

        $this->assertDatabaseCount('export_requests', 0);
        Queue::assertNothingPushed();
    }

    public function test_exportar_parecer_no_acompanhamento_nao_enfileira_linha_fora_do_escopo_ou_nao_concluida(): void
    {
        Queue::fake();

        $dadosEscopo = $this->criarCenarioExportacaoParecerAcompanhamento();
        $componentEscopo = $dadosEscopo['component'];
        $linhaEscopo = $dadosEscopo['linha'];

        $componentEscopo->call(
            'exportarParecerTurma',
            $linhaEscopo['avaliacao_id'],
            $linhaEscopo['turma_id'],
            $linhaEscopo['escola_id'] + 999,
            $linhaEscopo['serie_id'],
            $linhaEscopo['componente_id'],
            $linhaEscopo['professor_id']
        )->assertNotified('A avaliação selecionada não está mais disponível no seu escopo.');

        $this->assertDatabaseCount('export_requests', 0);
        Queue::assertNothingPushed();

        Notification::assertNotNotified('Exportação enviada para a fila');

        $dadosNaoConcluida = $this->criarCenarioExportacaoParecerAcompanhamento(concluida: false);
        $componentNaoConcluida = $dadosNaoConcluida['component'];
        $linhaNaoConcluida = $dadosNaoConcluida['linha'];

        $this->assertSame('nao_iniciado', $linhaNaoConcluida['status']);

        $componentNaoConcluida->call(
            'exportarParecerTurma',
            $linhaNaoConcluida['avaliacao_id'],
            $linhaNaoConcluida['turma_id'],
            $linhaNaoConcluida['escola_id'],
            $linhaNaoConcluida['serie_id'],
            $linhaNaoConcluida['componente_id'],
            $linhaNaoConcluida['professor_id']
        )->assertNotified('O parecer só pode ser exportado quando a turma estiver concluída.');

        $this->assertDatabaseCount('export_requests', 0);
        Queue::assertNothingPushed();
    }

    /**
     * @return array{component: Testable, linha: array<string, mixed>}
     */
    private function criarCenarioExportacaoParecerAcompanhamento(
        bool $comDiretor = true,
        bool $comCoordenacao = true,
        bool $concluida = true
    ): array {
        $sufixo = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6));

        Permission::findOrCreate('Acompanhar Avaliações');
        Permission::findOrCreate('Exportar Avaliações');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo(['Acompanhar Avaliações', 'Exportar Avaliações']);

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Exportação '.$sufixo, 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Período Exportação '.$sufixo, 'status' => true]);
        $serie = $this->criarSerie('SER-EXP-'.$sufixo, 'Infantil 3 '.$sufixo);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-EXP-'.$sufixo,
            'nome' => 'Escuta, fala, pensamento e imaginação '.$sufixo,
        ]);
        $escola = $this->criarEscola('Escola Exportação Parecer '.$sufixo);
        $user->escolas()->attach($escola->id);

        $turma = $this->criarTurma($escola, $serie, 'A', 'manha');
        $aluno = $this->criarAluno($turma, 'Aluno Exportação Parecer '.$sufixo, 'CGM-EXP-'.$sufixo);

        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-EXP-'.$sufixo,
            'nome' => 'Professor Exportação Parecer '.$sufixo,
            'email' => 'prof.exportacao.parecer.'.strtolower($sufixo).'@edu.umuarama.pr.gov.br',
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
        $pauta = $this->criarPauta($tipo, $serie, $componente, 'Pauta exportação parecer '.$sufixo);
        $pauta->alternativas()->attach([$alternativa->id]);

        $avaliacao = $this->criarAvaliacao('Avaliação Exportação Parecer '.$sufixo, $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        if ($concluida) {
            $this->registrarResposta($avaliacao, $turma, $aluno, $pauta, $alternativa, $professor);
        }

        $this->vincularGestoresDaTurma($escola, $turma, $comDiretor, $comCoordenacao);

        $component = Livewire::actingAs($user)
            ->test(DashboardAvaliacoes::class)
            ->set('filtros.avaliacao_id', $avaliacao->id);

        return [
            'component' => $component,
            'linha' => $component->instance()->acompanhamentoTurmas[0],
        ];
    }

    private function vincularGestoresDaTurma(
        Escola $escola,
        Turma $turma,
        bool $comDiretor,
        bool $comCoordenacao
    ): void {
        if ($comDiretor) {
            $diretora = Servidor::query()->create([
                'id_escola' => $escola->id,
                'nome' => 'Diretora Parecer',
                'email' => uniqid().'@edu.umuarama.pr.gov.br',
                'status' => Servidor::STATUS_ATIVO,
            ]);
            ServidorFuncaoAdministrativa::query()->create([
                'servidor_id' => $diretora->id,
                'funcao_administrativa_id' => FuncaoAdministrativa::direcaoPadrao()->id,
                'id_escola' => $escola->id,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                'origem' => 'teste',
                'portaria' => '111/2026',
                'principal' => true,
                'data_inicio' => now()->subDay()->toDateString(),
            ]);
        }

        if ($comCoordenacao) {
            $coordenadora = Servidor::query()->create([
                'id_escola' => $escola->id,
                'nome' => 'Coordenadora Parecer',
                'email' => uniqid().'@edu.umuarama.pr.gov.br',
                'status' => Servidor::STATUS_ATIVO,
            ]);
            $coordenacao = ServidorFuncaoAdministrativa::query()->create([
                'servidor_id' => $coordenadora->id,
                'funcao_administrativa_id' => FuncaoAdministrativa::coordenacaoPadrao()->id,
                'id_escola' => $escola->id,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                'origem' => 'teste',
                'portaria' => '222/2026',
                'data_inicio' => now()->subDay()->toDateString(),
            ]);
            ServidorFuncaoTurma::query()->create([
                'servidor_funcao_administrativa_id' => $coordenacao->id,
                'turma_id' => $turma->id,
                'principal' => true,
                'status' => ServidorFuncaoTurma::STATUS_ATIVO,
                'data_inicio' => now()->subDay()->toDateString(),
            ]);
        }
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
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
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

    private function criarTurma(Escola $escola, Serie $serie, string $nome, string $turno): Turma
    {
        return Turma::query()->create([
            'codigo' => 'TUR'.strtoupper(substr(md5($nome.$turno.microtime()), 0, 8)),
            'nome' => $nome,
            'turno' => $turno,
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarAluno(
        Turma $turma,
        string $nome,
        string $cgm,
        string $tipoVinculo = Aluno::TIPO_VINCULO_PRINCIPAL
    ): Aluno {
        return Aluno::query()->create([
            'nome' => $nome,
            'cgm' => $cgm,
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
            'tipo_vinculo' => $tipoVinculo,
        ]);
    }

    private function criarPauta(
        TipoAvaliacao $tipo,
        Serie $serie,
        ComponenteCurricular $componente,
        string $texto
    ): Pauta {
        return Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => $texto,
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
    }

    private function registrarResposta(
        Avaliacao $avaliacao,
        Turma $turma,
        Aluno $aluno,
        Pauta $pauta,
        Alternativa $alternativa,
        ?Professor $professor = null
    ): void {
        $this->criarDocumentoResposta([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
            'professor_id' => $professor?->id,
            'alternativa_id' => $alternativa->id,
            'respondido_em' => now(),
        ]);
    }
}
