<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\ListaPermissoes;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\Role;
use App\Models\Setor;
use App\Models\User;
use App\Services\Dashboard\Calendar\Sources\ManualCalendarEventSource;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\Dashboard\PublicoAlvoService;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManualCalendarEventSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_fonte_manual_filtra_periodo_publicacao_e_publico_alvo_no_backend(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        CarbonImmutable::setTestNow($agora);
        [$usuarioA, $usuarioB] = $this->usuariosDeEscolasDiferentes();
        $publicos = app(PublicoAlvoService::class);
        $publicoA = $publicos->criar($usuarioA, ['todos_usuarios' => true]);
        $publicoB = $publicos->criar($usuarioB, ['todos_usuarios' => true]);
        $visivel = $this->criarEvento($publicoA->id, 'Evento permitido', $agora->addDay());

        $this->criarEvento($publicoA->id, 'Evento não publicado', $agora->addDays(2), ativo: false);
        $this->criarEvento($publicoA->id, 'Evento fora do período', $agora->addDays(20));
        $this->criarEvento($publicoB->id, 'Evento de outra escola', $agora->addDay());

        $source = app(ManualCalendarEventSource::class);
        $contextA = $this->contexto($usuarioA, $agora, $agora->addDays(6)->endOfDay());
        $contextB = $this->contexto($usuarioB, $agora, $agora->addDays(6)->endOfDay());
        $eventsA = collect($source->events($contextA));

        $this->assertSame([$visivel->id], $eventsA->map(
            static fn (CalendarEventData $event): int => (int) $event->reference,
        )->all());
        $this->assertSame('manual:'.$visivel->id, $eventsA->first()->id);
        $this->assertNull($eventsA->first()->status);
        $this->assertNull($eventsA->first()->progresso);
        $this->assertNull($source->detail($contextB, (string) $visivel->id));
    }

    public function test_detalhe_manual_reaplica_publicacao_e_autorizacao_ao_ser_aberto(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        CarbonImmutable::setTestNow($agora);
        [$usuarioA, $usuarioB] = $this->usuariosDeEscolasDiferentes();
        $publico = app(PublicoAlvoService::class)->criar($usuarioA, ['todos_usuarios' => true]);
        $evento = $this->criarEvento($publico->id, 'Detalhe protegido', $agora->addDay());
        $source = app(ManualCalendarEventSource::class);
        $contextA = $this->contexto($usuarioA, $agora, $agora->addDays(6)->endOfDay());
        $contextB = $this->contexto($usuarioB, $agora, $agora->addDays(6)->endOfDay());

        $this->assertSame(
            'Detalhe protegido',
            $source->detail($contextA, (string) $evento->id)?->event->titulo,
        );
        $this->assertNull($source->detail($contextB, (string) $evento->id));

        $evento->forceFill(['ativo' => false])->save();

        $this->assertNull($source->detail($contextA, (string) $evento->id));
    }

    public function test_fonte_manual_oculta_eventos_pendentes_rejeitados_e_inativos(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        CarbonImmutable::setTestNow($agora);
        [$usuario] = $this->usuariosDeEscolasDiferentes();
        $publico = app(PublicoAlvoService::class)->criar($usuario, ['todos_usuarios' => true]);
        $publicado = $this->criarEvento(
            $publico->id,
            'Evento publicado',
            $agora->addDay(),
            status: EventoCalendarioStatus::PUBLICADO,
        );
        $pendente = $this->criarEvento(
            $publico->id,
            'Evento pendente',
            $agora->addDays(2),
            status: EventoCalendarioStatus::PENDENTE_APROVACAO,
        );
        $rejeitado = $this->criarEvento(
            $publico->id,
            'Evento rejeitado',
            $agora->addDays(3),
            status: EventoCalendarioStatus::REJEITADO,
        );
        $inativo = $this->criarEvento(
            $publico->id,
            'Evento inativo',
            $agora->addDays(4),
            status: EventoCalendarioStatus::INATIVO,
        );
        $contexto = $this->contexto($usuario, $agora, $agora->addDays(6)->endOfDay());
        $source = app(ManualCalendarEventSource::class);

        $ids = collect($source->events($contexto))
            ->map(static fn (CalendarEventData $event): int => (int) str($event->reference)->before('@')->toString())
            ->all();

        $this->assertSame([$publicado->id], $ids);
        $this->assertNull($source->detail($contexto, (string) $pendente->id));
        $this->assertNull($source->detail($contexto, (string) $rejeitado->id));
        $this->assertNull($source->detail($contexto, (string) $inativo->id));
    }

    public function test_evento_manual_global_nao_ignora_escola_ou_setor_relacionado_fora_do_contexto(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        CarbonImmutable::setTestNow($agora);
        $setorPermitido = $this->criarSetor('Setor permitido da agenda manual');
        $setorBloqueado = $this->criarSetor('Setor bloqueado da agenda manual');
        $escolaPermitida = $this->criarEscola('Escola permitida da agenda manual', $setorPermitido);
        $escolaBloqueada = $this->criarEscola('Escola bloqueada da agenda manual', $setorBloqueado);
        $usuario = User::factory()->create([
            'id_escola' => $escolaPermitida->id,
            'setor_id' => $setorPermitido->id,
        ]);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        $publicoGlobal = app(PublicoAlvoService::class)->criar($admin, [
            'todos_usuarios' => true,
        ]);
        $permitido = $this->criarEvento(
            $publicoGlobal->id,
            'Evento relacionado permitido',
            $agora->addDay(),
            escola: $escolaPermitida,
            setor: $setorPermitido,
        );
        $escolaFora = $this->criarEvento(
            $publicoGlobal->id,
            'Evento de escola fora do contexto',
            $agora->addDay(),
            escola: $escolaBloqueada,
        );
        $setorFora = $this->criarEvento(
            $publicoGlobal->id,
            'Evento de setor fora do contexto',
            $agora->addDay(),
            setor: $setorBloqueado,
        );
        $contexto = $this->contexto($usuario, $agora, $agora->addDays(6)->endOfDay());
        $source = app(ManualCalendarEventSource::class);

        $this->assertSame(
            [$permitido->id],
            collect($source->events($contexto))
                ->map(static fn (CalendarEventData $event): int => (int) $event->reference)
                ->all(),
        );
        $this->assertNotNull($source->detail($contexto, (string) $permitido->id));
        $this->assertNull($source->detail($contexto, (string) $escolaFora->id));
        $this->assertNull($source->detail($contexto, (string) $setorFora->id));
    }

    public function test_evento_especifico_gera_uma_unica_ocorrencia_agregada(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        CarbonImmutable::setTestNow($agora);
        $setor = $this->criarSetor('Setor das ocorrências escolares');
        $escolaA = $this->criarEscola('Ocorrência A', $setor);
        $escolaB = $this->criarEscola('Ocorrência B', $setor);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        $admin->givePermissionTo(Permission::findOrCreate(
            ListaPermissoes::VisualizarAgendaDeTodaARede->label(),
            'web',
        ));
        $publico = app(PublicoAlvoService::class)->criar($admin, ['todos_usuarios' => true]);
        $evento = $this->criarEvento($publico->id, 'Evento com horários escolares', $agora->addDay());
        $evento->update(['enviar_todas_escolas' => false]);
        $evento->escolasAgendadas()->createMany([
            ['escola_id' => $escolaA->id, 'hora_inicio' => '08:00', 'hora_fim' => '10:00'],
            ['escola_id' => $escolaB->id, 'hora_inicio' => '10:00', 'hora_fim' => '12:00'],
        ]);

        $events = collect(app(ManualCalendarEventSource::class)->events(
            $this->contexto($admin, $agora, $agora->addDays(6)->endOfDay(), redeCompleta: true),
        ));

        $this->assertSame(1, $events->count());
        $this->assertSame('08:00', $events->sole()->inicio->format('H:i'));
        $this->assertSame('12:00', $events->sole()->fim->format('H:i'));
        $this->assertSame(2, $events->sole()->escolasCount);
        $this->assertSame('2 escolas', $events->sole()->escola);
        $this->assertNull($events->sole()->escolaId);
    }

    public function test_criador_global_nao_entra_no_modo_pessoal_apenas_por_ser_criador(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        CarbonImmutable::setTestNow($agora);
        $setor = $this->criarSetor('Setor do evento criado pelo administrador');
        $escola = $this->criarEscola('Escola do evento criado pelo administrador', $setor);
        $adminCriador = User::factory()->create();
        $adminCriador->assignRole(Role::findOrCreate('Admin', 'web'));
        $outroAdmin = User::factory()->create();
        $outroAdmin->assignRole(Role::findOrCreate('Admin', 'web'));
        $publico = app(PublicoAlvoService::class)->criar($adminCriador, [
            'todos_usuarios' => false,
            'escolas_ids' => [$escola->id],
        ]);
        $evento = $this->criarEvento($publico->id, 'Evento escolar do administrador', $agora->addDays(3));
        $evento->update([
            'criado_por_id' => $adminCriador->id,
            'enviar_todas_escolas' => false,
        ]);
        $evento->escolasAgendadas()->create([
            'escola_id' => $escola->id,
            'hora_inicio' => '13:30',
            'hora_fim' => '17:30',
            'precisa_transporte' => false,
        ]);
        $source = app(ManualCalendarEventSource::class);

        $doCriador = collect($source->events(
            $this->contexto($adminCriador, $agora, $agora->addDays(4)->endOfDay()),
        ));
        $doOutroAdmin = collect($source->events(
            $this->contexto($outroAdmin, $agora, $agora->addDays(4)->endOfDay()),
        ));

        $this->assertNotContains($evento->id, $doCriador->map(
            static fn (CalendarEventData $item): int => (int) str($item->reference)->before('@')->toString(),
        ));
        $this->assertNotContains($evento->id, $doOutroAdmin->map(
            static fn (CalendarEventData $item): int => (int) str($item->reference)->before('@')->toString(),
        ));
    }

    public function test_permissao_da_agenda_da_rede_libera_eventos_fora_do_escopo_global_do_usuario(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        [$usuarioA, $usuarioRede] = $this->usuariosDeEscolasDiferentes();
        $usuarioRede->givePermissionTo(Permission::findOrCreate(
            ListaPermissoes::VisualizarAgendaDeTodaARede->label(),
            'web',
        ));
        $publicoA = app(PublicoAlvoService::class)->criar($usuarioA, ['todos_usuarios' => true]);
        $evento = $this->criarEvento($publicoA->id, 'Evento de toda a rede', $agora->addDay());
        $source = app(ManualCalendarEventSource::class);

        $pessoal = collect($source->events(
            $this->contexto($usuarioRede, $agora, $agora->addDays(4)->endOfDay()),
        ));
        $rede = collect($source->events(
            $this->contexto($usuarioRede, $agora, $agora->addDays(4)->endOfDay(), redeCompleta: true),
        ));

        $this->assertNotContains($evento->id, $pessoal->map(fn (CalendarEventData $item): int => (int) $item->reference));
        $this->assertContains($evento->id, $rede->map(fn (CalendarEventData $item): int => (int) $item->reference));
    }

    public function test_modo_rede_rejeita_usuario_sem_a_permissao_especifica(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        [$usuario] = $this->usuariosDeEscolasDiferentes();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não possui acesso ao calendário de toda a rede');

        $this->contexto($usuario, $agora, $agora->addDays(4)->endOfDay(), redeCompleta: true);
    }

    /** @return array{0: User, 1: User} */
    private function usuariosDeEscolasDiferentes(): array
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor compartilhado da agenda manual',
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);
        $escolaA = $this->criarEscola('Agenda Manual A', $setor);
        $escolaB = $this->criarEscola('Agenda Manual B', $setor);

        return [
            User::factory()->create(['id_escola' => $escolaA->id]),
            User::factory()->create(['id_escola' => $escolaB->id]),
        ];
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

    private function criarEvento(
        int $publicoAlvoId,
        string $titulo,
        CarbonImmutable $inicio,
        bool $ativo = true,
        ?Escola $escola = null,
        ?Setor $setor = null,
        ?EventoCalendarioStatus $status = null,
    ): EventoCalendario {
        $evento = EventoCalendario::query()->create([
            'publico_alvo_id' => $publicoAlvoId,
            'escola_id' => $escola?->id,
            'setor_id' => $setor?->id,
            'enviar_todas_escolas' => ! $escola,
            'titulo' => $titulo,
            'descricao' => 'Descrição do evento manual.',
            'categoria' => EventoCalendarioCategoria::ADMINISTRATIVO,
            'assunto' => 'Comunicado',
            'prioridade' => DashboardPrioridade::Normal,
            'data_inicio' => $inicio,
            'data_fim' => $inicio->addHour(),
            'status' => $status ?? ($ativo
                ? EventoCalendarioStatus::PUBLICADO
                : EventoCalendarioStatus::INATIVO),
            'ativo' => $ativo,
            'cor' => EventoCalendarioCor::AZUL,
            'origem' => EventoCalendarioOrigem::MANUAL,
        ]);

        if ($escola) {
            $evento->escolasAgendadas()->create([
                'escola_id' => $escola->id,
                'hora_inicio' => $inicio->format('H:i'),
                'hora_fim' => $inicio->addHour()->format('H:i'),
                'precisa_transporte' => false,
            ]);
        }

        return $evento;
    }

    private function contexto(
        User $user,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        bool $redeCompleta = false,
    ): CalendarQueryContext {
        return new CalendarQueryContext(
            user: $user,
            userContext: app(DashboardUserContextFactory::class)->make($user),
            inicio: $inicio->startOfDay(),
            fim: $fim->endOfDay(),
            redeCompleta: $redeCompleta,
        );
    }
}
