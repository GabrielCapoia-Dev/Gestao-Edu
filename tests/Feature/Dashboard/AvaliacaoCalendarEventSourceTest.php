<?php

namespace Tests\Feature\Dashboard;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Permission;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Setor;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\Avaliacoes\AvaliacaoDashboardProgressService;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Services\Dashboard\Calendar\Sources\AvaliacaoCalendarEventSource;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AvaliacaoCalendarEventSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_fonte_de_avaliacoes_respeita_escola_periodo_status_e_sinaliza_progresso_pendente(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        CarbonImmutable::setTestNow($agora);
        $setor = $this->criarSetor('Setor das avaliações da agenda');
        $escolaA = $this->criarEscola('Avaliação Agenda A', $setor);
        $escolaB = $this->criarEscola('Avaliação Agenda B', $setor);
        $userA = User::factory()->create(['id_escola' => $escolaA->id]);
        $this->darPermissao($userA, 'Acompanhar Avaliações');
        [$tipo, $periodo, $serie] = $this->estruturaAvaliacao();
        $turmaA = $this->criarTurma($escolaA, $serie, 'Turma Agenda A');
        $turmaB = $this->criarTurma($escolaB, $serie, 'Turma Agenda B');
        $avaliacaoA = $this->criarAvaliacao('Avaliação permitida', $tipo, $periodo, $agora);
        $avaliacaoB = $this->criarAvaliacao('Avaliação de outra escola', $tipo, $periodo, $agora);
        $inativa = $this->criarAvaliacao('Avaliação inativa', $tipo, $periodo, $agora, Avaliacao::STATUS_INATIVA);
        $foraDoPeriodo = $this->criarAvaliacao('Avaliação futura', $tipo, $periodo, $agora->addDays(20));
        $avaliacaoA->turmas()->attach($turmaA->id);
        $avaliacaoB->turmas()->attach($turmaB->id);
        $inativa->turmas()->attach($turmaA->id);
        $foraDoPeriodo->turmas()->attach($turmaA->id);
        $context = $this->contexto($userA, $agora, $agora->addDays(6)->endOfDay());
        $source = app(AvaliacaoCalendarEventSource::class);

        $result = (new CalendarEventAggregator([$source]))->aggregate($context);
        $events = collect($result->events);

        $this->assertTrue($source->supports($context));
        $this->assertSame([], $result->errors);
        $this->assertSame([$avaliacaoA->id], $events->map(
            static fn (CalendarEventData $event): int => (int) $event->reference,
        )->all());
        $this->assertNull($events->first()->progresso);
        $this->assertStringContainsString('Progresso em atualização', (string) $events->first()->resumo);
        $this->assertSame($escolaA->id, $events->first()->escolaId);
        $this->assertNull($source->detail($context, (string) $avaliacaoB->id));

        $detail = $source->detail($context, (string) $avaliacaoA->id);

        $this->assertSame('Progresso em atualização', $detail?->metadata['Progresso']);
    }

    public function test_progresso_do_professor_usa_pares_turma_componente_e_inclui_fatos_pendentes_sem_professor(): void
    {
        $setor = $this->criarSetor('Setor do progresso por professor');
        $escola = $this->criarEscola('Escola do progresso por professor', $setor);
        [$tipo, $periodo, $serie] = $this->estruturaAvaliacao();
        $turma = $this->criarTurma($escola, $serie, 'Turma do progresso');
        $componentePermitido = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-PERMITIDO',
            'nome' => 'Componente permitido',
        ]);
        $componenteFora = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-FORA',
            'nome' => 'Componente fora do vínculo',
        ]);
        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-AGENDA-001',
            'turno' => 'manha',
            'nome' => 'Professor da agenda',
            'email' => 'professor.agenda@teste.local',
            'ativo' => true,
        ]);
        TurmaComponenteProfessor::query()->create([
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componentePermitido->id,
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);
        $avaliacao = $this->criarAvaliacao(
            'Avaliação consolidada por pares',
            $tipo,
            $periodo,
            CarbonImmutable::parse('2026-07-20'),
        );
        $pautaRespondida = $this->criarPauta($tipo, $serie, $componentePermitido, 'Pauta respondida');
        $pautaPendente = $this->criarPauta($tipo, $serie, $componentePermitido, 'Pauta pendente');
        $pautaFora = $this->criarPauta($tipo, $serie, $componenteFora, 'Pauta de outro componente');
        $alunoRespondido = $this->criarAluno($turma, 'AGENDA-ALUNO-1');
        $alunoPendente = $this->criarAluno($turma, 'AGENDA-ALUNO-2');
        $alunoFora = $this->criarAluno($turma, 'AGENDA-ALUNO-3');

        DB::table('avaliacao_dashboard_consolidacoes')->insert([
            'avaliacao_id' => $avaliacao->id,
            'status' => 'consolidado',
            'consolidada_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->inserirFato($avaliacao, $alunoRespondido, $turma, $escola, $serie, $pautaRespondida, $componentePermitido, $professor, true);
        $this->inserirFato($avaliacao, $alunoPendente, $turma, $escola, $serie, $pautaPendente, $componentePermitido, null, false);
        $this->inserirFato($avaliacao, $alunoFora, $turma, $escola, $serie, $pautaFora, $componenteFora, null, false);

        $progress = app(AvaliacaoDashboardProgressService::class)->batch(
            [$avaliacao->id],
            [$escola->id],
            [$professor->id],
        )[$avaliacao->id];

        $this->assertFalse($progress->emAtualizacao());
        $this->assertSame(50.0, $progress->percentual);
    }

    private function contexto(User $user, CarbonImmutable $inicio, CarbonImmutable $fim): CalendarQueryContext
    {
        return new CalendarQueryContext(
            user: $user,
            userContext: app(DashboardUserContextFactory::class)->make($user),
            inicio: $inicio->startOfDay(),
            fim: $fim->endOfDay(),
        );
    }

    private function darPermissao(User $user, string $nome): void
    {
        $user->givePermissionTo(Permission::findOrCreate($nome, 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);
    }

    private function criarEscola(string $nome, Setor $setor): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'email' => str($nome)->slug('.').'@teste.local',
            'setor_id' => $setor->id,
            'ativo' => true,
        ]);
    }

    /** @return array{0: TipoAvaliacao, 1: PeriodoAvaliacao, 2: Serie} */
    private function estruturaAvaliacao(): array
    {
        return [
            TipoAvaliacao::query()->create(['nome' => 'Tipo da agenda '.uniqid(), 'status' => true]),
            PeriodoAvaliacao::query()->create(['nome' => 'Período da agenda '.uniqid(), 'status' => true]),
            Serie::query()->create(['codigo' => 'SER-'.uniqid(), 'nome' => 'Série da agenda '.uniqid()]),
        ];
    }

    private function criarTurma(Escola $escola, Serie $serie, string $nome): Turma
    {
        return Turma::query()->create([
            'codigo' => 'TUR-'.strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarAvaliacao(
        string $nome,
        TipoAvaliacao $tipo,
        PeriodoAvaliacao $periodo,
        CarbonImmutable $inicio,
        string $status = Avaliacao::STATUS_ATIVA,
    ): Avaliacao {
        return Avaliacao::query()->create([
            'nome' => $nome,
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => $inicio->toDateString(),
            'data_fim' => $inicio->addDays(5)->toDateString(),
            'data_inicio_preenchimento' => $inicio->toDateString(),
            'data_fim_preenchimento' => $inicio->addDays(5)->toDateString(),
            'status' => $status,
        ]);
    }

    private function criarPauta(
        TipoAvaliacao $tipo,
        Serie $serie,
        ComponenteCurricular $componente,
        string $texto,
    ): Pauta {
        return Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => $texto,
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
    }

    private function criarAluno(Turma $turma, string $cgm): Aluno
    {
        return Aluno::query()->create([
            'nome' => 'Aluno '.$cgm,
            'cgm' => $cgm,
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);
    }

    private function inserirFato(
        Avaliacao $avaliacao,
        Aluno $aluno,
        Turma $turma,
        Escola $escola,
        Serie $serie,
        Pauta $pauta,
        ComponenteCurricular $componente,
        ?Professor $professor,
        bool $respondida,
    ): void {
        DB::table('avaliacao_dashboard_fatos')->insert([
            'avaliacao_id' => $avaliacao->id,
            'aluno_id' => $aluno->id,
            'turma_id' => $turma->id,
            'escola_id' => $escola->id,
            'serie_id' => $serie->id,
            'pauta_id' => $pauta->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professor?->id,
            'respondida' => $respondida,
            'observacao_pendente' => false,
            'status_resposta' => $respondida ? 'respondida' : 'pendente',
            'respondida_em' => $respondida ? now() : null,
            'origem_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
