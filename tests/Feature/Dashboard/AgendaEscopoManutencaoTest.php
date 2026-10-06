<?php

namespace Tests\Feature\Dashboard;

use App\Contracts\Dashboard\CalendarEventSource;
use App\Livewire\Home\AgendaProximosDias;
use App\Livewire\Home\CalendarioCompleto;
use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\ListaPermissoes;
use App\Models\Permission;
use App\Models\User;
use App\Services\Dashboard\Calendar\CalendarEventAggregator;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\PedidoService;
use App\Services\ProfilePreviewService;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarEventDetailData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use App\Support\Dashboard\DashboardUserContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaEscopoManutencaoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    /** @var list<CalendarEventData> */
    private array $eventos;

    private AgendaEscopoManutencaoSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 09:00:00'));
        config()->set('permission.cache.store', 'array');

        $this->usuario = User::factory()->create();
        Permission::findOrCreate(ListaPermissoes::VisualizarAgendaDeTodaARede->label(), 'web');
        $this->usuario->givePermissionTo(ListaPermissoes::VisualizarAgendaDeTodaARede->label());
        auth()->login($this->usuario);

        $inicio = CarbonImmutable::today()->setTime(10, 0);
        $this->eventos = [
            $this->evento('pedido-manutencao', 'manutencao', $inicio),
            $this->evento('evento-geral', 'evento', $inicio->addHour()),
            $this->evento('reserva-veiculo', 'veiculos', $inicio->addHours(2)),
            $this->evento('evento-pedagogico', 'pedagogico', $inicio->addHours(3)),
        ];

        $this->source = new AgendaEscopoManutencaoSource($this->eventos);
        app()->instance(CalendarEventAggregator::class, new CalendarEventAggregator([$this->source]));
        app()->instance(
            DashboardUserContextFactory::class,
            \Mockery::mock(DashboardUserContextFactory::class)
                ->shouldReceive('make')
                ->andReturn($this->userContext())
                ->getMock(),
        );
        app()->instance(
            PedidoService::class,
            \Mockery::mock(PedidoService::class)
                ->shouldReceive('ehMembroDaManutencao')
                ->andReturn(false)
                ->getMock(),
        );
        app()->instance(
            ProfilePreviewService::class,
            \Mockery::mock(ProfilePreviewService::class)
                ->shouldReceive('effectiveUser')
                ->andReturn($this->usuario)
                ->getMock(),
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_agenda_para_mim_omite_manutencao_sem_remover_a_aba_de_manutencao(): void
    {
        $component = new AgendaProximosDias;
        $component->mount();
        $component->escopoAgenda = 'pessoal';

        $data = $component->render()->getData();
        $pessoal = collect($data['days'])->flatMap(
            static fn (array $day): array => $day['events'],
        );

        $this->assertNotContains('manutencao', $pessoal->pluck('categoria')->all());
        $this->assertContains('evento-geral', $pessoal->pluck('id')->all());
        $this->assertContains('reserva-veiculo', $pessoal->pluck('id')->all());
        $this->assertContains('evento-pedagogico', $pessoal->pluck('id')->all());
        $this->assertSame(1, $this->source->calls);
        $this->assertSame(
            ['pessoal', 'rede', 'transporte', 'manutencao', 'pedagogico'],
            collect($data['tabsAgenda'])->pluck('key')->all(),
        );

        $component->escopoAgenda = 'manutencao';
        $manutencao = collect($component->render()->getData()['days'])->flatMap(
            static fn (array $day): array => $day['events'],
        );

        $this->assertSame(['manutencao'], $manutencao->pluck('categoria')->unique()->all());
        $this->assertSame(2, $this->source->calls);
    }

    public function test_calendario_completo_para_mim_omite_manutencao_e_a_mantem_em_sua_aba(): void
    {
        $component = new CalendarioCompleto;
        $component->mount();
        $component->escopoAgenda = 'pessoal';

        $pessoal = collect($component->render()->getData()['days'])->flatMap(
            static fn (array $day): array => $day['events'],
        );

        $this->assertNotContains('manutencao', $pessoal->pluck('categoria')->all());
        $this->assertContains('evento-geral', $pessoal->pluck('id')->all());
        $this->assertContains('reserva-veiculo', $pessoal->pluck('id')->all());
        $this->assertContains('evento-pedagogico', $pessoal->pluck('id')->all());

        $component->escopoAgenda = 'manutencao';
        $manutencao = collect($component->render()->getData()['days'])->flatMap(
            static fn (array $day): array => $day['events'],
        );

        $this->assertSame(['manutencao'], $manutencao->pluck('categoria')->unique()->all());
    }

    private function userContext(): DashboardUserContext
    {
        return new DashboardUserContext(
            userId: (int) $this->usuario->getKey(),
            escopoGlobal: true,
            roleIds: [],
            permissionIds: [],
            funcaoAdministrativaIds: [],
            escolaIds: [],
            setorIds: [],
            setorVisivelIds: [],
        );
    }

    private function evento(string $id, string $categoria, CarbonImmutable $inicio): CalendarEventData
    {
        return new CalendarEventData(
            id: $id,
            source: 'test',
            reference: $id,
            titulo: $id,
            resumo: null,
            inicio: $inicio,
            fim: $inicio->addHour(),
            diaInteiro: false,
            categoria: $categoria,
            categoriaLabel: $categoria,
            assunto: null,
            status: null,
            statusLabel: null,
            prioridade: DashboardPrioridade::Normal,
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

final class AgendaEscopoManutencaoSource implements CalendarEventSource
{
    public int $calls = 0;

    /** @param list<CalendarEventData> $eventos */
    public function __construct(private readonly array $eventos) {}

    public function key(): string
    {
        return 'teste';
    }

    public function supports(CalendarQueryContext $context): bool
    {
        return true;
    }

    public function events(CalendarQueryContext $context): iterable
    {
        $this->calls++;

        return $this->eventos;
    }

    public function detail(CalendarQueryContext $context, string $reference): ?CalendarEventDetailData
    {
        return null;
    }
}
