<?php

namespace Tests\Feature\Manutencao;

use App\Filament\Admin\Resources\Pedidos\Pages\ListPedidos;
use App\Filament\Admin\Resources\Pedidos\Pages\EditPedido;
use App\Filament\Admin\Resources\Pedidos\PedidoResource;
use App\Filament\Admin\Resources\Pedidos\RelationManagers\PedidosAdicionaisRelationManager;
use App\Models\EmpresaContratada;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\ResultadoFeedbackPedido;
use App\Models\Escola;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\PedidoService;
use App\Services\Relatorios\PedidoRelatorioGeralService;
use App\Services\Relatorios\RelatorioPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PedidoServiceFluxoManutencaoTest extends TestCase
{
    use RefreshDatabase;

    private PedidoService $service;

    private Setor $educacao;

    private Setor $obras;

    private Escola $escola;

    private TipoManutencao $tipo;

    private TipoManutencaoOpcao $opcaoLuz;

    private TipoManutencaoOpcao $opcaoDisjuntor;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->service = app(PedidoService::class);
        $this->seedStatus();

        $this->educacao = Setor::create(['nome' => 'Educação', 'ativo' => true, 'status' => 'Ativo', 'is_default_root' => true]);
        $this->obras = Setor::create(['nome' => 'Obras', 'parent_id' => $this->educacao->id, 'ativo' => true, 'status' => 'Ativo']);
        $escolaSetor = Setor::create(['nome' => 'Escola Teste Setor', 'parent_id' => $this->educacao->id, 'ativo' => true, 'status' => 'Ativo']);

        $this->escola = Escola::create([
            'codigo' => '001',
            'nome' => 'Escola Teste',
            'setor_id' => $escolaSetor->id,
            'ativo' => true,
        ]);

        $this->tipo = TipoManutencao::create([
            'nome' => 'Elétrica',
            'descricao' => 'Serviços elétricos',
            'ativo' => true,
        ]);

        $this->opcaoLuz = TipoManutencaoOpcao::create([
            'tipo_manutencao_id' => $this->tipo->id,
            'texto' => 'Sem luz na unidade',
            'ativo' => true,
        ]);

        $this->opcaoDisjuntor = TipoManutencaoOpcao::create([
            'tipo_manutencao_id' => $this->tipo->id,
            'texto' => 'Disjuntor queimado',
            'ativo' => true,
        ]);
    }

    public function test_cria_pedido_com_data_de_identificacao_e_multiplas_opcoes_ativas(): void
    {
        $inativa = TipoManutencaoOpcao::create([
            'tipo_manutencao_id' => $this->tipo->id,
            'texto' => 'Opcao inativa',
            'ativo' => false,
        ]);

        $usuario = User::factory()->create([
            'id_escola' => $this->escola->id,
            'email_approved' => true,
        ]);

        $pedido = $this->service->criarPedido([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoLuz->id, $this->opcaoDisjuntor->id, $inativa->id],
            'data_identificacao_problema' => '2026-05-01',
            'descricao_pedido' => 'Unidade ficou sem energia no bloco principal.',
            'nome_solicitante' => 'Direção',
        ], $usuario);

        $this->assertSame('2026-05-01', $pedido->data_identificacao_problema->toDateString());
        $this->assertSame('Em Aberto', $pedido->tipoStatus->nome);
        $this->assertTrue($pedido->setor->is($this->educacao));
        $this->assertFalse((bool) $pedido->is_pedido_adicional);
        $this->assertSame([
            'Sem luz na unidade',
            'Disjuntor queimado',
        ], $pedido->problemas()->orderBy('id')->pluck('texto_problema')->all());
    }

    public function test_criacao_de_pedido_salva_fotos_no_storage_publico(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create([
            'id_escola' => $this->escola->id,
            'email_approved' => true,
        ]);

        $pedido = $this->service->criarPedido([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoLuz->id],
            'data_identificacao_problema' => '2026-05-01',
            'descricao_pedido' => 'Foto enviada na abertura do pedido.',
            'nome_solicitante' => 'Direcao',
            'arquivos' => [UploadedFile::fake()->image('problema.jpg')],
        ], $usuario);

        $arquivo = $pedido->arquivos()->firstOrFail();

        $this->assertSame('fotos_problema', $arquivo->tipo_arquivo->value);
        $this->assertStringStartsWith('pedidos/', $arquivo->caminho);
        Storage::disk('public')->assertExists($arquivo->caminho);
    }

    public function test_escopo_por_role_de_setor_escola_e_permissao_global(): void
    {
        $pedidoEducacao = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);

        $outraEscola = Escola::create(['codigo' => '002', 'nome' => 'Outra Escola', 'setor_id' => $this->obras->id, 'ativo' => true]);
        $pedidoObras = $this->pedido(status: 'Em Aberto', setor: $this->obras, escola: $outraEscola);

        $educacaoUser = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, ['Listar Pedidos']);
        $globalUser = $this->usuarioComPermissoes(['Listar Todos os Pedidos']);
        $escolaUser = User::factory()->create(['id_escola' => $this->escola->id, 'email_approved' => true]);

        $this->assertEqualsCanonicalizing([$pedidoEducacao->id, $pedidoObras->id], $this->service->queryTabela($educacaoUser)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$pedidoEducacao->id, $pedidoObras->id], $this->service->queryTabela($globalUser)->pluck('id')->all());
        $this->assertSame([$pedidoEducacao->id], $this->service->queryTabela($escolaUser)->pluck('id')->all());
    }

    public function test_usuario_vinculado_a_escola_tem_escopo_de_escola_prioritario_ao_setor_geral(): void
    {
        $pedidoDaEscola = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);

        $outraEscola = Escola::create([
            'codigo' => '002',
            'nome' => 'Outra Escola',
            'setor_id' => $this->obras->id,
            'ativo' => true,
        ]);

        $pedidoOutraEscola = $this->pedido(status: 'Em Aberto', setor: $this->obras, escola: $outraEscola);

        foreach (['Listar Pedidos', 'Editar Pedidos', 'Listar Todos os Pedidos'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $usuarioEscolaComSetorGeral = User::factory()->create([
            'id_escola' => $this->escola->id,
            'setor_id' => $this->educacao->id,
            'email_approved' => true,
        ]);
        $usuarioEscolaComSetorGeral->givePermissionTo(['Listar Pedidos', 'Editar Pedidos']);

        $usuarioGlobalComEscola = User::factory()->create([
            'id_escola' => $this->escola->id,
            'setor_id' => $this->educacao->id,
            'email_approved' => true,
        ]);
        $usuarioGlobalComEscola->givePermissionTo(['Listar Pedidos', 'Editar Pedidos', 'Listar Todos os Pedidos']);

        $this->assertSame([$pedidoDaEscola->id], $this->service->queryTabela($usuarioEscolaComSetorGeral)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$pedidoDaEscola->id, $pedidoOutraEscola->id],
            $this->service->queryTabela($usuarioGlobalComEscola)->pluck('id')->all()
        );

        $this->assertTrue($usuarioEscolaComSetorGeral->can('view', $pedidoDaEscola));
        $this->assertTrue($usuarioEscolaComSetorGeral->can('update', $pedidoDaEscola));
        $this->assertFalse($usuarioEscolaComSetorGeral->can('view', $pedidoOutraEscola));
        $this->assertFalse($usuarioEscolaComSetorGeral->can('update', $pedidoOutraEscola));
        $this->assertTrue($this->service->podeGerenciarRegistro($pedidoDaEscola, $usuarioEscolaComSetorGeral));
        $this->assertFalse($this->service->podeGerenciarRegistro($pedidoOutraEscola, $usuarioEscolaComSetorGeral));

        $relatorio = new class(app(RelatorioPdfRenderer::class), $this->service) extends PedidoRelatorioGeralService {
            public function idsVisiveis(array $filtros, User $usuario): array
            {
                return $this->queryBase($filtros, $usuario)
                    ->select('p.id')
                    ->orderBy('p.id')
                    ->get()
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
            }
        };

        $this->assertSame([$pedidoDaEscola->id], $relatorio->idsVisiveis([], $usuarioEscolaComSetorGeral));
        $this->assertSame([], $relatorio->idsVisiveis(['escola_id' => $outraEscola->id], $usuarioEscolaComSetorGeral));
        $this->assertEqualsCanonicalizing(
            [$pedidoDaEscola->id, $pedidoOutraEscola->id],
            $relatorio->idsVisiveis([], $usuarioGlobalComEscola)
        );
    }

    public function test_encaminhar_educacao_para_obras_deixa_status_atual_em_aberto_em_obras(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Editar Pedidos',
            'Encaminhar Pedidos para Setor',
        ]);

        $pedido = $this->pedido(status: 'Em Análise', setor: $this->educacao, escola: $this->escola);

        $this->service->encaminharParaSetor($pedido, $this->obras, $usuario, 'Enviar para Obras.');

        $pedido->refresh();

        $this->assertSame('Em Aberto', $pedido->tipoStatus->nome);
        $this->assertTrue($pedido->setor->is($this->obras));

        $historicos = $pedido->historicos()->with('statusNovo')->oldest()->get();

        $this->assertSame('Encaminhado ao Setor', $historicos[0]->statusNovo->nome);
        $this->assertSame('Em Aberto', $historicos[1]->statusNovo->nome);
    }

    public function test_enviar_para_empresa_e_empresa_responsavel_ficam_restritos_ao_setor_obras(): void
    {
        $educacaoUser = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, ['Enviar Pedidos para Empresa']);
        $obrasUser = $this->usuarioComRoleSetor('Manutenção: Obras', $this->obras, ['Enviar Pedidos para Empresa']);

        EmpresaContratada::create([
            'nome' => 'Empresa Obras',
            'cnpj' => '12.345.678/0001-90',
            'setor_id' => $this->obras->id,
            'ativo' => true,
        ]);

        $this->assertTrue($this->service->podeEnviarParaEmpresa($educacaoUser));
        $this->assertTrue($this->service->podeEnviarParaEmpresa($obrasUser));
        $this->assertSame(['Empresa Obras'], EmpresaContratada::query()->doSetorDoUsuario($educacaoUser)->pluck('nome')->all());
        $this->assertSame(['Empresa Obras'], EmpresaContratada::query()->doSetorDoUsuario($obrasUser)->pluck('nome')->all());
    }

    public function test_enviar_para_empresa_atualiza_status_empresa_responsavel_e_historico(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutencao: Educacao Empresa', $this->educacao, [
            'Editar Pedidos',
            'Enviar Pedidos para Empresa',
        ]);

        $empresa = $this->empresa('Empresa Obras', $this->obras);
        $pedido = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);
        $setorOriginalId = $pedido->setor_id;

        $this->assertTrue($this->service->enviarParaEmpresa($pedido, $empresa, $usuario));

        $pedido->refresh();

        $this->assertSame('Enviado para Empresa', $pedido->tipoStatus->nome);
        $this->assertSame($empresa->id, $pedido->empresa_contratada_id);
        $this->assertSame($usuario->id, $pedido->responsavel_id);
        $this->assertSame($setorOriginalId, $pedido->setor_id);

        $historico = $pedido->historicos()->with('statusNovo')->orderByDesc('id')->firstOrFail();

        $this->assertSame('Enviado para Empresa', $historico->statusNovo->nome);
        $this->assertSame("Pedido enviado para a empresa {$empresa->nome}.", $historico->descricao_alteracao);
    }

    public function test_enviar_para_empresa_bloqueia_empresa_fora_do_escopo_do_usuario(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutencao: Obras Empresa', $this->obras, [
            'Editar Pedidos',
            'Enviar Pedidos para Empresa',
        ]);

        $empresaEducacao = $this->empresa('Empresa Educacao', $this->educacao);
        $pedido = $this->pedido(status: 'Em Aberto', setor: $this->obras, escola: $this->escola);

        $this->expectException(ValidationException::class);

        $this->service->enviarParaEmpresa($pedido, $empresaEducacao, $usuario);
    }

    public function test_bulk_action_enviar_para_empresa_aparece_somente_com_permissao(): void
    {
        $usuarioComPermissao = $this->usuarioComRoleSetor('Manutencao: Educacao Envia Empresa', $this->educacao, [
            'Listar Pedidos',
            'Editar Pedidos',
            'Enviar Pedidos para Empresa',
        ]);

        $usuarioSemPermissao = $this->usuarioComRoleSetor('Manutencao: Educacao Sem Empresa', $this->educacao, [
            'Listar Pedidos',
            'Editar Pedidos',
        ]);

        Livewire::actingAs($usuarioComPermissao)
            ->test(ListPedidos::class)
            ->assertTableBulkActionVisible('enviar_para_empresa');

        Livewire::actingAs($usuarioSemPermissao)
            ->test(ListPedidos::class)
            ->assertTableBulkActionHidden('enviar_para_empresa');
    }

    public function test_bulk_action_enviar_para_empresa_atualiza_multiplos_pedidos_e_ignora_nao_gerenciaveis(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutencao: Educacao Bulk Empresa', $this->educacao, [
            'Listar Pedidos',
            'Editar Pedidos',
            'Enviar Pedidos para Empresa',
        ]);

        $empresa = $this->empresa('Empresa Bulk Obras', $this->obras);
        $pedidoA = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);
        $pedidoB = $this->pedido(status: 'Em Aberto', setor: $this->obras, escola: $this->escola);
        $pedidoCancelado = $this->pedido(status: 'Cancelado', setor: $this->educacao, escola: $this->escola);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->mountTableBulkAction('enviar_para_empresa', [$pedidoA, $pedidoB, $pedidoCancelado])
            ->setTableBulkActionData([
                'empresa_contratada_id' => $empresa->id,
            ])
            ->callMountedTableBulkAction()
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('Enviado para Empresa', $pedidoA->refresh()->tipoStatus->nome);
        $this->assertSame($empresa->id, $pedidoA->empresa_contratada_id);
        $this->assertSame('Enviado para Empresa', $pedidoB->refresh()->tipoStatus->nome);
        $this->assertSame($empresa->id, $pedidoB->empresa_contratada_id);
        $this->assertSame('Cancelado', $pedidoCancelado->refresh()->tipoStatus->nome);
        $this->assertNull($pedidoCancelado->empresa_contratada_id);
    }

    public function test_pedido_adicional_e_feedback_por_problema_nao_reabrem_por_nota_um_sem_botao(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Avaliar Pedidos',
            'Vincular Pedidos Adicionais',
        ]);

        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $pedido->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);

        $adicional = $this->service->criarPedidosAdicionais($pedido, [[
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoDisjuntor->id],
            'data_identificacao_problema' => '2026-05-02',
            'descricao_pedido' => 'Disjuntor trocado durante a visita.',
            'arquivos' => $this->fotosAdicional(),
        ]], $usuario)->first();

        $feedback = $this->service->avaliarPedido($pedido, [
            'avaliacoes' => [
                $pedido->problemas()->first()->id => [
                    'valor' => 1,
                    'resultado' => ResultadoFeedbackPedido::NaoAtendido->value,
                    'comentario' => 'Ainda sem luz.',
                ],
                $adicional->problemas()->first()->id => [
                    'valor' => 5,
                    'resultado' => ResultadoFeedbackPedido::Atendido->value,
                    'comentario' => 'Adicional atendido.',
                ],
            ],
            'reabrir_pedido' => false,
            'descricao' => 'Avaliação geral.',
        ], $usuario);

        $pedido->refresh();
        $this->assertSame('Concluído', $pedido->tipoStatus->nome);
        $this->assertNotNull($pedido->data_entrega);
        $this->assertNotNull($adicional);
        $this->assertTrue((bool) $adicional->is_pedido_adicional);
        $this->assertSame('Pedido Adicional', $adicional->tipoStatus->nome);
        $this->assertSame('Disjuntor queimado', $adicional->problemas()->first()->texto_problema);
        $this->assertCount(2, $feedback->itens()->get());
    }

    public function test_cria_status_de_pedido_adicional_quando_base_antiga_nao_possui_o_status(): void
    {
        TipoStatus::query()->where('nome', 'Pedido Adicional')->delete();

        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Vincular Pedidos Adicionais',
        ]);

        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);

        $criados = $this->service->criarPedidosAdicionais($pedido, [[
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoDisjuntor->id],
            'data_identificacao_problema' => '2026-05-02',
            'descricao_pedido' => 'Disjuntor trocado durante a visita.',
            'arquivos' => $this->fotosAdicional(),
        ]], $usuario);

        $this->assertCount(1, $criados);
        $this->assertDatabaseHas('tipo_status', [
            'nome' => 'Pedido Adicional',
            'ativo' => true,
        ]);
        $this->assertSame('Pedido Adicional', $criados->first()->tipoStatus->nome);
    }

    public function test_botao_reabrir_pedido_define_status_reaberto_mesmo_com_nota_alta(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, ['Avaliar Pedidos']);
        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);

        $pedido->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);

        $this->service->avaliarPedido($pedido, [
            'avaliacoes' => [
                $pedido->problemas()->first()->id => [
                    'valor' => 5,
                    'resultado' => ResultadoFeedbackPedido::ParcialmenteAtendido->value,
                    'comentario' => 'Servico precisa voltar para ajuste.',
                ],
            ],
            'reabrir_pedido' => true,
            'descricao' => 'Pedido deve ser reaberto para nova execucao.',
        ], $usuario);

        $this->assertSame('Reaberto', $pedido->refresh()->tipoStatus->nome);
        $this->assertDatabaseMissing('feedback_pedido_itens', [
            'pedido_id' => $pedido->id,
        ]);
    }

    public function test_vincular_adicionais_na_tabela_cria_registros_sem_redirecionar(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Listar Pedidos',
            'Editar Pedidos',
            'Vincular Pedidos Adicionais',
        ]);

        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);

        $itemKey = null;

        $component = Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->assertTableActionVisible('vincular_adicionais', $pedido)
            ->mountTableAction('vincular_adicionais', $pedido)
            ->assertSchemaStateSet(function (array $state) use (&$itemKey): array {
                $itemKey = array_key_first($state['pedidos_adicionais'] ?? []);

                return [];
            });

        $this->assertNotNull($itemKey);

        $component
            ->setTableActionData([
                'pedidos_adicionais' => [
                    $itemKey => [
                        'tipo_manutencao_id' => $this->tipo->id,
                        'tipo_manutencao_opcao_ids' => [$this->opcaoDisjuntor->id],
                        'data_identificacao_problema' => '2026-05-02',
                        'descricao_pedido' => 'Disjuntor trocado durante a visita.',
                        'arquivos' => $this->fotosAdicional(),
                    ],
                ],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertNoRedirect();

        $this->assertDatabaseHas('pedidos', [
            'pedido_principal_id' => $pedido->id,
            'is_pedido_adicional' => true,
            'descricao_pedido' => 'Disjuntor trocado durante a visita.',
        ]);
    }

    public function test_avaliar_pedido_com_adicional_na_tabela_conclui_sem_redirecionar(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Listar Pedidos',
            'Avaliar Pedidos',
            'Vincular Pedidos Adicionais',
        ]);

        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $problema = $pedido->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);
        $adicional = $this->service->criarPedidosAdicionais($pedido, [[
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoDisjuntor->id],
            'data_identificacao_problema' => '2026-05-02',
            'descricao_pedido' => 'Disjuntor trocado durante a visita.',
            'arquivos' => $this->fotosAdicional(),
        ]], $usuario)->first();

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->assertTableActionVisible('finalizar', $pedido)
            ->callTableAction('finalizar', $pedido, [
                'avaliacoes' => [
                    $problema->id => [
                        'valor' => 5,
                        'resultado' => ResultadoFeedbackPedido::Atendido->value,
                        'comentario' => 'Problema atendido.',
                    ],
                    $adicional->problemas()->first()->id => [
                        'valor' => 5,
                        'resultado' => ResultadoFeedbackPedido::Atendido->value,
                        'comentario' => 'Adicional atendido.',
                    ],
                ],
                'reabrir_pedido' => false,
                'descricao' => 'Serviço atendido.',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNoRedirect();

        $this->assertSame('Concluído', $pedido->refresh()->tipoStatus->nome);
    }

    public function test_avaliacao_de_pedido_salva_fotos_de_conclusao_no_storage_publico(): void
    {
        Storage::fake('public');

        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Listar Pedidos',
            'Avaliar Pedidos',
        ]);

        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $problema = $pedido->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->callTableAction('finalizar', $pedido, [
                'avaliacoes' => [
                    $problema->id => [
                        'valor' => 5,
                        'resultado' => ResultadoFeedbackPedido::Atendido->value,
                        'comentario' => 'Problema atendido.',
                    ],
                ],
                'reabrir_pedido' => false,
                'descricao' => 'Servico atendido com foto.',
                'fotos_conclusao' => [UploadedFile::fake()->image('conclusao.jpg')],
            ])
            ->assertHasNoTableActionErrors()
            ->assertNoRedirect();

        $arquivo = $pedido->refresh()->fotosConclusao()->firstOrFail();

        $this->assertStringStartsWith('pedidos/conclusao/', $arquivo->caminho);
        $this->assertSame(basename($arquivo->caminho), $arquivo->nome_original);
        Storage::disk('public')->assertExists($arquivo->caminho);
    }

    public function test_avaliacao_exige_descricao_geral_e_comentario_por_problema(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, ['Avaliar Pedidos']);
        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $problema = $pedido->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->avaliarPedido($pedido, [
            'avaliacoes' => [
                $problema->id => [
                    'valor' => 5,
                    'resultado' => ResultadoFeedbackPedido::Atendido->value,
                ],
            ],
            'reabrir_pedido' => false,
        ], $usuario);
    }

    public function test_pedido_adicional_exige_descricao(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Vincular Pedidos Adicionais',
        ]);

        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);

        $this->expectException(ValidationException::class);

        $this->service->criarPedidosAdicionais($pedido, [[
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoDisjuntor->id],
            'data_identificacao_problema' => '2026-05-02',
            'descricao_pedido' => '',
        ]], $usuario);
    }

    public function test_pedido_adicional_exige_ao_menos_uma_foto(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Vincular Pedidos Adicionais',
        ]);

        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);

        $this->expectException(ValidationException::class);

        $this->service->criarPedidosAdicionais($pedido, [[
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoDisjuntor->id],
            'data_identificacao_problema' => '2026-05-02',
            'descricao_pedido' => 'Disjuntor trocado durante a visita.',
            'arquivos' => [],
        ]], $usuario);
    }

    public function test_relation_manager_de_adicionais_aparece_somente_no_pedido_principal(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, ['Editar Pedidos']);
        $this->actingAs($usuario);

        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $adicional = $this->service->criarPedidosAdicionais($pedido, [[
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoDisjuntor->id],
            'data_identificacao_problema' => '2026-05-02',
            'descricao_pedido' => 'Disjuntor trocado durante a visita.',
            'arquivos' => $this->fotosAdicional(),
        ]], $usuario)->first();

        $this->assertContains(PedidosAdicionaisRelationManager::class, PedidoResource::getRelations());
        $this->assertTrue(PedidosAdicionaisRelationManager::canViewForRecord($pedido, EditPedido::class));
        $this->assertFalse(PedidosAdicionaisRelationManager::canViewForRecord($adicional, EditPedido::class));
    }

    private function seedStatus(): void
    {
        foreach ([
            ['Reaberto', '#d40000', false, false, true],
            ['Em Aberto', '#3b82f6', false, false, true],
            ['Em Análise', '#f59e0b', false, false, true],
            ['Encaminhado ao Setor', '#8b5cf6', false, false, true],
            ['Em Andamento', '#e20ee9', false, false, false],
            ['Enviado para Empresa', '#6366f1', false, false, true],
            ['Em Manutenção', '#f97316', false, false, true],
            ['Concluído', '#10b981', true, false, true],
            ['Cancelado', '#d40000', false, true, true],
            ['Pedido Adicional', '#64748b', false, false, true],
        ] as [$nome, $cor, $finaliza, $cancela, $ativo]) {
            TipoStatus::create([
                'nome' => $nome,
                'cor' => $cor,
                'finaliza_pedido' => $finaliza,
                'cancela_pedido' => $cancela,
                'ativo' => $ativo,
            ]);
        }
    }

    private function pedido(string $status, Setor $setor, Escola $escola): Pedido
    {
        return Pedido::create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_status_id' => $this->service->statusPorNome($status, true)->id,
            'descricao_pedido' => 'Pedido de teste',
            'nome_solicitante' => 'Solicitante',
            'nivel_prioridade' => NivelEmergenciaPedido::INDEFINIDO,
            'escola_id' => $escola->id,
            'solicitante_id' => User::factory()->create(['id_escola' => $escola->id])->id,
            'setor_id' => $setor->id,
            'setor_origem_id' => $escola->setor_id,
            'data_solicitacao' => now(),
            'data_identificacao_problema' => now(),
            'ativo' => true,
        ]);
    }

    private function fotosAdicional(): array
    {
        return ['pedidos/adicionais/foto-adicional.jpg'];
    }

    private function empresa(string $nome, Setor $setor): EmpresaContratada
    {
        $sequencia = EmpresaContratada::query()->count() + 1;

        return EmpresaContratada::create([
            'nome' => $nome,
            'cnpj' => sprintf('12.345.678/%04d-%02d', $sequencia, $sequencia),
            'setor_id' => $setor->id,
            'ativo' => true,
        ]);
    }

    private function usuarioComRoleSetor(string $roleName, Setor $setor, array $permissions): User
    {
        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
            'setor_id' => $setor->id,
        ]);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role->syncPermissions($permissions);

        $user = User::factory()->create(['email_approved' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function usuarioComPermissoes(array $permissions): User
    {
        $user = User::factory()->create(['email_approved' => true]);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }
}
