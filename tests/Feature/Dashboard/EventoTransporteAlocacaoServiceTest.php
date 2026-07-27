<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioHistoricoAcao;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\ListaPermissoes;
use App\Models\Enums\PublicoAlvoModoCorrespondencia;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\EventoCalendarioTransporteAlocacao;
use App\Models\FuncaoAdministrativa;
use App\Models\Permission;
use App\Models\PublicoAlvo;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\User;
use App\Models\VeiculoTransporte;
use App\Livewire\Transporte\EventoTransporteEscolasManager;
use App\Services\Dashboard\EventoTransporteAlocacaoService;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\ServidorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventoTransporteAlocacaoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_exclusao_da_pessoa_e_bloqueada_enquanto_motorista_estiver_em_evento_futuro(): void
    {
        $ator = $this->ator();
        $evento = $this->evento($ator, 'Evento futuro');
        $motorista = $this->motorista('12345678901', 'Motorista vinculado');
        $veiculo = $this->veiculo('ABC1D23', 40);

        EventoCalendarioTransporteAlocacao::query()->create([
            'evento_calendario_id' => $evento->id,
            'veiculo_transporte_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'motorista_nome' => $motorista->nome,
            'criado_por_id' => $ator->id,
        ]);

        try {
            app(ServidorService::class)->excluirPessoa($motorista);
            $this->fail('A exclusão deveria ser bloqueada.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Substitua o motorista',
                $exception->errors()['pessoa'][0],
            );
        }

        $this->assertDatabaseHas('servidores', ['id' => $motorista->id]);
        $this->assertDatabaseHas('eventos_calendario', ['id' => $evento->id]);
    }

    public function test_exclusao_preserva_fotografia_do_motorista_em_evento_ja_atendido(): void
    {
        $ator = $this->ator();
        $evento = $this->evento($ator, 'Evento atendido');
        $evento->forceFill([
            'data_inicio' => '2026-01-10 08:00:00',
            'data_fim' => '2026-01-10 12:00:00',
        ])->save();
        $motorista = $this->motorista('10987654321', 'Motorista histórico');
        $motorista->forceFill(['matricula' => 'MOT-10'])->save();
        $veiculo = $this->veiculo('DEF4G56', 40);
        $alocacao = EventoCalendarioTransporteAlocacao::query()->create([
            'evento_calendario_id' => $evento->id,
            'veiculo_transporte_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'motorista_nome' => $motorista->nome,
            'motorista_cpf' => $motorista->cpf,
            'motorista_matricula' => $motorista->matricula,
            'criado_por_id' => $ator->id,
        ]);

        app(ServidorService::class)->excluirPessoa($motorista);

        $this->assertDatabaseMissing('servidores', ['id' => $motorista->id]);
        $this->assertDatabaseHas('eventos_calendario', ['id' => $evento->id]);
        $this->assertDatabaseHas('evento_calendario_transporte_alocacoes', [
            'id' => $alocacao->id,
            'motorista_id' => null,
            'motorista_nome' => 'Motorista histórico',
            'motorista_cpf' => '10987654321',
            'motorista_matricula' => 'MOT-10',
        ]);
    }

    public function test_aloca_recursos_ativos_sem_bloqueio_por_capacidade(): void
    {
        $ator = $this->ator();
        $evento = $this->evento($ator, 'Evento com transporte');
        $agendamento = $evento->escolasAgendadas()->firstOrFail();
        $turma = $this->turma((int) $agendamento->escola_id);
        $veiculo = $this->veiculo('ABC1234', 1);
        $motorista = $this->motorista('11111111111', 'Motorista A');

        $alocacao = app(EventoTransporteAlocacaoService::class)->atribuirTurmasDaEscola(
            $ator,
            $evento,
            (int) $agendamento->getKey(),
            $veiculo->id,
            $motorista->id,
            [$turma->id],
        );

        $this->assertTrue($alocacao->estaAtiva());
        $this->assertSame($veiculo->id, $alocacao->veiculo->id);
        $this->assertSame($motorista->id, $alocacao->motorista->id);
        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'usuario_id' => $ator->id,
            'acao' => EventoCalendarioHistoricoAcao::ALOCACAO_TRANSPORTE_ADICIONADA->value,
        ]);

        $resumo = app(EventoTransporteAlocacaoService::class)->resumo($ator, $evento);
        $this->assertCount(1, $resumo['alocacoes']);
        $this->assertFalse($resumo['possui_recursos_inativos']);
    }

    public function test_gestor_consegue_ocultar_relacao_de_veiculos_ja_alocados(): void
    {
        $ator = $this->ator();
        $evento = $this->evento($ator, 'Evento com relação de veículos');
        $agendamento = $evento->escolasAgendadas()->firstOrFail();
        $turma = $this->turma((int) $agendamento->escola_id);

        app(EventoTransporteAlocacaoService::class)->atribuirTurmasDaEscola(
            $ator,
            $evento,
            (int) $agendamento->getKey(),
            $this->veiculo('REL1A23', 40)->id,
            $this->motorista('12121212121', 'Motorista da relação')->id,
            [$turma->id],
        );

        $this->actingAs($ator);

        Livewire::test(EventoTransporteEscolasManager::class, ['eventoId' => $evento->id])
            ->assertSet('mostrarRelacaoVeiculos', false)
            ->call('alternarRelacaoVeiculos')
            ->assertSet('mostrarRelacaoVeiculos', true)
            ->call('alternarRelacaoVeiculos')
            ->assertSet('mostrarRelacaoVeiculos', false);
    }

    public function test_gestor_vincula_turma_independentemente_da_capacidade_do_veiculo(): void
    {
        $ator = $this->ator();
        $evento = $this->evento($ator, 'Evento para atribuição');
        $agendamento = $evento->escolasAgendadas()->firstOrFail();
        $turma = $this->turma((int) $agendamento->escola_id);
        $veiculo = $this->veiculo('VIN1B23', 1);
        $motorista = $this->motorista('13131313131', 'Motorista da atribuição');

        $this->actingAs($ator);

        Livewire::test(EventoTransporteEscolasManager::class, ['eventoId' => $evento->id])
            ->set("veiculosSelecionados.{$agendamento->id}", $veiculo->id)
            ->set("motoristasSelecionados.{$agendamento->id}", $motorista->id)
            ->call('alternarTurma', $agendamento->id, $turma->id, true)
            ->assertSet("turmasSelecionadas.{$agendamento->id}", [$turma->id])
            ->call('alocar', $agendamento->id)
            ->assertHasNoErrors();

        $alocacao = EventoCalendarioTransporteAlocacao::query()
            ->where('evento_calendario_id', $evento->id)
            ->where('veiculo_transporte_id', $veiculo->id)
            ->firstOrFail();

        $this->assertDatabaseHas('evento_transporte_alocacao_turma', [
            'alocacao_id' => $alocacao->id,
            'turma_id' => $turma->id,
        ]);
    }

    public function test_permite_reutilizar_veiculo_e_motorista_em_eventos_sobrepostos(): void
    {
        $ator = $this->ator();
        $veiculo = $this->veiculo('ABC1234', 40);
        $motorista = $this->motorista('22222222222', 'Motorista B');
        $ocupado = $this->evento($ator, 'Evento confidencial');
        $destino = $this->evento($ator, 'Outro evento no mesmo período');
        $service = app(EventoTransporteAlocacaoService::class);
        $service->adicionar($ator, $ocupado, $veiculo->id, $motorista->id);

        $segundaAlocacao = $service->adicionar($ator, $destino, $veiculo->id, $motorista->id);

        $this->assertTrue($segundaAlocacao->estaAtiva());
        $this->assertSame($veiculo->id, $segundaAlocacao->veiculo_transporte_id);
        $this->assertSame($motorista->id, $segundaAlocacao->motorista_id);
    }

    public function test_permite_reutilizar_recursos_quando_um_evento_comeca_no_fim_do_outro(): void
    {
        $ator = $this->ator();
        $veiculo = $this->veiculo('ABC1234', 40);
        $motorista = $this->motorista('88888888888', 'Motorista H');
        $primeiro = $this->evento($ator, 'Evento da manhã');
        $segundo = $this->evento($ator, 'Evento da tarde');
        $segundo->forceFill([
            'data_inicio' => '2026-08-10 12:00:00',
            'data_fim' => '2026-08-10 16:00:00',
        ])->save();
        $service = app(EventoTransporteAlocacaoService::class);

        $service->adicionar($ator, $primeiro, $veiculo->id, $motorista->id);
        $segundaAlocacao = $service->adicionar($ator, $segundo->fresh(), $veiculo->id, $motorista->id);

        $this->assertTrue($segundaAlocacao->estaAtiva());
    }

    public function test_remocao_e_logica_e_delete_da_policy_permanece_negado(): void
    {
        $ator = $this->ator();
        $evento = $this->evento($ator, 'Evento a remover');
        $alocacao = app(EventoTransporteAlocacaoService::class)->adicionar(
            $ator,
            $evento,
            $this->veiculo('ABC1234', 40)->id,
            $this->motorista('33333333333', 'Motorista C')->id,
        );

        $removida = app(EventoTransporteAlocacaoService::class)->remover($ator, $alocacao);

        $this->assertNotNull($removida->removido_em);
        $this->assertSame($ator->id, $removida->removido_por_id);
        $this->assertDatabaseCount('evento_calendario_transporte_alocacoes', 1);
        $this->assertFalse(Gate::forUser($ator)->allows('delete', $removida));
    }

    public function test_usuario_que_visualiza_evento_enxerga_alocacoes_sem_poder_altera_las(): void
    {
        $ator = $this->ator();
        $evento = $this->evento($ator, 'Evento visível em leitura');
        $service = app(EventoTransporteAlocacaoService::class);
        $service->adicionar(
            $ator,
            $evento,
            $this->veiculo('ABC1234', 40)->id,
            $this->motorista('55555555555', 'Motorista E')->id,
        );

        $leitor = User::factory()->create(['email_approved' => true]);
        Permission::findOrCreate(ListaPermissoes::ListarEventosGeral->label(), 'web');
        $leitor->givePermissionTo(ListaPermissoes::ListarEventosGeral->label());

        $this->assertCount(1, $service->queryAtivas($leitor, $evento)->get());
        $this->assertFalse(Gate::forUser($leitor)->allows('manageTransport', $evento));

        $this->expectException(AuthorizationException::class);

        $service->adicionar(
            $leitor,
            $evento,
            $this->veiculo('XYZ9Z99', 20)->id,
            $this->motorista('66666666666', 'Motorista F')->id,
        );
    }

    public function test_recurso_inativo_permanece_visivel_no_resumo(): void
    {
        $ator = $this->ator();
        $evento = $this->evento($ator, 'Evento com recurso posteriormente inativo');
        $veiculo = $this->veiculo('ABC1234', 40);
        $service = app(EventoTransporteAlocacaoService::class);
        $service->adicionar(
            $ator,
            $evento,
            $veiculo->id,
            $this->motorista('77777777777', 'Motorista G')->id,
        );

        $veiculo->forceFill(['ativo' => false])->save();
        $resumo = $service->resumo($ator, $evento);

        $this->assertCount(1, $resumo['alocacoes']);
        $this->assertTrue($resumo['possui_recursos_inativos']);
    }

    public function test_edicao_que_remove_transporte_libera_alocacoes_ativas_sem_permissao_de_gerencia(): void
    {
        $ator = $this->ator();
        $evento = $this->evento($ator, 'Evento que deixa de usar transporte');
        $evento->load('escolasAgendadas');
        $ator->forceFill(['id_escola' => $evento->escolasAgendadas->first()->escola_id])->save();
        $alocacao = app(EventoTransporteAlocacaoService::class)->adicionar(
            $ator,
            $evento,
            $this->veiculo('ABC1234', 40)->id,
            $this->motorista('44444444444', 'Motorista D')->id,
        );
        $ator->revokePermissionTo(ListaPermissoes::GerenciarTransporteDeEventos->label());

        app(EventoCalendarioService::class)->atualizar($evento, [
            'titulo' => 'Evento sem transporte',
            'descricao' => 'A programação foi atualizada.',
            'categoria' => EventoCalendarioCategoria::ADMINISTRATIVO->value,
            'data_evento' => '2026-08-10',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'cor' => 'azul',
            'inserir_link' => false,
            'enviar_escolas_especificas' => false,
        ], null, $ator);

        $this->assertNotNull($alocacao->fresh()->removido_em);
        $this->assertDatabaseHas('evento_calendario_historicos', [
            'evento_calendario_id' => $evento->id,
            'acao' => EventoCalendarioHistoricoAcao::ALOCACOES_TRANSPORTE_REMOVIDAS->value,
        ]);
    }

    private function ator(): User
    {
        $ator = User::factory()->create(['email_approved' => true]);
        $admin = Role::findOrCreate('Admin', 'web');
        $ator->assignRole($admin);
        $permissoes = [
            ListaPermissoes::ListarEventosGeral,
            ListaPermissoes::GerenciarTransporteDeEventos,
            ListaPermissoes::EditarEventos,
        ];

        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao->label(), 'web');
            $ator->givePermissionTo($permissao->label());
        }

        return $ator;
    }

    private function evento(User $ator, string $titulo): EventoCalendario
    {
        $publico = PublicoAlvo::query()->create([
            'modo_correspondencia' => PublicoAlvoModoCorrespondencia::Qualquer,
            'todos_usuarios' => true,
            'escopo_global' => true,
        ]);
        $evento = EventoCalendario::query()->create([
            'publico_alvo_id' => $publico->id,
            'enviar_todas_escolas' => false,
            'titulo' => $titulo,
            'categoria' => EventoCalendarioCategoria::ADMINISTRATIVO,
            'prioridade' => DashboardPrioridade::Normal,
            'data_inicio' => '2026-08-10 08:00:00',
            'data_fim' => '2026-08-10 12:00:00',
            'status' => EventoCalendarioStatus::PENDENTE_APROVACAO,
            'ativo' => false,
            'cor' => 'azul',
            'origem' => EventoCalendarioOrigem::MANUAL,
            'criado_por_id' => $ator->id,
            'atualizado_por_id' => $ator->id,
        ]);
        $evento->escolasAgendadas()->create([
            'escola_id' => $this->escola()->id,
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'precisa_transporte' => true,
            'quantidade_estimada_transporte' => 20,
        ]);

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

    private function veiculo(string $placa, int $capacidade): VeiculoTransporte
    {
        return VeiculoTransporte::query()->create([
            'placa' => $placa,
            'identificacao' => 'Veículo '.$placa,
            'capacidade_passageiros' => $capacidade,
            'ativo' => true,
        ]);
    }

    private function turma(int $escolaId): Turma
    {
        $serie = Serie::query()->create([
            'nome' => '1º Ano',
        ]);

        return Turma::query()->create([
            'nome' => fake()->unique()->bothify('Turma ??'),
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escolaId,
        ]);
    }

    private function motorista(string $cpf, string $nome): Servidor
    {
        $motorista = Servidor::query()->create([
            'cpf' => $cpf,
            'nome' => $nome,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $funcao = FuncaoAdministrativa::motoristaPadrao();
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $motorista->id,
            'funcao_administrativa_id' => $funcao->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
        ]);

        return $motorista;
    }
}
