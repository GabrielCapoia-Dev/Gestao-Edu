<?php

namespace Tests\Feature\Dashboard;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Livewire\Home\AgendaProximosDias;
use App\Models\Enums\DashboardPrioridade;
use App\Models\User;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarEventDetailData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use App\Support\Dashboard\DashboardUserContext;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class CalendarEventAggregatorTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_agenda_inicia_com_cinco_dias_e_aceita_somente_periodos_configurados(): void
    {
        config()->set('dashboard.calendar.default_days', 5);
        config()->set('dashboard.calendar.max_days', 30);
        config()->set('dashboard.calendar.period_options', [5, 10, 15, 20, 25, 30]);
        config()->set('dashboard.calendar.timezone', 'America/Sao_Paulo');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-20 10:00:00', 'America/Sao_Paulo'));

        $component = new AgendaProximosDias();
        $component->mount();

        $this->assertSame(5, $component->quantidadeDias);

        $component->updatedQuantidadeDias(17);

        $this->assertSame(5, $component->quantidadeDias);

        $component->updatedQuantidadeDias(30);

        $this->assertSame(30, $component->quantidadeDias);

        $this->contexto(
            inicio: CarbonImmutable::parse('2026-07-01')->startOfDay(),
            fim: CarbonImmutable::parse('2026-07-30')->endOfDay(),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no máximo 30 dias');

        $this->contexto(
            inicio: CarbonImmutable::parse('2026-07-01')->startOfDay(),
            fim: CarbonImmutable::parse('2026-07-31')->endOfDay(),
        );
    }

    public function test_view_da_agenda_exibe_acordeao_resumido_e_destaca_fim_de_semana_sem_filtros_ou_modal(): void
    {
        $event = $this->evento('sabado');
        $day = CarbonImmutable::parse('2026-07-25');
        $html = view('livewire.home.agenda-proximos-dias', [
            'days' => [[
                'date' => $day,
                'events' => [$event],
                'remaining' => 0,
                'expanded' => false,
            ]],
            'sourceErrors' => [],
            'truncated' => false,
            'periodOptions' => [5, 10, 15, 20, 25, 30],
            'manageUrl' => null,
            'erro' => null,
        ])->render();

        $this->assertStringContainsString('home-agenda__day is-weekend', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('home-agenda__event-detail', $html);
        $this->assertStringNotContainsString('home-agenda__filters', $html);
        $this->assertStringNotContainsString('agenda-event-detail', $html);
    }

    public function test_dashboard_nao_renderiza_mais_a_grade_de_acesso_rapido(): void
    {
        $blade = file_get_contents(resource_path('views/filament/pages/dashboard.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('<livewire:home.avisos-banner', $blade);
        $this->assertStringContainsString('<livewire:home.agenda-proximos-dias', $blade);
        $this->assertStringNotContainsString('Acesso rápido', $blade);
        $this->assertStringNotContainsString('quickLinks', $blade);
        $this->assertStringNotContainsString('nav-card', file_get_contents(public_path('css/geral.css')));
    }

    public function test_agregador_aplica_filtros_de_categoria_status_prioridade_e_assunto(): void
    {
        $inicio = CarbonImmutable::parse('2026-07-20 00:00:00');
        $context = $this->contexto(
            inicio: $inicio,
            fim: $inicio->addDays(6)->endOfDay(),
            categorias: ['avaliacao'],
            status: ['em_andamento'],
            prioridades: ['alta'],
            assunto: 'preenchimento',
        );
        $matching = $this->evento(
            id: 'match',
            categoria: 'avaliacao',
            status: 'em_andamento',
            prioridade: DashboardPrioridade::Alta,
            assunto: 'Período de preenchimento',
        );

        $source = new CalendarSourceStub('fonte', [
            $matching,
            $this->evento('outra-categoria', categoria: 'manutencao', status: 'em_andamento', prioridade: DashboardPrioridade::Alta, assunto: 'Preenchimento'),
            $this->evento('outro-status', categoria: 'avaliacao', status: 'agendado', prioridade: DashboardPrioridade::Alta, assunto: 'Preenchimento'),
            $this->evento('outra-prioridade', categoria: 'avaliacao', status: 'em_andamento', prioridade: DashboardPrioridade::Normal, assunto: 'Preenchimento'),
            $this->evento('outro-assunto', categoria: 'avaliacao', status: 'em_andamento', prioridade: DashboardPrioridade::Alta, assunto: 'Encerramento'),
        ]);

        $result = (new CalendarEventAggregator([$source]))->aggregate($context);

        $this->assertSame(['match'], array_map(
            static fn (CalendarEventData $event): string => $event->id,
            $result->events,
        ));
        $this->assertSame([], $result->errors);
        $this->assertFalse($result->truncated);
    }

    public function test_falha_de_uma_fonte_nao_descarta_eventos_das_demais(): void
    {
        $context = $this->contexto();
        $evento = $this->evento('disponivel');
        $aggregator = new CalendarEventAggregator([
            new CalendarSourceStub('disponivel', [$evento]),
            new CalendarSourceStub('indisponivel', falhar: true),
        ]);

        $result = $aggregator->aggregate($context);

        $this->assertSame(['disponivel'], array_map(
            static fn (CalendarEventData $item): string => $item->id,
            $result->events,
        ));
        $this->assertSame(
            ['indisponivel' => 'Não foi possível carregar esta origem agora.'],
            $result->errors,
        );
    }

    private function contexto(
        ?CarbonImmutable $inicio = null,
        ?CarbonImmutable $fim = null,
        array $categorias = [],
        array $status = [],
        array $prioridades = [],
        ?string $assunto = null,
    ): CalendarQueryContext {
        $inicio ??= CarbonImmutable::parse('2026-07-20')->startOfDay();
        $fim ??= $inicio->addDays(6)->endOfDay();
        $user = (new User())->forceFill(['id' => 9001]);
        $userContext = new DashboardUserContext(
            userId: 9001,
            escopoGlobal: true,
            roleIds: [],
            permissionIds: [],
            funcaoAdministrativaIds: [],
            escolaIds: [],
            setorIds: [],
            setorVisivelIds: [],
        );

        return new CalendarQueryContext(
            user: $user,
            userContext: $userContext,
            inicio: $inicio,
            fim: $fim,
            categorias: $categorias,
            status: $status,
            prioridades: $prioridades,
            assunto: $assunto,
        );
    }

    private function evento(
        string $id,
        string $categoria = 'avaliacao',
        string $status = 'em_andamento',
        DashboardPrioridade $prioridade = DashboardPrioridade::Alta,
        ?string $assunto = 'Preenchimento',
    ): CalendarEventData {
        $inicio = CarbonImmutable::parse('2026-07-21 08:00:00');

        return new CalendarEventData(
            id: $id,
            source: 'fonte',
            reference: $id,
            titulo: 'Evento '.$id,
            resumo: null,
            inicio: $inicio,
            fim: $inicio->addHour(),
            diaInteiro: false,
            categoria: $categoria,
            categoriaLabel: ucfirst($categoria),
            assunto: $assunto,
            status: $status,
            statusLabel: ucfirst($status),
            prioridade: $prioridade,
            progresso: null,
            cor: 'azul',
            escolaId: null,
            escola: null,
            setorId: null,
            setor: null,
            origem: 'Teste',
        );
    }
}

final class CalendarSourceStub implements CalendarEventSource
{
    /** @param list<CalendarEventData> $items */
    public function __construct(
        private readonly string $sourceKey,
        private readonly array $items = [],
        private readonly bool $falhar = false,
        private readonly bool $suportada = true,
    ) {}

    public function key(): string
    {
        return $this->sourceKey;
    }

    public function supports(CalendarQueryContext $context): bool
    {
        return $this->suportada;
    }

    public function events(CalendarQueryContext $context): iterable
    {
        if ($this->falhar) {
            throw new RuntimeException('Falha controlada da fonte.');
        }

        return $this->items;
    }

    public function detail(CalendarQueryContext $context, string $reference): ?CalendarEventDetailData
    {
        return null;
    }
}
