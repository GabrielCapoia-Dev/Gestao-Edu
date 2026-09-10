<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\GestaoAvaliacoes;
use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoSnapshotEvento;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Services\Avaliacoes\AvaliacaoEstruturaService;
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

        Aluno::query()->create([
            'nome' => 'Aluno com componente sem professor',
            'cgm' => 'CGM-AVAL-1',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaA->id,
        ]);
        Aluno::query()->create([
            'nome' => 'Aluno sem vinculo de professor',
            'cgm' => 'CGM-AVAL-2',
            'data_nascimento' => '2015-01-02',
            'id_turma' => $turmaB->id,
        ]);

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
            ->set('form.data_inicio_preenchimento', now()->toDateString())
            ->set('form.data_fim_preenchimento', now()->addDays(10)->toDateString())
            ->set('form.status', Avaliacao::STATUS_ATIVA)
            ->set('form.series_ids', [$serie1->id])
            ->assertSet('form.series_ids', [(string) $serie1->id])
            ->set('form.componentes_ids', [$componenteMat->id])
            ->assertSet('form.componentes_ids', [(string) $componenteMat->id])
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
            ->set('form.data_inicio_preenchimento', now()->toDateString())
            ->set('form.data_fim_preenchimento', now()->addDays(10)->toDateString())
            ->set('form.status', Avaliacao::STATUS_ATIVA)
            ->set('form.series_ids', [$serie->id])
            ->set('form.componentes_ids', [$componente->id])
            ->set('form.escolas_ids', [(string) $escola->id])
            ->set("pautasOverrideHabilitado.{$pauta->id}", true)
            ->set("alternativasOverride.{$pauta->id}", [$alternativaOverride->id])
            ->call('salvarAvaliacao')
            ->assertHasNoErrors();

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

    public function test_adiciona_alternativas_por_multiplos_tipos_sem_remover_ajustes_individuais(): void
    {
        $tipoAvaliacao = TipoAvaliacao::query()->create(['nome' => 'SRM', 'status' => true]);
        $tipoParecer = TipoAvaliacao::query()->create(['nome' => 'Parecer', 'status' => true]);
        $tipoEtapa = TipoAvaliacao::query()->create(['nome' => 'SRM - 2a Etapa', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => '2o Trimestre', 'status' => true]);
        $serie = Serie::query()->create(['codigo' => 'SER-SRM', 'nome' => 'Sala de Recursos']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-SRM', 'nome' => 'SRM']);
        $serie->componentesCurriculares()->sync([$componente->id]);

        $escola = Escola::query()->create([
            'codigo' => 'ESC-SRM',
            'nome' => 'Escola SRM',
            'email' => 'escola.srm@teste.local',
            'telefone' => '(44) 99999-4444',
        ]);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-SRM',
            'nome' => 'SRM',
            'turno' => 'tarde',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $turma->componentes()->attach($componente->id, ['professor_id' => null, 'tem_professor' => false]);

        $parecerSim = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipoParecer->id,
            'nome' => 'Sim',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $parecerNao = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipoParecer->id,
            'nome' => 'Não',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $parecerInativo = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipoParecer->id,
            'nome' => 'Inativo',
            'tem_observacao' => false,
            'status' => false,
        ]);
        $naoSeAplica = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipoEtapa->id,
            'nome' => 'Não se Aplica',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipoAvaliacao->id,
            'texto' => 'Realiza atividade proposta',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);

        $pagina = app(GestaoAvaliacoes::class);
        $pagina->form['tipo_avaliacao_id'] = $tipoAvaliacao->id;
        $pagina->form['series_ids'] = [$serie->id];
        $pagina->form['componentes_ids'] = [$componente->id];
        $pagina->tiposAlternativasEmMassa = [$tipoParecer->id];
        $pagina->adicionarTiposAlternativasEmMassa();

        $this->assertTrue((bool) ($pagina->pautasOverrideHabilitado[$pauta->id] ?? false));
        $this->assertSame(
            collect([$parecerSim->id, $parecerNao->id])->sort()->values()->all(),
            collect($pagina->alternativasOverride[$pauta->id] ?? [])->sort()->values()->all(),
        );

        $this->assertNull($pagina->pautaAlternativasAberta);
        $pagina->alternarEditorAlternativasPauta($pauta->id);
        $this->assertSame($pauta->id, $pagina->pautaAlternativasAberta);
        $pagina->alternarEditorAlternativasPauta($pauta->id);
        $this->assertNull($pagina->pautaAlternativasAberta);

        $pagina->atualizarModoAlternativasPauta($pauta->id, '0');
        $this->assertFalse((bool) $pagina->pautasOverrideHabilitado[$pauta->id]);
        $this->assertNull($pagina->pautaAlternativasAberta);

        $pagina->atualizarModoAlternativasPauta($pauta->id, '1');
        $this->assertTrue((bool) $pagina->pautasOverrideHabilitado[$pauta->id]);
        $this->assertSame($pauta->id, $pagina->pautaAlternativasAberta);

        $pagina->tipoAlternativaAdicionar[$pauta->id] = $tipoEtapa->id;
        $pagina->adicionarTipoAlternativasNaPauta($pauta->id);

        $this->assertSame(
            collect([$parecerSim->id, $parecerNao->id, $naoSeAplica->id])->sort()->values()->all(),
            collect($pagina->alternativasOverride[$pauta->id] ?? [])->sort()->values()->all(),
        );

        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliacao SRM por tipos',
            'tipo_avaliacao_id' => $tipoAvaliacao->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => now()->toDateString(),
            'data_fim' => now()->addDays(7)->toDateString(),
            'data_inicio_preenchimento' => now()->toDateString(),
            'data_fim_preenchimento' => now()->addDays(10)->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->sync([$pauta->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->alternativasOverride()->attach([
            $parecerSim->id => ['pauta_id' => $pauta->id],
            $parecerNao->id => ['pauta_id' => $pauta->id],
        ]);

        AvaliacaoSnapshotEvento::query()->create([
            'idempotency_key' => 'gestao-avaliacoes-tipos-'.$avaliacao->id,
            'tipo' => AvaliacaoSnapshotEvento::TIPO_CONCLUSAO,
            'avaliacao_id' => $avaliacao->id,
            'payload_hash_agregado' => str_repeat('a', 64),
        ]);

        app(AvaliacaoEstruturaService::class)->validarAlteracao(
            $avaliacao,
            [$pauta->id],
            [$turma->id],
            [$serie->id],
            [$componente->id],
            [
                ['pauta_id' => $pauta->id, 'alternativa_id' => $parecerSim->id],
                ['pauta_id' => $pauta->id, 'alternativa_id' => $parecerNao->id],
                ['pauta_id' => $pauta->id, 'alternativa_id' => $naoSeAplica->id],
            ],
        );

        $avaliacao->alternativasOverride()->attach($naoSeAplica->id, ['pauta_id' => $pauta->id]);

        foreach ([$parecerSim, $parecerNao, $naoSeAplica] as $alternativa) {
            $this->assertDatabaseHas('avaliacao_pauta_alternativa', [
                'avaliacao_id' => $avaliacao->id,
                'pauta_id' => $pauta->id,
                'alternativa_id' => $alternativa->id,
            ]);
        }

        $this->assertDatabaseMissing('avaliacao_pauta_alternativa', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'alternativa_id' => $parecerInativo->id,
        ]);

        $this->expectExceptionMessage('Não é possível remover alternativas da avaliação');

        app(AvaliacaoEstruturaService::class)->validarAlteracao(
            $avaliacao->fresh(),
            [$pauta->id],
            [$turma->id],
            [$serie->id],
            [$componente->id],
            [
                ['pauta_id' => $pauta->id, 'alternativa_id' => $parecerSim->id],
                ['pauta_id' => $pauta->id, 'alternativa_id' => $naoSeAplica->id],
            ],
        );
    }

    public function test_botao_acompanhar_aparece_na_listagem_com_permissao_especifica(): void
    {
        Permission::findOrCreate('Listar Avaliações');
        Permission::findOrCreate('Acompanhar Avaliações');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Tipo Acompanhamento', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Acompanhamento', 'status' => true]);
        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliacao com acompanhamento',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => now()->toDateString(),
            'data_fim' => now()->addDays(7)->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);

        Livewire::actingAs($usuario)
            ->test(GestaoAvaliacoes::class)
            ->assertTableActionHidden('acompanhar', $avaliacao);

        $usuario->givePermissionTo('Acompanhar Avaliações');

        Livewire::actingAs($usuario->fresh())
            ->test(GestaoAvaliacoes::class)
            ->assertTableActionVisible('acompanhar', $avaliacao)
            ->assertTableActionHasUrl(
                'acompanhar',
                route('filament.admin.pages.dashboard-avaliacoes', ['avaliacao' => $avaliacao->id]),
                $avaliacao
            );
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
