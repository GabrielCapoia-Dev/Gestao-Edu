<?php

namespace Tests\Feature\Dashboard;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Livewire\Home\AgendaProximosDias;
use App\Models\Enums\DashboardPrioridade;
use App\Models\User;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Services\Dashboard\Calendar\Sources\PedidoManutencaoCalendarEventSource;
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

        $component = new AgendaProximosDias;
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
                'hasOverflow' => false,
                'expanded' => false,
            ]],
            'sourceErrors' => [],
            'truncated' => false,
            'periodOptions' => [5, 10, 15, 20, 25, 30],
            'tabsAgenda' => [],
            'erro' => null,
        ])->render();

        $this->assertStringContainsString('home-agenda__day is-weekend', $html);
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringContainsString('x-bind:aria-expanded="aberto"', $html);
        $this->assertStringContainsString('home-agenda__event-detail-transition', $html);
        $this->assertStringContainsString('home-agenda__event-detail', $html);
        $this->assertStringNotContainsString('home-agenda__filters', $html);
        $this->assertStringNotContainsString('agenda-event-detail', $html);
    }

    public function test_agenda_exibe_contadores_nas_abas_e_expande_a_altura_do_dia_com_scroll_interno(): void
    {
        $day = CarbonImmutable::parse('2026-07-27');
        $events = [
            $this->evento('um'),
            $this->evento('dois'),
            $this->evento('tres'),
            $this->evento('quatro'),
        ];
        $html = view('livewire.home.agenda-proximos-dias', [
            'days' => [[
                'date' => $day,
                'events' => $events,
                'hasOverflow' => true,
                'expanded' => false,
            ]],
            'sourceErrors' => [],
            'truncated' => false,
            'periodOptions' => [5],
            'tabsAgenda' => [
                ['key' => 'pessoal', 'label' => 'Para mim', 'count' => 4],
                ['key' => 'rede', 'label' => 'Para a rede', 'count' => null],
                ['key' => 'veiculos', 'label' => 'Veículos', 'count' => 2],
                ['key' => 'transporte', 'label' => 'Transporte', 'count' => 0],
                ['key' => 'manutencao', 'label' => 'Manutenção', 'count' => 2],
                ['key' => 'pedagogico', 'label' => 'Pedagógico', 'count' => 1],
            ],
            'escopoAgenda' => 'pessoal',
            'erro' => null,
        ])->render();
        $css = file_get_contents(public_path('css/geral.css'));

        $this->assertStringContainsString('home-agenda__scope-count', $html);
        $this->assertStringContainsString('Manutenção', $html);
        $this->assertStringContainsString('Pedagógico', $html);
        $this->assertStringContainsString('Veículos', $html);
        $this->assertStringContainsString('Para a rede', $html);
        $this->assertStringContainsString('Contagem carregada ao selecionar', $html);
        $this->assertStringNotContainsString('<span>Transporte</span>', $html);
        $this->assertStringContainsString('Mostrar mais', $html);
        $this->assertStringContainsString('overflow-y: auto', $css);
        $this->assertStringContainsString('grid-auto-rows: max-content', $css);
        $this->assertStringContainsString('.home-agenda__day.is-expanded', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $css);
        $this->assertStringContainsString('.home-agenda__scope button:last-child:nth-child(odd)', $css);
    }

    public function test_dashboard_nao_renderiza_mais_a_grade_de_acesso_rapido(): void
    {
        $blade = file_get_contents(resource_path('views/filament/pages/dashboard.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('<livewire:home.avisos-banner', $blade);
        $this->assertStringContainsString('<livewire:home.agenda-proximos-dias', $blade);
        $this->assertStringContainsString('<livewire:home.evento-calendario-modal :mostrar-gatilho="false" />', $blade);
        $agendaBlade = file_get_contents(resource_path('views/livewire/home/agenda-proximos-dias.blade.php'));
        $this->assertIsString($agendaBlade);
        $this->assertStringNotContainsString('<livewire:home.evento-calendario-modal', $agendaBlade);
        $this->assertStringContainsString('$dispatch(\'abrir-evento-calendario\')', $agendaBlade);
        $this->assertStringNotContainsString('<livewire:home.reservas-veiculos-resumo', $blade);
        $dashboardPage = file_get_contents(app_path('Filament/Admin/Pages/Dashboard.php'));
        $this->assertStringContainsString("'actions' => \$this->getCachedHeaderActions()", $dashboardPage);
        $this->assertStringContainsString("->label('Gerenciar avisos')", $dashboardPage);
        $this->assertStringContainsString("->label('Gerenciar agenda')", $dashboardPage);
        $this->assertStringContainsString('ListaPermissoes::CriarAvisos->label()', $dashboardPage);
        $this->assertStringContainsString('ListaPermissoes::CriarEventos->label()', $dashboardPage);
        $this->assertStringContainsString("->label('Visualizar calendário')", $dashboardPage);
        $this->assertStringContainsString("->label('Gerenciar reservas')", $dashboardPage);
        $this->assertStringContainsString("->label('Reservar veículo')", $dashboardPage);
        $this->assertStringNotContainsString('Gerenciar avisos', file_get_contents(resource_path('views/livewire/home/avisos-banner.blade.php')));
        $this->assertStringNotContainsString('Gerenciar agenda', file_get_contents(resource_path('views/livewire/home/agenda-proximos-dias.blade.php')));
        $this->assertStringNotContainsString('Visualizar calendário', file_get_contents(resource_path('views/livewire/home/agenda-proximos-dias.blade.php')));
        $this->assertStringNotContainsString('Acesso rápido', $blade);
        $this->assertStringNotContainsString('quickLinks', $blade);
        $this->assertStringNotContainsString('nav-card', file_get_contents(public_path('css/geral.css')));
    }

    public function test_dashboard_monta_avisos_e_agenda_somente_com_permissao_de_listagem(): void
    {
        $blade = file_get_contents(resource_path('views/filament/pages/dashboard.blade.php'));

        $this->assertStringContainsString("@can('viewAny', \\App\\Models\\Aviso::class)", $blade);
        $this->assertStringContainsString("@can('viewAny', \\App\\Models\\EventoCalendario::class)", $blade);
        $this->assertStringContainsString('<livewire:home.avisos-banner lazy />', $blade);
        $this->assertStringContainsString('<livewire:home.agenda-proximos-dias lazy />', $blade);
    }

    public function test_agenda_da_rede_pula_consulta_de_manutencao_que_nao_exibe(): void
    {
        config()->set('dashboard.calendar.sources.pedidos_manutencao', true);

        $source = app(PedidoManutencaoCalendarEventSource::class);

        $this->assertFalse($source->supports($this->contexto(ignorarPedidosManutencao: true)));
        $agenda = file_get_contents(app_path('Livewire/Home/AgendaProximosDias.php'));
        $this->assertStringContainsString('ignorarPedidosManutencao: $escopo === \'rede\'', $agenda);
    }

    public function test_detalhe_de_evento_exibe_estado_neutro_sem_coordenadas(): void
    {
        $view = file_get_contents(resource_path('views/livewire/home/evento-calendario-detalhes-modal.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString("@if (\$resumo['latitude'] !== null && \$resumo['longitude'] !== null)", $view);
        $this->assertStringContainsString('Localização não definida.', $view);
        $this->assertStringContainsString("eventoDetailMap({ latitude:", $view);
        $this->assertStringContainsString('irParaPaginaParticipantes', $view);
        $this->assertStringContainsString('irParaPaginaEscolas', $view);
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

    public function test_resumo_exclui_eventos_ja_encerrados_mas_mantem_eventos_em_andamento_e_futuros(): void
    {
        config()->set('dashboard.calendar.timezone', 'America/Sao_Paulo');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-21 10:00:00', 'America/Sao_Paulo'));
        $context = $this->contexto(somenteNaoEncerrados: true);
        $source = new CalendarSourceStub('fonte', [
            $this->evento(
                'encerrado',
                inicio: CarbonImmutable::parse('2026-07-21 08:00:00', 'America/Sao_Paulo'),
                fim: CarbonImmutable::parse('2026-07-21 09:00:00', 'America/Sao_Paulo'),
            ),
            $this->evento(
                'em-andamento',
                inicio: CarbonImmutable::parse('2026-07-21 09:00:00', 'America/Sao_Paulo'),
                fim: CarbonImmutable::parse('2026-07-21 11:00:00', 'America/Sao_Paulo'),
            ),
            $this->evento(
                'futuro',
                inicio: CarbonImmutable::parse('2026-07-22 08:00:00', 'America/Sao_Paulo'),
                fim: CarbonImmutable::parse('2026-07-22 09:00:00', 'America/Sao_Paulo'),
            ),
        ]);

        $result = (new CalendarEventAggregator([$source]))->aggregate($context);

        $this->assertSame(['em-andamento', 'futuro'], array_map(
            static fn (CalendarEventData $event): string => $event->id,
            $result->events,
        ));
    }

    public function test_calendario_completo_renderiza_navegacao_e_exportacao_do_periodo_ativo(): void
    {
        $html = view('livewire.home.calendario-completo', [
            'days' => [[
                'date' => CarbonImmutable::parse('2026-07-01'),
                'events' => [$this->evento('historico')],
            ]],
            'offsetInicial' => 2,
            'tituloPeriodo' => 'Julho de 2026',
            'sourceErrors' => [],
            'truncated' => false,
            'visualizacao' => 'mes',
            'referencia' => '2026-07-01',
            'erro' => null,
        ])->render();

        $this->assertStringContainsString('Julho de 2026', $html);
        $this->assertSame(3, substr_count($html, 'wire:click="definirVisualizacao'));
        $this->assertSame(2, substr_count($html, route('dashboard.calendar.export')));
        $this->assertStringContainsString('Exportar calendÃ¡rio', $html);
        $this->assertStringContainsString('Planilha', $html);
        $this->assertStringContainsString('XLSX', $html);
        $this->assertStringContainsString('PDF', $html);
        $this->assertSame(2, substr_count($html, 'full-calendar__day--empty'));
        $this->assertStringContainsString('Evento historico', $html);
    }

    public function test_calendario_completo_renderiza_visao_anual_compacta_e_permite_abrir_o_mes(): void
    {
        $html = view('livewire.home.calendario-completo', [
            'days' => [],
            'months' => [[
                'date' => CarbonImmutable::parse('2026-01-01'),
                'offset' => 3,
                'days' => [[
                    'date' => CarbonImmutable::parse('2026-01-01'),
                    'events' => [$this->evento('anual')],
                ]],
            ]],
            'offsetInicial' => 0,
            'tituloPeriodo' => '2026',
            'sourceErrors' => [],
            'truncated' => false,
            'visualizacao' => 'ano',
            'referencia' => '2026-01-01',
            'erro' => null,
        ])->render();

        $this->assertStringContainsString('full-calendar__year-grid', $html);
        $this->assertStringContainsString('Janeiro', $html);
        $this->assertStringContainsString('wire:click="abrirMes', $html);
        $this->assertStringContainsString('1 evento(s)', $html);
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

    public function test_evento_so_e_classificado_como_transporte_quando_solicita_transporte(): void
    {
        $this->assertFalse($this->evento('sem-transporte')->precisaTransporte());
        $this->assertTrue($this->evento('com-transporte', transporteEstimado: 0)->precisaTransporte());
    }

    private function contexto(
        ?CarbonImmutable $inicio = null,
        ?CarbonImmutable $fim = null,
        array $categorias = [],
        array $status = [],
        array $prioridades = [],
        ?string $assunto = null,
        bool $somenteNaoEncerrados = false,
        bool $ignorarPedidosManutencao = false,
    ): CalendarQueryContext {
        $inicio ??= CarbonImmutable::parse('2026-07-20')->startOfDay();
        $fim ??= $inicio->addDays(6)->endOfDay();
        $user = (new User)->forceFill(['id' => 9001]);
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
            somenteNaoEncerrados: $somenteNaoEncerrados,
            ignorarPedidosManutencao: $ignorarPedidosManutencao,
        );
    }

    private function evento(
        string $id,
        string $categoria = 'avaliacao',
        string $status = 'em_andamento',
        DashboardPrioridade $prioridade = DashboardPrioridade::Alta,
        ?string $assunto = 'Preenchimento',
        ?int $transporteEstimado = null,
        ?CarbonImmutable $inicio = null,
        ?CarbonImmutable $fim = null,
    ): CalendarEventData {
        $inicio ??= CarbonImmutable::parse('2026-07-21 08:00:00');
        $fim ??= $inicio->addHour();

        return new CalendarEventData(
            id: $id,
            source: 'fonte',
            reference: $id,
            titulo: 'Evento '.$id,
            resumo: null,
            inicio: $inicio,
            fim: $fim,
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
            transporteEstimado: $transporteEstimado,
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
