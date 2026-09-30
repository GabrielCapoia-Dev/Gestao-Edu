<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioHistoricoAcao;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\ListaPermissoes;
use App\Models\Enums\PublicoAlvoModoCorrespondencia;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\Permission;
use App\Models\PublicoAlvo;
use App\Models\Setor;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventoCalendarioWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_aprova_evento_de_transporte_pendente_e_registra_historico(): void
    {
        $ator = $this->usuarioComPermissoes(
            ListaPermissoes::ListarEventosGeral,
            ListaPermissoes::PublicarEventosTransporte,
        );
        $evento = $this->evento(EventoCalendarioStatus::PENDENTE_APROVACAO, false, transporte: true);

        $resultado = app(EventoCalendarioWorkflowService::class)->publicar($evento, $ator);

        $this->assertSame(EventoCalendarioStatus::PUBLICADO, $resultado->status);
        $this->assertTrue($resultado->ativo);
        $this->assertSame($ator->id, $resultado->atualizado_por_id);
        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'usuario_id' => $ator->id,
            'acao' => EventoCalendarioHistoricoAcao::APROVADO->value,
            'status_anterior' => EventoCalendarioStatus::PENDENTE_APROVACAO->value,
            'status_novo' => EventoCalendarioStatus::PUBLICADO->value,
            'motivo' => null,
        ]);
    }

    public function test_rejeita_evento_de_transporte_pendente_com_motivo_e_historico(): void
    {
        $ator = $this->usuarioComPermissoes(
            ListaPermissoes::ListarEventosGeral,
            ListaPermissoes::RejeitarEventosTransporte,
        );
        $evento = $this->evento(EventoCalendarioStatus::PENDENTE_APROVACAO, false, transporte: true);

        $resultado = app(EventoCalendarioWorkflowService::class)->rejeitar(
            $evento,
            $ator,
            'Transporte incompatível com a programação.',
        );

        $this->assertSame(EventoCalendarioStatus::REJEITADO, $resultado->status);
        $this->assertFalse($resultado->ativo);
        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'usuario_id' => $ator->id,
            'acao' => EventoCalendarioHistoricoAcao::REJEITADO->value,
            'status_anterior' => EventoCalendarioStatus::PENDENTE_APROVACAO->value,
            'status_novo' => EventoCalendarioStatus::REJEITADO->value,
            'motivo' => 'Transporte incompatível com a programação.',
        ]);
    }

    public function test_rejeita_automaticamente_eventos_pendentes_com_inicio_passado(): void
    {
        $agora = Carbon::parse('2026-09-30 12:00:00');
        $vencido = $this->evento(EventoCalendarioStatus::PENDENTE_APROVACAO, false, transporte: true);
        $vencido->update([
            'data_inicio' => $agora->copy()->subMinute(),
            'data_fim' => $agora->copy()->addHour(),
        ]);

        $futuro = $this->evento(EventoCalendarioStatus::PENDENTE_APROVACAO, false, transporte: true);
        $futuro->update([
            'data_inicio' => $agora->copy()->addMinute(),
            'data_fim' => $agora->copy()->addHours(2),
        ]);

        $publicado = $this->evento(EventoCalendarioStatus::PUBLICADO, true, transporte: true);
        $publicado->update([
            'data_inicio' => $agora->copy()->subHour(),
            'data_fim' => $agora->copy()->addHour(),
        ]);

        $workflow = app(EventoCalendarioWorkflowService::class);

        $this->assertSame(1, $workflow->rejeitarPendentesExpirados($agora));

        $this->assertSame(EventoCalendarioStatus::REJEITADO, $vencido->refresh()->status);
        $this->assertFalse($vencido->ativo);
        $this->assertNull($vencido->atualizado_por_id);
        $this->assertSame(EventoCalendarioStatus::PENDENTE_APROVACAO, $futuro->refresh()->status);
        $this->assertSame(EventoCalendarioStatus::PUBLICADO, $publicado->refresh()->status);
        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $vencido->id,
            'usuario_id' => null,
            'acao' => EventoCalendarioHistoricoAcao::REJEITADO->value,
            'status_anterior' => EventoCalendarioStatus::PENDENTE_APROVACAO->value,
            'status_novo' => EventoCalendarioStatus::REJEITADO->value,
            'motivo' => 'Rejeitado automaticamente: o horário de início do evento já passou.',
        ]);
        $this->assertSame(0, $workflow->rejeitarPendentesExpirados($agora));
    }

    public function test_evento_de_transporte_rejeitado_precisa_ser_reenviado_antes_de_publicar(): void
    {
        $ator = $this->usuarioComPermissoes(
            ListaPermissoes::ListarEventosGeral,
            ListaPermissoes::PublicarEventosTransporte,
        );
        $evento = $this->evento(EventoCalendarioStatus::REJEITADO, false, transporte: true);

        $this->expectException(AuthorizationException::class);

        app(EventoCalendarioWorkflowService::class)->publicar($evento, $ator);
    }

    public function test_desativa_evento_comum_publicado_e_registra_historico(): void
    {
        $ator = $this->usuarioComPermissoes(
            ListaPermissoes::ListarEventosGeral,
            ListaPermissoes::DesativarEventos,
        );
        $evento = $this->evento(EventoCalendarioStatus::PUBLICADO, true);

        $resultado = app(EventoCalendarioWorkflowService::class)->desativar($evento, $ator);

        $this->assertSame(EventoCalendarioStatus::INATIVO, $resultado->status);
        $this->assertFalse($resultado->ativo);
        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'usuario_id' => $ator->id,
            'acao' => EventoCalendarioHistoricoAcao::DESATIVADO->value,
            'status_anterior' => EventoCalendarioStatus::PUBLICADO->value,
            'status_novo' => EventoCalendarioStatus::INATIVO->value,
        ]);
    }

    public function test_distingue_primeira_publicacao_de_republicacao_de_evento_comum(): void
    {
        $ator = $this->usuarioComPermissoes(
            ListaPermissoes::ListarEventosGeral,
            ListaPermissoes::PublicarEventos,
            ListaPermissoes::DesativarEventos,
        );
        $evento = $this->evento(EventoCalendarioStatus::INATIVO, false);
        $workflow = app(EventoCalendarioWorkflowService::class);

        $workflow->publicar($evento, $ator);

        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'acao' => EventoCalendarioHistoricoAcao::PUBLICADO->value,
        ]);

        $workflow->desativar($evento->refresh(), $ator);
        $workflow->publicar($evento->refresh(), $ator);

        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'acao' => EventoCalendarioHistoricoAcao::REPUBLICADO->value,
        ]);
    }

    public function test_permissoes_comuns_nao_aprovam_evento_de_transporte(): void
    {
        $ator = $this->usuarioComPermissoes(
            ListaPermissoes::ListarEventosGeral,
            ListaPermissoes::PublicarEventos,
            ListaPermissoes::DesativarEventos,
        );
        $evento = $this->evento(EventoCalendarioStatus::PENDENTE_APROVACAO, false, transporte: true);

        try {
            app(EventoCalendarioWorkflowService::class)->publicar($evento, $ator);
            $this->fail('Era esperada a negação da aprovação de transporte.');
        } catch (AuthorizationException) {
            // A permissão de publicação comum não deve aprovar transporte.
        }

        $evento->refresh();
        $this->assertSame(EventoCalendarioStatus::PENDENTE_APROVACAO, $evento->status);
        $this->assertFalse($evento->ativo);
        $this->assertDatabaseMissing('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'acao' => EventoCalendarioHistoricoAcao::APROVADO->value,
        ]);
    }

    private function usuarioComPermissoes(ListaPermissoes ...$permissoes): User
    {
        $user = User::factory()->create(['email_approved' => true]);

        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao->label(), 'web');
            $user->givePermissionTo($permissao->label());
        }

        return $user;
    }

    private function evento(
        EventoCalendarioStatus $status,
        bool $ativo,
        bool $transporte = false,
    ): EventoCalendario {
        $publico = PublicoAlvo::query()->create([
            'modo_correspondencia' => PublicoAlvoModoCorrespondencia::Qualquer,
            'todos_usuarios' => true,
            'escopo_global' => true,
        ]);
        $criador = User::factory()->create();
        $evento = EventoCalendario::query()->create([
            'publico_alvo_id' => $publico->id,
            'enviar_todas_escolas' => ! $transporte,
            'titulo' => $transporte ? 'Evento com transporte' : 'Evento comum',
            'descricao' => 'Evento para validar o fluxo de publicação.',
            'categoria' => EventoCalendarioCategoria::ADMINISTRATIVO,
            'prioridade' => DashboardPrioridade::Normal,
            'data_inicio' => '2026-07-25 08:00:00',
            'data_fim' => '2026-07-25 12:00:00',
            'status' => $status,
            'ativo' => $ativo,
            'cor' => EventoCalendarioCor::AZUL,
            'origem' => EventoCalendarioOrigem::MANUAL,
            'criado_por_id' => $criador->id,
            'atualizado_por_id' => $criador->id,
        ]);

        if ($transporte) {
            $escola = $this->escola();
            $evento->escolasAgendadas()->create([
                'escola_id' => $escola->id,
                'hora_inicio' => '08:00',
                'hora_fim' => '12:00',
                'precisa_transporte' => true,
                'quantidade_estimada_transporte' => 20,
            ]);
        }

        return $evento;
    }

    private function escola(): Escola
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor '.fake()->unique()->numerify('####'),
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);

        return Escola::query()->create([
            'codigo' => fake()->unique()->bothify('ESC-####'),
            'nome' => 'Escola '.fake()->unique()->numerify('####'),
            'email' => fake()->unique()->safeEmail(),
            'setor_id' => $setor->id,
            'ativo' => true,
        ]);
    }
}
