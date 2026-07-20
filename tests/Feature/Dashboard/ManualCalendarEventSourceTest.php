<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioStatus;
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
    ): EventoCalendario {
        return EventoCalendario::query()->create([
            'publico_alvo_id' => $publicoAlvoId,
            'escola_id' => $escola?->id,
            'setor_id' => $setor?->id,
            'titulo' => $titulo,
            'descricao' => 'Descrição do evento manual.',
            'categoria' => EventoCalendarioCategoria::ADMINISTRATIVO,
            'assunto' => 'Comunicado',
            'prioridade' => DashboardPrioridade::Normal,
            'data_inicio' => $inicio,
            'data_fim' => $inicio->addHour(),
            'status' => EventoCalendarioStatus::AGENDADO,
            'ativo' => $ativo,
            'cor' => EventoCalendarioCor::AZUL,
            'origem' => EventoCalendarioOrigem::MANUAL,
        ]);
    }

    private function contexto(
        User $user,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
    ): CalendarQueryContext {
        return new CalendarQueryContext(
            user: $user,
            userContext: app(DashboardUserContextFactory::class)->make($user),
            inicio: $inicio->startOfDay(),
            fim: $fim->endOfDay(),
        );
    }
}
