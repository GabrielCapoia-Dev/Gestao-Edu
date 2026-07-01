<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\Relatorios\DashboardAvaliacoes;
use App\Jobs\ProcessExportRequestJob;
use App\Livewire\Avaliacoes\AvaliacaoTurmaWorkspace;
use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoInformacaoComplementar;
use App\Models\AvaliacaoResposta;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\FuncaoAdministrativa;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Services\Avaliacoes\AvaliacaoDashboardMetricsService;
use App\Services\ServidorService;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardAvaliacoesPageTest extends TestCase
{
    use RefreshDatabase;

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
            $turma = $this->criarTurma($escola, $serie, 'Turma Pag ' . $i, 'manha');
            $aluno = $this->criarAluno($turma, 'Aluno Pag ' . $i, 'CGM-PAG-00' . $i);
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

    public function test_cache_do_dashboard_e_invalidado_por_resposta_e_informacao_complementar(): void
    {
        cache()->flush();

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Cache Dashboard', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Cache Dashboard', 'status' => true]);
        $serie = $this->criarSerie('SER-CACHE-DASH', '5o Ano');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-CACHE-DASH',
            'nome' => 'Historia',
        ]);
        $escola = $this->criarEscola('Escola Cache Dashboard');
        $turma = $this->criarTurma($escola, $serie, 'Turma Cache', 'manha');
        $aluno = $this->criarAluno($turma, 'Aluno Cache', 'CGM-CACHE-DASH');
        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Sim',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta = $this->criarPauta($tipo, $serie, $componente, 'Pauta cache dashboard');
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = $this->criarAvaliacao('Avaliacao Cache Dashboard', $tipo, $periodo);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        $service = app(AvaliacaoDashboardMetricsService::class);

        $this->assertSame(1, $service->versionFor($avaliacao->id));

        $this->registrarResposta($avaliacao, $turma, $aluno, $pauta, $alternativa);

        $this->assertSame(2, $service->versionFor($avaliacao->id));

        AvaliacaoResposta::query()->firstOrFail()->update(['observacao' => 'Ajuste de cache']);

        $this->assertSame(3, $service->versionFor($avaliacao->id));

        AvaliacaoInformacaoComplementar::query()->create([
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
            'componente_curricular_id' => $componente->id,
            'informacoes_complementares' => 'Informacao para invalidar cache',
        ]);

        $this->assertSame(4, $service->versionFor($avaliacao->id));
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

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoUm->id,
            'professor_id' => $professor->id,
            'alternativa_id' => $alternativa->id,
        ]);

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoDois->id,
            'professor_id' => $professor->id,
            'alternativa_id' => $alternativa->id,
        ]);

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
            ]);

        $this->assertCount(1, $workspace->instance()->getPautasDisponiveisProperty());

        $workspace
            ->set('avaliacaoEmMassaGlobal', $alternativa->id)
            ->call('aplicarEmMassaNaSerie');

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pautaUm->id,
            'turma_id' => $turmaPermitida->id,
            'aluno_id' => $alunoPermitido->id,
            'professor_id' => $professor->id,
            'alternativa_id' => $alternativa->id,
        ]);
        $this->assertDatabaseMissing('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pautaDois->id,
            'turma_id' => $turmaPermitida->id,
            'aluno_id' => $alunoPermitido->id,
        ]);

        $workspace->set('componenteWorkspaceId', '');

        $this->assertCount(2, $workspace->instance()->getPautasDisponiveisProperty());

        $workspace
            ->set('avaliacaoEmMassaGlobal', $alternativa->id)
            ->call('aplicarEmMassaNaSerie');

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pautaDois->id,
            'turma_id' => $turmaPermitida->id,
            'aluno_id' => $alunoPermitido->id,
            'professor_id' => $professor->id,
            'alternativa_id' => $alternativa->id,
        ]);
    }

    public function test_workspace_do_acompanhamento_carrega_todos_os_componentes_no_primeiro_render_e_sem_validacao_manual(): void
    {
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
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->pautas()->sync([$pautaUm->id, $pautaDois->id]);

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
            ->assertSee('Todos os componentes')
            ->assertDontSee('Validar pend')
            ->assertSee('Pauta todos 1')
            ->assertSee('Pauta todos 2');

        $this->assertCount(2, $workspace->instance()->getPautasDisponiveisProperty());

        $workspace->set('componenteWorkspaceId', (string) $componenteUm->id);
        $this->assertCount(1, $workspace->instance()->getPautasDisponiveisProperty());

        $workspace->set('componenteWorkspaceId', '');
        $this->assertCount(2, $workspace->instance()->getPautasDisponiveisProperty());

        $workspace
            ->call('alternarPauta', $turma->id, $pautaUm->id)
            ->assertSet('pautasExpandidas', [$turma->id . ':' . $pautaUm->id])
            ->call('alternarPauta', $turma->id, $pautaDois->id)
            ->assertSet('pautasExpandidas', [$turma->id . ':' . $pautaDois->id])
            ->call('definirVisualizacao', 'alunos')
            ->assertSet('pautasExpandidas', []);

        $aluno = Aluno::query()->where('id_turma', $turma->id)->firstOrFail();

        $workspace
            ->call('alternarAluno', $turma->id, $aluno->id)
            ->assertSet('alunosExpandidos', [$turma->id . ':' . $aluno->id])
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

        $component->assertRedirect(route('filament.admin.pages.minhas-exportacoes', [
            'download' => $exportRequest->getKey(),
        ]));

        Queue::assertPushed(ProcessExportRequestJob::class, 1);
    }

    public function test_exportar_parecer_no_acompanhamento_bloqueia_quando_falta_gestor_obrigatorio(): void
    {
        Queue::fake();

        $dados = $this->criarCenarioExportacaoParecerAcompanhamento(comCoordenacao: false);
        $component = $dados['component'];
        $linha = $dados['linha'];

        $this->assertFalse($linha['parecer_exportavel']);
        $this->assertSame('A turma não possui vínculo com Diretor(a) ou Coordenador(a).', $linha['parecer_exportavel_motivo']);

        $component->call(
            'exportarParecerTurma',
            $linha['avaliacao_id'],
            $linha['turma_id'],
            $linha['escola_id'],
            $linha['serie_id'],
            $linha['componente_id'],
            $linha['professor_id']
        )->assertNotified('A turma não possui vínculo com Diretor(a) ou Coordenador(a).');

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
     * @return array{component: \Livewire\Features\SupportTesting\Testable, linha: array<string, mixed>}
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

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Exportação ' . $sufixo, 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Período Exportação ' . $sufixo, 'status' => true]);
        $serie = $this->criarSerie('SER-EXP-' . $sufixo, 'Infantil 3 ' . $sufixo);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-EXP-' . $sufixo,
            'nome' => 'Escuta, fala, pensamento e imaginação ' . $sufixo,
        ]);
        $escola = $this->criarEscola('Escola Exportação Parecer ' . $sufixo);
        $user->escolas()->attach($escola->id);

        $turma = $this->criarTurma($escola, $serie, 'A', 'manha');
        $aluno = $this->criarAluno($turma, 'Aluno Exportação Parecer ' . $sufixo, 'CGM-EXP-' . $sufixo);

        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-EXP-' . $sufixo,
            'nome' => 'Professor Exportação Parecer ' . $sufixo,
            'email' => 'prof.exportacao.parecer.' . strtolower($sufixo) . '@edu.umuarama.pr.gov.br',
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
        $pauta = $this->criarPauta($tipo, $serie, $componente, 'Pauta exportação parecer ' . $sufixo);
        $pauta->alternativas()->attach([$alternativa->id]);

        $avaliacao = $this->criarAvaliacao('Avaliação Exportação Parecer ' . $sufixo, $tipo, $periodo);
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
        $funcaoDiretor = FuncaoAdministrativa::query()->create([
            'nome' => 'Direção Escolar Parecer ' . $turma->id,
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'tem_relacao_turma' => false,
            'direcao_escolar' => true,
        ]);
        $funcaoCoordenador = FuncaoAdministrativa::query()->create([
            'nome' => 'Coordenação Pedagógica Parecer ' . $turma->id,
            'categoria' => FuncaoAdministrativa::CATEGORIA_PEDAGOGICO,
            'ativo' => true,
            'tem_relacao_turma' => true,
            'coordenacao_pedagogica' => true,
        ]);

        if ($comDiretor) {
            app(ServidorService::class)->criarServidorComFuncoes([
                'id_escola' => $escola->id,
                'nome' => 'Diretora Parecer',
                'matricula' => 'DIR-PARECER',
                'status' => 'ativo',
            ], [[
                'funcao_administrativa_id' => $funcaoDiretor->id,
                'portaria' => '111/2026',
            ]]);
        }

        if ($comCoordenacao) {
            app(ServidorService::class)->criarServidorComFuncoes([
                'id_escola' => $escola->id,
                'nome' => 'Coordenadora Parecer',
                'matricula' => 'COORD-PARECER',
                'status' => 'ativo',
            ], [[
                'funcao_administrativa_id' => $funcaoCoordenador->id,
                'portaria' => '222/2026',
                'turma_ids' => [$turma->id],
            ]]);
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

    private function criarTurma(Escola $escola, Serie $serie, string $nome, string $turno): Turma
    {
        return Turma::query()->create([
            'codigo' => 'TUR' . strtoupper(substr(md5($nome . $turno . microtime()), 0, 8)),
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
    ): Aluno
    {
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
        AvaliacaoResposta::query()->create([
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
