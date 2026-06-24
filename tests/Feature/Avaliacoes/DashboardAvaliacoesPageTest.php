<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\Relatorios\DashboardAvaliacoes;
use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoResposta;
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

class DashboardAvaliacoesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_carrega_indicadores_de_pendencia_apos_selecionar_avaliacao(): void
    {
        Permission::findOrCreate('Listar Avaliações');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Listar Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer Dashboard', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Dashboard', 'status' => true]);
        $serie = $this->criarSerie('SER-DASH', '1o Ano');
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-DASH',
            'nome' => 'Lingua Portuguesa',
        ]);

        $escolaManha = $this->criarEscola('Escola Manha');
        $escolaTarde = $this->criarEscola('Escola Tarde');
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

        $cards = $component->instance()->cards;

        $this->assertSame(10, $cards['preenchimentos_esperados']);
        $this->assertSame(5, $cards['preenchimentos_respondidos']);
        $this->assertSame(5, $cards['preenchimentos_pendentes']);
        $this->assertEquals(50.0, $cards['percentual_alunos_sem_resposta_pautas']);
        $this->assertEquals(0.0, $cards['percentual_turmas_preenchidas']);
        $this->assertEquals(0.0, $cards['percentual_escolas_preenchidas']);
        $this->assertEquals(83.3, $cards['percentual_turno_manha']);
        $this->assertEquals(0.0, $cards['percentual_turno_tarde']);
        $this->assertSame(1, $cards['turno_manha_alunos_pendentes']);
        $this->assertSame(3, $cards['turno_manha_alunos_total']);
        $this->assertSame(2, $cards['turno_tarde_alunos_pendentes']);
        $this->assertSame(2, $cards['turno_tarde_alunos_total']);

        $alternativas = collect($component->instance()->distribuicaoAlternativas['itens'])->keyBy('nome');

        $this->assertEquals(60.0, $alternativas->get('Sim')['percentual']);
        $this->assertEquals(40.0, $alternativas->get('Nao')['percentual']);
        $this->assertSame(5, $component->instance()->distribuicaoAlternativas['total_alunos']);

        $vinculos = collect($component->instance()->vinculosAvaliados)->keyBy('tipo_vinculo');

        $this->assertSame(4, $vinculos->get(Aluno::TIPO_VINCULO_PRINCIPAL)['alunos_total']);
        $this->assertSame(8, $vinculos->get(Aluno::TIPO_VINCULO_PRINCIPAL)['preenchimentos_esperados']);
        $this->assertSame(5, $vinculos->get(Aluno::TIPO_VINCULO_PRINCIPAL)['preenchimentos_respondidos']);
        $this->assertSame(3, $vinculos->get(Aluno::TIPO_VINCULO_PRINCIPAL)['preenchimentos_pendentes']);
        $this->assertEquals(62.5, $vinculos->get(Aluno::TIPO_VINCULO_PRINCIPAL)['percentual_preenchimento']);
        $this->assertSame(1, $vinculos->get(Aluno::TIPO_VINCULO_CONTRA_TURNO)['alunos_total']);
        $this->assertSame(2, $vinculos->get(Aluno::TIPO_VINCULO_CONTRA_TURNO)['preenchimentos_esperados']);
        $this->assertSame(0, $vinculos->get(Aluno::TIPO_VINCULO_CONTRA_TURNO)['preenchimentos_respondidos']);
        $this->assertSame(2, $vinculos->get(Aluno::TIPO_VINCULO_CONTRA_TURNO)['preenchimentos_pendentes']);

        $graficoEscolas = collect($component->instance()->graficoAlunosSemRespostaPorEscola)->keyBy('nome');

        $this->assertEquals(16.7, $graficoEscolas->get('Escola Manha')['percentual']);
        $this->assertEquals(100.0, $graficoEscolas->get('Escola Tarde')['percentual']);
    }

    public function test_listagem_de_turmas_avaliadas_tem_paginacao_configuravel(): void
    {
        Permission::findOrCreate('Listar Avaliacoes');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Listar Avaliacoes');

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

        $this->assertSame(7, $component->instance()->turmasAvaliadasTotal);
        $this->assertCount(7, $component->instance()->turmasAvaliadas);

        $component->set('turmasAvaliadasPorPagina', 5);

        $this->assertSame(5, $component->instance()->turmasAvaliadasPorPagina);
        $this->assertSame(1, $component->instance()->turmasAvaliadasPagina);
        $this->assertCount(5, $component->instance()->turmasAvaliadas);

        $component->call('proximaPaginaTurmasAvaliadas');

        $this->assertSame(2, $component->instance()->turmasAvaliadasPagina);
        $this->assertCount(2, $component->instance()->turmasAvaliadas);

        $component->set('turmasAvaliadasPorPagina', 25);

        $this->assertSame(25, $component->instance()->turmasAvaliadasPorPagina);
        $this->assertSame(1, $component->instance()->turmasAvaliadasPagina);
        $this->assertCount(7, $component->instance()->turmasAvaliadas);
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
        Alternativa $alternativa
    ): void {
        AvaliacaoResposta::query()->create([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $aluno->id,
            'professor_id' => null,
            'alternativa_id' => $alternativa->id,
            'respondido_em' => now(),
        ]);
    }
}
