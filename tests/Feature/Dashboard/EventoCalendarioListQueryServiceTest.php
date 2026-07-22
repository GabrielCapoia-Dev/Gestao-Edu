<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioHistoricoAcao;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\ListaPermissoes;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\Permission;
use App\Models\PublicoAlvo;
use App\Models\Setor;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioListQueryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventoCalendarioListQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private Setor $setor;

    private int $schoolSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->setor = Setor::query()->create([
            'nome' => 'Setor da listagem de eventos',
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);
    }

    public function test_query_carrega_resumo_separado_e_agregados_sem_carregar_relacao_canonica(): void
    {
        $user = User::factory()->create();
        $this->conceder($user, ListaPermissoes::ListarEventosGeral);
        $schools = [$this->school('A'), $this->school('B'), $this->school('C')];
        $target = $this->target($schools);
        $event = $this->event(
            $user,
            $target,
            'Evento com escolas resumidas',
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            '2026-07-28 08:00:00',
            false,
        );
        $this->schedule($event, $schools[0], true, 30);
        $this->schedule($event, $schools[1], false, null);
        $this->schedule($event, $schools[2], true, 20);

        $listed = app(EventoCalendarioListQueryService::class)
            ->query($user)
            ->sole();

        $this->assertTrue($listed->relationLoaded('criadoPor'));
        $this->assertTrue($listed->relationLoaded('escolasResumo'));
        $this->assertFalse($listed->relationLoaded('escolasAgendadas'));
        $this->assertCount(2, $listed->escolasResumo);
        $this->assertSame(3, $listed->escolas_agendadas_count);
        $this->assertSame(3, $listed->escolas_publico_count);
        $this->assertTrue($listed->possui_transporte);
        $this->assertSame(50, $listed->total_estudantes_transporte);
    }

    public function test_detalhes_reautoriza_e_carrega_todas_as_relacoes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->conceder($user, ListaPermissoes::ListarMeusEventos);
        $schools = [$this->school('D'), $this->school('E'), $this->school('F')];
        $target = $this->target($schools);
        $own = $this->event(
            $user,
            $target,
            'Evento próprio detalhado',
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            '2026-07-29 08:00:00',
            false,
        );

        foreach ($schools as $school) {
            $this->schedule($own, $school, true, 10);
        }

        $own->historicos()->create([
            'usuario_id' => $user->id,
            'acao' => EventoCalendarioHistoricoAcao::CRIADO,
            'status_novo' => EventoCalendarioStatus::PENDENTE_APROVACAO,
        ]);

        $foreign = $this->event(
            $other,
            $target,
            'Evento alheio',
            EventoCalendarioStatus::PUBLICADO,
            '2026-07-30 08:00:00',
        );

        $service = app(EventoCalendarioListQueryService::class);
        $details = $service->detalhes($user, (int) $own->id);

        $this->assertTrue($details->relationLoaded('escolasAgendadas'));
        $this->assertTrue($details->relationLoaded('historicos'));
        $this->assertCount(3, $details->escolasAgendadas);
        $this->assertTrue($details->escolasAgendadas->every(
            fn ($schedule): bool => $schedule->relationLoaded('escola'),
        ));

        $this->expectException(ModelNotFoundException::class);
        $service->detalhes($user, (int) $foreign->id);
    }

    public function test_filtro_e_opcoes_usam_as_escolas_reais_do_publico_abrangente(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->conceder($user, ListaPermissoes::ListarMeusEventos);
        $schoolA = $this->school('G');
        $schoolB = $this->school('H');
        $schoolC = $this->school('I');

        $broadA = $this->event(
            $user,
            $this->target([$schoolA]),
            'Abrangente para A',
            EventoCalendarioStatus::PUBLICADO,
            '2026-08-01 08:00:00',
        );
        $specificB = $this->event(
            $user,
            $this->target([$schoolB]),
            'Específico para B',
            EventoCalendarioStatus::PUBLICADO,
            '2026-08-02 08:00:00',
            false,
        );
        $this->schedule($specificB, $schoolB, false, null);
        $this->event(
            $other,
            $this->target([$schoolC]),
            'Abrangente alheio para C',
            EventoCalendarioStatus::PUBLICADO,
            '2026-08-03 08:00:00',
        );

        $service = app(EventoCalendarioListQueryService::class);

        $this->assertSame(
            [$broadA->id],
            $service->applySchoolFilter($service->query($user), (int) $schoolA->id)
                ->pluck('eventos_calendario.id')
                ->all(),
        );
        $this->assertSame(
            [$specificB->id],
            $service->applySchoolFilter($service->query($user), (int) $schoolB->id)
                ->pluck('eventos_calendario.id')
                ->all(),
        );
        $this->assertSame([], $service->applySchoolFilter(
            $service->query($user),
            (int) $schoolC->id,
        )->pluck('eventos_calendario.id')->all());
        $this->assertSame(
            [$schoolA->id => $schoolA->nome, $schoolB->id => $schoolB->nome],
            $service->schoolOptions($user),
        );
        $this->assertSame([$user->id => $user->name], $service->creatorOptions($user));
    }

    public function test_alerta_de_inversao_compara_data_e_horario_em_uma_consulta(): void
    {
        $user = User::factory()->create();
        $this->conceder($user, ListaPermissoes::ListarEventosGeral);
        $school = $this->school('J');
        $target = $this->target([$school]);
        $first = $this->event(
            $user,
            $target,
            'Solicitação anterior',
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            '2026-08-10 14:00:00',
        );
        $inverted = $this->event(
            $user,
            $target,
            'Solicitação que entrou na frente',
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            '2026-08-10 10:00:00',
        );
        $notInverted = $this->event(
            $user,
            $target,
            'Solicitação posterior',
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            '2026-08-11 08:00:00',
        );
        $common = $this->event(
            $user,
            $target,
            'Evento comum posterior',
            EventoCalendarioStatus::PUBLICADO,
            '2026-08-09 08:00:00',
        );
        $this->schedule($first, $school, true, 20);
        $this->schedule($inverted, $school, true, 20);
        $this->schedule($notInverted, $school, true, 20);
        $first->forceFill(['created_at' => '2026-07-22 08:00:00'])->saveQuietly();
        $inverted->forceFill(['created_at' => '2026-07-23 08:00:00'])->saveQuietly();
        $notInverted->forceFill(['created_at' => '2026-07-24 08:00:00'])->saveQuietly();
        $common->forceFill(['created_at' => '2026-07-25 08:00:00'])->saveQuietly();

        $listed = app(EventoCalendarioListQueryService::class)
            ->query($user)
            ->get()
            ->keyBy('id');

        $this->assertFalse($listed[$first->id]->possui_inversao_fila);
        $this->assertTrue($listed[$inverted->id]->possui_inversao_fila);
        $this->assertFalse($listed[$notInverted->id]->possui_inversao_fila);
        $this->assertFalse($listed[$common->id]->possui_inversao_fila);

        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $this->conceder($owner, ListaPermissoes::ListarMeusEventos);
        $outsideFirst = $this->event(
            $outsider,
            $target,
            'Transporte anterior de outro criador',
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            '2026-08-20 08:00:00',
            false,
        );
        $ownLater = $this->event(
            $owner,
            $target,
            'Transporte próprio posterior',
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            '2026-08-19 08:00:00',
            false,
        );
        $this->schedule($outsideFirst, $school, true, 20);
        $this->schedule($ownLater, $school, true, 20);
        $outsideFirst->forceFill(['created_at' => '2026-07-26 08:00:00'])->saveQuietly();
        $ownLater->forceFill(['created_at' => '2026-07-27 08:00:00'])->saveQuietly();

        $ownListed = app(EventoCalendarioListQueryService::class)
            ->query($owner)
            ->sole();

        $this->assertFalse($ownListed->possui_inversao_fila);
    }

    public function test_indicadores_consideram_somente_transporte_e_respeitam_filtros_do_builder(): void
    {
        $user = User::factory()->create();
        $this->conceder($user, ListaPermissoes::ListarEventosGeral);
        $school = $this->school('K');
        $target = $this->target([$school]);

        foreach ([
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            EventoCalendarioStatus::PUBLICADO,
            EventoCalendarioStatus::REJEITADO,
            EventoCalendarioStatus::INATIVO,
        ] as $index => $status) {
            $event = $this->event(
                $user,
                $target,
                'Transporte '.$status->value,
                $status,
                sprintf('2026-08-%02d 08:00:00', 12 + $index),
                false,
            );
            $this->schedule($event, $school, true, 20);
        }

        $this->event(
            $user,
            $target,
            'Evento comum publicado',
            EventoCalendarioStatus::PUBLICADO,
            '2026-08-16 08:00:00',
        );

        $service = app(EventoCalendarioListQueryService::class);

        $this->assertSame([
            'pendentes' => 1,
            'publicados' => 1,
            'rejeitados' => 1,
            'total_transporte' => 4,
        ], $service->indicadores($service->query($user)));

        $this->assertSame([
            'pendentes' => 0,
            'publicados' => 1,
            'rejeitados' => 0,
            'total_transporte' => 1,
        ], $service->indicadores(
            $service->query($user)->where('eventos_calendario.status', EventoCalendarioStatus::PUBLICADO),
        ));
    }

    private function school(string $suffix): Escola
    {
        $this->schoolSequence++;

        return Escola::query()->create([
            'codigo' => "ESC-LIST-{$this->schoolSequence}",
            'nome' => "Escola {$suffix}",
            'email' => "escola-list-{$this->schoolSequence}@teste.local",
            'setor_id' => $this->setor->id,
            'ativo' => true,
        ]);
    }

    /** @param list<Escola> $schools */
    private function target(array $schools): PublicoAlvo
    {
        $target = PublicoAlvo::query()->create([
            'modo_correspondencia' => 'qualquer',
            'todos_usuarios' => false,
            'escopo_global' => true,
        ]);
        $target->escolas()->sync(array_map(
            fn (Escola $school): int => (int) $school->id,
            $schools,
        ));

        return $target;
    }

    private function event(
        User $creator,
        PublicoAlvo $target,
        string $title,
        EventoCalendarioStatus $status,
        string $start,
        bool $allSchools = true,
    ): EventoCalendario {
        $end = date('Y-m-d H:i:s', strtotime($start.' + 2 hours'));

        return EventoCalendario::query()->create([
            'publico_alvo_id' => $target->id,
            'enviar_todas_escolas' => $allSchools,
            'titulo' => $title,
            'descricao' => 'Descrição usada nos testes da consulta de listagem.',
            'categoria' => EventoCalendarioCategoria::ADMINISTRATIVO,
            'prioridade' => DashboardPrioridade::Normal,
            'data_inicio' => $start,
            'data_fim' => $end,
            'status' => $status,
            'ativo' => $status === EventoCalendarioStatus::PUBLICADO,
            'cor' => EventoCalendarioCor::AZUL,
            'origem' => EventoCalendarioOrigem::MANUAL,
            'criado_por_id' => $creator->id,
            'atualizado_por_id' => $creator->id,
        ]);
    }

    private function schedule(
        EventoCalendario $event,
        Escola $school,
        bool $needsTransport,
        ?int $estimatedStudents,
    ): void {
        $event->escolasAgendadas()->create([
            'escola_id' => $school->id,
            'hora_inicio' => $event->data_inicio->format('H:i'),
            'hora_fim' => $event->data_fim->format('H:i'),
            'precisa_transporte' => $needsTransport,
            'quantidade_estimada_transporte' => $estimatedStudents,
        ]);
    }

    private function conceder(User $user, ListaPermissoes $permission): void
    {
        Permission::findOrCreate($permission->label(), 'web');
        $user->givePermissionTo($permission->label());
    }
}
