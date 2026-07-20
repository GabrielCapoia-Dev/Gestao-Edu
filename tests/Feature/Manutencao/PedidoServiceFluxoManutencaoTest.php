<?php

namespace Tests\Feature\Manutencao;

use App\Filament\Admin\Resources\Pedidos\Pages\EditPedido;
use App\Filament\Admin\Resources\Pedidos\Pages\ListPedidos;
use App\Filament\Admin\Resources\Pedidos\PedidoResource;
use App\Filament\Admin\Resources\Pedidos\RelationManagers\PedidosAdicionaisRelationManager;
use App\Models\EmpresaContratada;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\ResultadoFeedbackPedido;
use App\Models\Enums\TipoArquivoPedido;
use App\Models\Escola;
use App\Models\Pedido;
use App\Models\PedidoArquivo;
use App\Models\Role;
use App\Models\Setor;
use App\Models\SetorAcesso;
use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Notifications\SistemaNotification;
use App\Services\PedidoService;
use App\Services\ProfilePreviewService;
use App\Services\Relatorios\PedidoRelatorioGeralService;
use App\Services\Relatorios\RelatorioPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
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

        SetorAcesso::create([
            'setor_origem_id' => $this->educacao->id,
            'setor_alvo_id' => $this->obras->id,
            'pode_listar' => true,
            'pode_editar' => true,
            'pode_cancelar' => true,
            'pode_encaminhar' => true,
        ]);

        SetorAcesso::create([
            'setor_origem_id' => $escolaSetor->id,
            'setor_alvo_id' => $this->educacao->id,
            'pode_listar' => true,
            'pode_editar' => true,
            'pode_cancelar' => true,
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

    public function test_cria_pedido_com_escola_vinculada_mesmo_sem_id_escola_legado(): void
    {
        $usuario = User::factory()->create([
            'id_escola' => null,
            'email_approved' => true,
        ]);
        $usuario->escolas()->attach($this->escola->id);

        $pedido = $this->service->criarPedido([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoLuz->id],
            'data_identificacao_problema' => '2026-05-01',
            'descricao_pedido' => 'Pedido aberto por usuario vinculado a escola.',
            'nome_solicitante' => 'Direcao',
        ], $usuario);

        $this->assertSame($this->escola->id, $pedido->escola_id);
        $this->assertSame($this->escola->setor_id, $pedido->setor_origem_id);
    }

    public function test_listagem_exibe_escola_do_solicitante_para_pedido_antigo_sem_escola_id(): void
    {
        $solicitante = User::factory()->create([
            'id_escola' => $this->escola->id,
            'email_approved' => true,
        ]);

        $pedido = Pedido::create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_status_id' => $this->service->statusPorNome('Em Aberto', true)->id,
            'descricao_pedido' => 'Pedido antigo sem escola_id.',
            'nome_solicitante' => 'Solicitante',
            'nivel_prioridade' => NivelEmergenciaPedido::INDEFINIDO,
            'escola_id' => null,
            'solicitante_id' => $solicitante->id,
            'setor_id' => $this->educacao->id,
            'setor_origem_id' => $this->escola->setor_id,
            'data_solicitacao' => now(),
            'data_identificacao_problema' => now(),
            'ativo' => true,
        ]);

        $usuario = $this->usuarioComPermissoes(['Listar Pedidos', 'Listar Todos os Pedidos']);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->assertSee($pedido->numero_protocolo)
            ->assertSee($this->escola->nome);
    }

    public function test_listagem_diferencia_pedido_encaminhado_do_aberto(): void
    {
        $pedidoAberto = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);
        $pedidoEncaminhado = $this->pedido(status: 'Encaminhado ao Setor', setor: $this->obras, escola: $this->escola);
        $usuario = $this->usuarioComPermissoes(['Listar Pedidos', 'Listar Todos os Pedidos']);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->assertSee($pedidoAberto->numero_protocolo)
            ->assertSee($pedidoEncaminhado->numero_protocolo)
            ->assertSee('Em Aberto')
            ->assertSee('Encaminhado ao Setor - '.$pedidoEncaminhado->setor->nome_completo)
            ->assertDontSee('Em Aberto - '.$pedidoEncaminhado->setor->nome_completo);
    }

    public function test_edicao_de_pedido_renderiza_cabecalho_com_problemas_segmentados(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutencao: Educacao', $this->educacao, [
            'Listar Pedidos',
            'Editar Pedidos',
        ]);
        $pedido = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);
        $pedido->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);

        Livewire::actingAs($usuario)
            ->test(EditPedido::class, ['record' => $pedido->getKey()])
            ->assertSee($pedido->numero_protocolo)
            ->assertSee($this->opcaoLuz->texto);
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

    public function test_criacao_rejeita_arquivos_que_nao_sao_imagens_permitidas(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create([
            'id_escola' => $this->escola->id,
            'email_approved' => true,
        ]);

        $arquivosInvalidos = [
            UploadedFile::fake()->create('fotos.zip', 10, 'application/zip'),
            UploadedFile::fake()->create('foto.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('programa.exe', 10, 'application/octet-stream'),
            UploadedFile::fake()->createWithContent('arquivo-renomeado.jpg', "PK\x03\x04conteudo-zip"),
        ];

        foreach ($arquivosInvalidos as $arquivoInvalido) {
            try {
                $this->service->criarPedido([
                    'tipo_manutencao_id' => $this->tipo->id,
                    'tipo_manutencao_opcao_ids' => [$this->opcaoLuz->id],
                    'data_identificacao_problema' => '2026-05-01',
                    'descricao_pedido' => 'Tentativa de envio de arquivo invalido.',
                    'nome_solicitante' => 'Direcao',
                    'arquivos' => [$arquivoInvalido],
                ], $usuario);

                $this->fail('O arquivo invalido deveria ter sido rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    'Envie uma imagem JPEG, PNG ou WEBP.',
                    $exception->errors()['arquivos'][0] ?? null
                );
            }
        }

        $this->assertDatabaseCount('pedidos', 0);
        $this->assertDatabaseCount('pedido_arquivos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_criacao_remove_do_storage_arquivo_invalido_ja_persistido(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pedidos/arquivo-renomeado.jpg', "PK\x03\x04conteudo-docx");

        $usuario = User::factory()->create([
            'id_escola' => $this->escola->id,
            'email_approved' => true,
        ]);

        try {
            $this->service->criarPedido([
                'tipo_manutencao_id' => $this->tipo->id,
                'tipo_manutencao_opcao_ids' => [$this->opcaoLuz->id],
                'data_identificacao_problema' => '2026-05-01',
                'descricao_pedido' => 'Tentativa com arquivo persistido invalido.',
                'nome_solicitante' => 'Direcao',
                'arquivos' => ['pedidos/arquivo-renomeado.jpg'],
            ], $usuario);

            $this->fail('O arquivo invalido deveria ter sido rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('arquivos', $exception->errors());
        }

        Storage::disk('public')->assertMissing('pedidos/arquivo-renomeado.jpg');
        $this->assertDatabaseCount('pedidos', 0);
        $this->assertDatabaseCount('pedido_arquivos', 0);
    }

    public function test_laudo_e_orcamento_continuam_aceitando_documentos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pedidos/laudo.pdf', '%PDF-1.4 teste');
        Storage::disk('public')->put('pedidos/orcamento.docx', "PK\x03\x04documento");

        $usuario = User::factory()->create();
        $pedido = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);

        foreach ([
            [TipoArquivoPedido::LAUDO, 'pedidos/laudo.pdf'],
            [TipoArquivoPedido::ORCAMENTO, 'pedidos/orcamento.docx'],
        ] as [$tipo, $caminho]) {
            PedidoArquivo::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $usuario->id,
                'tipo_arquivo' => $tipo,
                'caminho' => $caminho,
            ]);
        }

        $this->assertDatabaseCount('pedido_arquivos', 2);
        Storage::disk('public')->assertExists('pedidos/laudo.pdf');
        Storage::disk('public')->assertExists('pedidos/orcamento.docx');
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

        $relatorio = new class(app(RelatorioPdfRenderer::class), $this->service) extends PedidoRelatorioGeralService
        {
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

    public function test_encaminhar_educacao_para_obras_deixa_status_atual_encaminhado_ao_setor(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Editar Pedidos',
            'Encaminhar Pedidos para Setor',
        ]);

        $pedido = $this->pedido(status: 'Em Análise', setor: $this->educacao, escola: $this->escola);

        $this->service->encaminharParaSetor($pedido, $this->obras, $usuario, 'Enviar para Obras.');

        $pedido->refresh();

        $this->assertSame('Encaminhado ao Setor', $pedido->tipoStatus->nome);
        $this->assertTrue($pedido->setor->is($this->obras));

        $historicos = $pedido->historicos()->with('statusNovo')->oldest()->get();

        $this->assertCount(1, $historicos);
        $this->assertSame('Encaminhado ao Setor', $historicos[0]->statusNovo->nome);
        $this->assertSame('Enviar para Obras.', $historicos[0]->descricao_alteracao);
    }

    public function test_gerenciar_pedido_encaminhado_move_para_em_analise_no_setor_destino(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Obras', $this->obras, [
            'Editar Pedidos',
        ]);

        $pedido = $this->pedido(status: 'Encaminhado ao Setor', setor: $this->obras, escola: $this->escola);

        $this->service->assumirPedido($pedido, $usuario);

        $pedido->refresh();

        $this->assertSame('Em Análise', $pedido->tipoStatus->nome);
        $this->assertSame($usuario->id, $pedido->responsavel_id);

        $historico = $pedido->historicos()->with('statusNovo')->latest('id')->firstOrFail();

        $this->assertSame('Em Análise', $historico->statusNovo->nome);
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

    public function test_bulk_action_alterar_status_aparece_somente_com_permissao_de_edicao(): void
    {
        $usuarioComPermissao = $this->usuarioComRoleSetor('Manutencao: Educacao Altera Status', $this->educacao, [
            'Listar Pedidos',
            'Editar Pedidos',
        ]);

        $usuarioSemPermissao = $this->usuarioComRoleSetor('Manutencao: Educacao Sem Alterar Status', $this->educacao, [
            'Listar Pedidos',
        ]);

        Livewire::actingAs($usuarioComPermissao)
            ->test(ListPedidos::class)
            ->assertTableBulkActionVisible('alterar_status');

        Livewire::actingAs($usuarioSemPermissao)
            ->test(ListPedidos::class)
            ->assertTableBulkActionHidden('alterar_status');
    }

    public function test_bulk_action_alterar_status_atualiza_multiplos_pedidos_e_registra_historico(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutencao: Educacao Bulk Status', $this->educacao, [
            'Listar Pedidos',
            'Editar Pedidos',
        ]);

        $novoStatus = $this->service->statusPorNome('Em Manutenção', true);
        $pedidoA = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);
        $pedidoB = $this->pedido(status: 'Em Análise', setor: $this->obras, escola: $this->escola);
        $pedidoCancelado = $this->pedido(status: 'Cancelado', setor: $this->educacao, escola: $this->escola);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->mountTableBulkAction('alterar_status', [$pedidoA, $pedidoB, $pedidoCancelado])
            ->setTableBulkActionData([
                'tipo_status_id' => $novoStatus->id,
                'descricao' => 'Ajuste operacional em lote.',
            ])
            ->callMountedTableBulkAction()
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('Em Manutenção', $pedidoA->refresh()->tipoStatus->nome);
        $this->assertSame('Em Manutenção', $pedidoB->refresh()->tipoStatus->nome);
        $this->assertSame('Cancelado', $pedidoCancelado->refresh()->tipoStatus->nome);

        foreach ([$pedidoA, $pedidoB] as $pedido) {
            $historico = $pedido->historicos()->with(['statusNovo', 'usuario'])->latest('id')->firstOrFail();

            $this->assertSame($novoStatus->id, $historico->status_novo_id);
            $this->assertSame($usuario->id, $historico->usuario_id);
            $this->assertStringContainsString(
                "Usuario {$usuario->name} alterou o status do pedido para {$novoStatus->nome}.",
                $historico->descricao_alteracao
            );
            $this->assertStringContainsString('Ajuste operacional em lote.', $historico->descricao_alteracao);
        }

        $this->assertSame(0, $pedidoCancelado->historicos()->count());
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

    public function test_notifica_somente_usuarios_com_permissao_quando_pedido_adicional_e_criado(): void
    {
        Notification::fake();

        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Vincular Pedidos Adicionais',
        ]);
        $destinatario = $this->usuarioComPermissoes([
            PedidoService::PERMISSAO_NOTIFICAR_PEDIDO_ADICIONAL_CRIADO,
        ]);
        $usuarioSemPermissao = User::factory()->create();
        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);

        $adicional = $this->service->criarPedidosAdicionais($pedido, [[
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoDisjuntor->id],
            'data_identificacao_problema' => '2026-05-02',
            'descricao_pedido' => 'Disjuntor trocado durante a visita.',
            'arquivos' => $this->fotosAdicional(),
        ]], $usuario)->firstOrFail();

        Notification::assertSentTo(
            $destinatario,
            SistemaNotification::class,
            fn (SistemaNotification $notification): bool => $notification->titulo === 'Pedido adicional criado'
                && $notification->mensagem === "Pedido adicional {$adicional->numero_protocolo} foi criado."
        );
        Notification::assertNotSentTo($usuarioSemPermissao, SistemaNotification::class);
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

    public function test_usuario_da_escola_pode_adicionar_e_avaliar_pedido_em_manutencao_em_outro_setor(): void
    {
        $usuario = $this->usuarioComPermissoes([
            'Listar Pedidos',
            'Avaliar Pedidos',
            'Vincular Pedidos Adicionais',
        ]);
        $usuario->forceFill([
            'id_escola' => $this->escola->id,
            'setor_id' => $this->escola->setor_id,
        ])->save();
        $usuario->escolas()->attach($this->escola->id);

        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->obras, escola: $this->escola);
        $problema = $pedido->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);

        $this->assertFalse($this->service->podeGerenciarRegistro($pedido, $usuario));
        $this->assertTrue($this->service->podeVincularAdicionaisAoPedido($pedido, $usuario));
        $this->assertTrue($this->service->podeAvaliarRegistro($pedido, $usuario));

        $outraEscola = Escola::create([
            'codigo' => '002',
            'nome' => 'Outra Escola',
            'setor_id' => $this->escola->setor_id,
            'ativo' => true,
        ]);
        $usuarioOutraEscola = $this->usuarioComPermissoes([
            'Avaliar Pedidos',
            'Vincular Pedidos Adicionais',
        ]);
        $usuarioOutraEscola->forceFill([
            'id_escola' => $outraEscola->id,
            'setor_id' => $outraEscola->setor_id,
        ])->save();
        $usuarioOutraEscola->escolas()->attach($outraEscola->id);

        $this->assertFalse($this->service->podeVincularAdicionaisAoPedido($pedido, $usuarioOutraEscola));
        $this->assertFalse($this->service->podeAvaliarRegistro($pedido, $usuarioOutraEscola));

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->assertTableActionHidden('gerenciar', $pedido)
            ->assertTableActionVisible('vincular_adicionais', $pedido)
            ->assertTableActionVisible('finalizar', $pedido);

        $adicional = $this->service->criarPedidosAdicionais($pedido, [[
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_ids' => [$this->opcaoDisjuntor->id],
            'data_identificacao_problema' => '2026-06-11',
            'descricao_pedido' => 'Problema adicional informado pela escola.',
            'arquivos' => $this->fotosAdicional(),
        ]], $usuario)->firstOrFail();

        $this->service->avaliarPedido($pedido, [
            'avaliacoes' => [
                $problema->id => [
                    'valor' => 5,
                    'resultado' => ResultadoFeedbackPedido::Atendido->value,
                    'comentario' => 'Problema principal atendido.',
                ],
                $adicional->problemas()->firstOrFail()->id => [
                    'valor' => 4,
                    'resultado' => ResultadoFeedbackPedido::Atendido->value,
                    'comentario' => 'Problema adicional atendido.',
                ],
            ],
            'reabrir_pedido' => false,
            'descricao' => 'Atendimento avaliado pela escola solicitante.',
        ], $usuario);

        $this->assertSame('Concluído', $pedido->refresh()->tipoStatus->nome);
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

    public function test_listagem_exibe_feedback_sem_misturar_filtros_da_tela_de_feedback(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Feedback', $this->educacao, [
            'Listar Pedidos',
            'Listar Todos os Pedidos',
            'Visualizar Pedidos por Status',
            'Visualizar Feedback de Pedidos',
            'Avaliar Pedidos',
        ]);

        $pedidoAvaliado = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $problema = $pedidoAvaliado->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);

        $pedidoSemAvaliacao = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);

        $this->service->avaliarPedido($pedidoAvaliado, [
            'avaliacoes' => [
                $problema->id => [
                    'valor' => 5,
                    'resultado' => ResultadoFeedbackPedido::Atendido->value,
                    'comentario' => 'Resolvido por problema.',
                ],
            ],
            'reabrir_pedido' => false,
            'descricao' => 'Atendimento aprovado pela escola.',
        ], $usuario);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->set('activeTab', 'todos')
            ->assertTableActionVisible('visualizar_feedback', $pedidoAvaliado)
            ->assertTableActionHidden('visualizar_feedback', $pedidoSemAvaliacao)
            ->assertDontSee('Avaliado de')
            ->assertDontSee('Avaliado até')
            ->callTableAction('visualizar_feedback', $pedidoAvaliado)
            ->assertHasNoTableActionErrors();

        $html = view('components.pedido.feedback', [
            'pedido' => $pedidoAvaliado->refresh()->load(['tipoManutencao', 'tipoStatus', 'escola', 'empresaContratada']),
            'feedback' => $pedidoAvaliado->ultimoFeedback()->with(['itens.problema', 'itens.pedido.tipoManutencao'])->first(),
        ])->render();

        $this->assertStringContainsString('Atendimento aprovado pela escola.', $html);
        $this->assertStringContainsString('Resolvido por problema.', $html);
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

    public function test_avaliacao_rejeita_documento_como_foto_sem_criar_feedback_ou_concluir_pedido(): void
    {
        Storage::fake('public');

        $usuario = $this->usuarioComRoleSetor('Teste Avaliacao Upload', $this->educacao, [
            'Avaliar Pedidos',
        ]);
        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $statusOriginalId = $pedido->tipo_status_id;
        $problema = $pedido->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);

        try {
            $this->service->avaliarPedido($pedido, [
                'avaliacoes' => [
                    $problema->id => [
                        'valor' => 5,
                        'resultado' => ResultadoFeedbackPedido::Atendido->value,
                        'comentario' => 'Problema atendido.',
                    ],
                ],
                'reabrir_pedido' => false,
                'descricao' => 'Tentativa de conclusao com documento.',
                'fotos_conclusao' => [
                    UploadedFile::fake()->create('foto.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ], $usuario);

            $this->fail('O documento deveria ter sido rejeitado como foto de conclusao.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fotos_conclusao', $exception->errors());
        }

        $this->assertSame($statusOriginalId, $pedido->refresh()->tipo_status_id);
        $this->assertDatabaseCount('feedback_pedidos', 0);
        $this->assertDatabaseCount('pedido_arquivos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_avaliacao_exige_comentario_por_problema_e_descricao_geral_somente_ao_reabrir(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, ['Avaliar Pedidos']);
        $pedido = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $problema = $pedido->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);

        try {
            $this->service->avaliarPedido($pedido, [
                'avaliacoes' => [
                    $problema->id => [
                        'comentario' => 'Problema resolvido.',
                    ],
                ],
                'reabrir_pedido' => false,
            ], $usuario);

            $this->fail('A avaliacao sem nota e resultado deveria falhar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey("avaliacoes.{$problema->id}.valor", $exception->errors());
            $this->assertArrayHasKey("avaliacoes.{$problema->id}.resultado", $exception->errors());
            $this->assertArrayNotHasKey("avaliacoes.{$problema->id}.comentario", $exception->errors());
            $this->assertArrayNotHasKey('descricao', $exception->errors());
        }

        try {
            $this->service->avaliarPedido($pedido, [
                'avaliacoes' => [
                    $problema->id => [
                        'valor' => 5,
                        'resultado' => ResultadoFeedbackPedido::Atendido->value,
                    ],
                ],
                'reabrir_pedido' => false,
            ], $usuario);

            $this->fail('A avaliacao sem comentario por problema deveria falhar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey("avaliacoes.{$problema->id}.comentario", $exception->errors());
            $this->assertArrayNotHasKey('descricao', $exception->errors());
        }

        try {
            $this->service->avaliarPedido($pedido, [
                'reabrir_pedido' => true,
            ], $usuario);

            $this->fail('A reabertura sem comentario geral deveria falhar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('descricao', $exception->errors());
        }

        $feedback = $this->service->avaliarPedido($pedido, [
            'avaliacoes' => [
                $problema->id => [
                    'valor' => 5,
                    'resultado' => ResultadoFeedbackPedido::Atendido->value,
                    'comentario' => 'Problema resolvido.',
                ],
            ],
            'reabrir_pedido' => false,
        ], $usuario);

        $this->assertNull($feedback->descricao);
        $this->assertSame('Concluído', $pedido->refresh()->tipoStatus->nome);
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

    public function test_pedido_adicional_rejeita_arquivo_renomeado_e_remove_do_storage(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pedidos/adicionais/falso.png', "PK\x03\x04conteudo-zip");

        $usuario = $this->usuarioComRoleSetor('ManutenÃ§Ã£o: EducaÃ§Ã£o', $this->educacao, [
            'Vincular Pedidos Adicionais',
        ]);
        $pedido = $this->pedido(status: 'Em ManutenÃ§Ã£o', setor: $this->educacao, escola: $this->escola);

        try {
            $this->service->criarPedidosAdicionais($pedido, [[
                'tipo_manutencao_id' => $this->tipo->id,
                'tipo_manutencao_opcao_ids' => [$this->opcaoDisjuntor->id],
                'data_identificacao_problema' => '2026-05-02',
                'descricao_pedido' => 'Arquivo falso no pedido adicional.',
                'arquivos' => ['pedidos/adicionais/falso.png'],
            ]], $usuario);

            $this->fail('O arquivo renomeado deveria ter sido rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pedidos_adicionais.0.arquivos', $exception->errors());
        }

        Storage::disk('public')->assertMissing('pedidos/adicionais/falso.png');
        $this->assertDatabaseMissing('pedidos', [
            'pedido_principal_id' => $pedido->id,
            'is_pedido_adicional' => true,
        ]);
        $this->assertDatabaseCount('pedido_arquivos', 0);
    }

    public function test_cancela_pedido_adicional_e_o_remove_da_avaliacao_do_principal(): void
    {
        Storage::fake('public');

        $usuario = $this->usuarioComRoleSetor('Manutencao: Educacao', $this->educacao, [
            'Editar Pedidos',
        ]);
        $principal = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $principal->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);
        $adicional = $this->pedidoAdicional($principal);

        $this->assertTrue($this->service->cancelarPedidoAdicional(
            $adicional,
            $usuario,
            'Servico nao sera mais necessario.'
        ));

        $adicional->refresh();

        $this->assertTrue((bool) $adicional->is_pedido_adicional);
        $this->assertTrue($adicional->pedidoPrincipal->is($principal));
        $this->assertSame('Cancelado', $adicional->tipoStatus->nome);
        $this->assertFalse(
            $this->service->problemasParaAvaliacao($principal, false)
                ->contains('pedido_id', $adicional->id)
        );
        $this->assertDatabaseHas('pedido_historicos', [
            'pedido_id' => $principal->id,
            'descricao_alteracao' => "Pedido adicional {$adicional->numero_protocolo} cancelado. Motivo: Servico nao sera mais necessario.",
        ]);
    }

    public function test_promove_pedido_adicional_a_principal_preservando_seus_dados(): void
    {
        Storage::fake('public');

        $usuario = $this->usuarioComRoleSetor('Manutencao: Educacao', $this->educacao, [
            'Editar Pedidos',
        ]);
        $principal = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $adicional = $this->pedidoAdicional($principal);
        $problemaId = $adicional->problemas()->value('id');
        $statusPromovido = $this->service->statusPorNome('Em Aberto', true);

        $this->assertTrue($this->service->promoverPedidoAdicional(
            $adicional,
            $usuario,
            $statusPromovido,
            'Retomar como pedido principal.'
        ));

        $adicional->refresh();

        $this->assertFalse((bool) $adicional->is_pedido_adicional);
        $this->assertNull($adicional->pedido_principal_id);
        $this->assertSame('Em Aberto', $adicional->tipoStatus->nome);
        $this->assertSame($problemaId, $adicional->problemas()->value('id'));
        $this->assertDatabaseHas('pedido_historicos', [
            'pedido_id' => $adicional->id,
            'descricao_alteracao' => "Pedido adicional promovido a pedido principal. Origem: {$principal->numero_protocolo}. Ação registrada: Retomar como pedido principal.",
        ]);
        $this->assertDatabaseHas('pedido_historicos', [
            'pedido_id' => $principal->id,
            'descricao_alteracao' => "Pedido adicional {$adicional->numero_protocolo} transformado em pedido principal. Ação registrada: Retomar como pedido principal.",
        ]);
    }

    public function test_acoes_filament_cancelam_e_promovem_pedidos_adicionais(): void
    {
        Storage::fake('public');

        $usuario = $this->usuarioComRoleSetor('Manutencao: Educacao', $this->educacao, [
            'Listar Pedidos',
            'Editar Pedidos',
            'Visualizar Pedidos por Status',
            'Visualizar Histórico de Pedidos',
        ]);
        $principalCancelamento = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $adicionalCancelamento = $this->pedidoAdicional($principalCancelamento);

        $htmlVisualizacao = view('components.pedido.visualizar', [
            'pedido' => $principalCancelamento,
            'historico' => collect(),
            'adicionais' => collect([$adicionalCancelamento]),
            'usuario' => $usuario,
        ])->render();

        $this->assertStringContainsString('Transformar em principal', $htmlVisualizacao);
        $this->assertStringContainsString('Cancelar adicional', $htmlVisualizacao);
        $this->assertStringContainsString('x-on:click="tab = \'historico\'"', $htmlVisualizacao);
        $this->assertStringContainsString('x-show="tab === \'historico\'"', $htmlVisualizacao);

        $htmlAdicional = view('components.pedido.visualizar', [
            'pedido' => $adicionalCancelamento,
            'pedidoOriginal' => $principalCancelamento,
            'historico' => collect(),
            'adicionais' => collect(),
            'usuario' => $usuario,
        ])->render();

        $this->assertStringContainsString('Pedido original', $htmlAdicional);
        $this->assertStringContainsString($principalCancelamento->numero_protocolo, $htmlAdicional);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->callTableAction(
                'cancelar_adicional',
                $principalCancelamento,
                ['descricao' => 'Cancelado pela tela de visualizacao.'],
                ['adicional' => $adicionalCancelamento->id],
            )
            ->assertHasNoTableActionErrors();

        $this->assertSame('Cancelado', $adicionalCancelamento->refresh()->tipoStatus->nome);

        $principalPromocao = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $adicionalPromocao = $this->pedidoAdicional($principalPromocao);
        $statusPromovido = $this->service->statusPorNome('Em Aberto', true);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->callTableAction(
                'promover_adicional',
                $principalPromocao,
                [
                    'tipo_status_id' => $statusPromovido->id,
                    'descricao' => 'Promovido pela tela de visualizacao.',
                ],
                arguments: ['adicional' => $adicionalPromocao->id],
            )
            ->assertHasNoTableActionErrors();

        $adicionalPromocao->refresh();

        $this->assertFalse((bool) $adicionalPromocao->is_pedido_adicional);
        $this->assertNull($adicionalPromocao->pedido_principal_id);
        $this->assertSame('Em Aberto', $adicionalPromocao->tipoStatus->nome);

        $principalAbaCancelamento = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $adicionalAbaCancelamento = $this->pedidoAdicional($principalAbaCancelamento);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->set('activeTab', 'adicionais')
            ->assertTableActionVisible('visualizar', $adicionalAbaCancelamento)
            ->assertTableActionVisible('cancelar_adicional', $adicionalAbaCancelamento)
            ->callTableAction('cancelar_adicional', $adicionalAbaCancelamento, [
                'descricao' => 'Cancelado pela aba de adicionais.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Cancelado', $adicionalAbaCancelamento->refresh()->tipoStatus->nome);

        $principalAbaPromocao = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $adicionalAbaPromocao = $this->pedidoAdicional($principalAbaPromocao);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->set('activeTab', 'adicionais')
            ->assertTableActionVisible('promover_adicional', $adicionalAbaPromocao)
            ->callTableAction('promover_adicional', $adicionalAbaPromocao, [
                'tipo_status_id' => $statusPromovido->id,
                'descricao' => 'Promovido pela aba de adicionais.',
            ])
            ->assertHasNoTableActionErrors();

        $adicionalAbaPromocao->refresh();

        $this->assertFalse((bool) $adicionalAbaPromocao->is_pedido_adicional);
        $this->assertNull($adicionalAbaPromocao->pedido_principal_id);
        $this->assertSame('Em Aberto', $adicionalAbaPromocao->tipoStatus->nome);
    }

    public function test_listagem_filtra_ordena_e_destaca_pedidos_com_adicionais(): void
    {
        $usuario = $this->usuarioComPermissoes([
            'Listar Pedidos',
            'Listar Todos os Pedidos',
            'Visualizar Pedidos por Status',
        ]);

        $principalComDois = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $adicionalUm = $this->pedidoAdicional($principalComDois);
        $adicionalDois = $this->pedidoAdicional($principalComDois);

        $principalComUm = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $adicionalTres = $this->pedidoAdicional($principalComUm);

        $principalSemAdicional = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->set('activeTab', 'adicionais')
            ->sortTable('pedido_principal_sort', 'asc')
            ->assertCanSeeTableRecords([$adicionalUm, $adicionalDois, $adicionalTres])
            ->assertCanNotSeeTableRecords([$principalComDois, $principalComUm, $principalSemAdicional]);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->set('activeTab', 'todos')
            ->filterTable('relacao_pedido', 'com_adicionais')
            ->assertCanSeeTableRecords([$principalComDois, $principalComUm])
            ->assertCanNotSeeTableRecords([$principalSemAdicional, $adicionalUm])
            ->assertSeeHtml('pedido-card--has-additionals')
            ->assertSeeHtml('pedido-card-field--additionals')
            ->sortTable('tipo_pedido_sort', 'asc')
            ->sortTable('pedidos_adicionais_count', 'desc')
            ->assertCanSeeTableRecords([$principalComDois, $principalComUm], inOrder: true);
    }

    public function test_listagem_mantem_concluidos_por_ultimo_e_exibe_resumo_compacto_da_avaliacao(): void
    {
        $usuario = $this->usuarioComPermissoes([
            'Listar Pedidos',
            'Listar Todos os Pedidos',
            'Visualizar Pedidos por Status',
        ]);

        $pendenteAntigo = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);
        $pendenteRecente = $this->pedido(status: 'Em Manutenção', setor: $this->educacao, escola: $this->escola);
        $concluidoRecente = $this->pedido(status: 'Concluído', setor: $this->educacao, escola: $this->escola);

        $pendenteAntigo->forceFill(['updated_at' => '2026-07-18 10:00:00'])->save();
        $pendenteRecente->forceFill(['updated_at' => '2026-07-19 10:00:00'])->save();
        $concluidoRecente->forceFill([
            'data_entrega' => '2026-07-20',
            'updated_at' => '2026-07-20 10:00:00',
        ])->save();
        $problemaConcluido = $concluidoRecente->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoLuz->id,
            'texto_problema' => $this->opcaoLuz->texto,
        ]);
        $feedback = $concluidoRecente->feedbacks()->create([
            'valor' => 5,
            'descricao' => 'Resumo geral que não deve substituir o comentário do problema.',
            'reabrir_pedido' => false,
        ]);
        $feedback->itens()->create([
            'pedido_id' => $concluidoRecente->id,
            'pedido_problema_id' => $problemaConcluido->id,
            'valor' => 5,
            'resultado' => ResultadoFeedbackPedido::Atendido,
            'comentario' => 'Serviço concluído e aprovado pela escola.',
        ]);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->set('activeTab', 'todos')
            ->sortTable('created_at_sort', 'desc')
            ->assertCanSeeTableRecords([
                $pendenteRecente,
                $pendenteAntigo,
                $concluidoRecente,
            ], inOrder: true)
            ->assertSeeHtml('pedido-card--completed')
            ->assertSeeHtml('pedido-card-main-grid')
            ->assertSee('Escola solicitante')
            ->assertSee('Comentário do problema')
            ->assertSee('Serviço concluído e aprovado pela escola.')
            ->assertDontSee('Resumo geral que não deve substituir o comentário do problema.')
            ->assertSee('Descrição do pedido')
            ->assertSee('Pedido de teste')
            ->assertSee('Sem comentário');
    }

    public function test_aba_adicionais_aparece_depois_de_em_manutencao(): void
    {
        $usuario = $this->usuarioComPermissoes([
            'Listar Pedidos',
            'Listar Todos os Pedidos',
            'Visualizar Pedidos por Status',
        ]);

        $statusManutencao = TipoStatus::query()
            ->where('nome', 'like', 'Em Manuten%')
            ->firstOrFail();

        $principal = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);
        $principal->forceFill(['tipo_status_id' => $statusManutencao->id])->save();
        $this->pedidoAdicional($principal);

        $tabs = Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->instance()
            ->getTabs();

        $keys = array_map('strval', array_keys($tabs));
        $manutencaoKey = (string) $statusManutencao->id;

        $this->assertSame($manutencaoKey, $keys[1] ?? null);
        $this->assertSame('adicionais', $keys[2] ?? null);
    }

    public function test_modo_visualizacao_da_listagem_usa_usuario_alvo_ao_alternar_abas(): void
    {
        $admin = $this->usuarioComPermissoes([
            ProfilePreviewService::PERMISSION,
            'Listar Pedidos',
            'Listar Todos os Pedidos',
            'Editar Pedidos',
            'Visualizar Pedidos por Status',
        ]);

        $usuarioObras = $this->usuarioComRoleSetor('Manutencao: Obras Preview', $this->obras, [
            'Listar Pedidos',
            'Editar Pedidos',
            'Visualizar Pedidos por Status',
        ]);

        $pedidoObras = $this->pedido(status: 'Em Aberto', setor: $this->obras, escola: $this->escola);
        $pedidoEducacao = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);

        $this->withSession([
            'profile_preview' => [
                'real_user_id' => $admin->id,
                'target_user_id' => $usuarioObras->id,
                'started_at' => now()->toISOString(),
            ],
        ]);

        Livewire::actingAs($admin)
            ->test(ListPedidos::class)
            ->set('activeTab', 'todos')
            ->assertCanSeeTableRecords([$pedidoObras])
            ->assertCanNotSeeTableRecords([$pedidoEducacao])
            ->assertTableActionVisible('gerenciar', $pedidoObras);
    }

    public function test_listagem_pesquisa_descricao_e_filtra_empresa_e_data_criacao(): void
    {
        $usuario = $this->usuarioComPermissoes([
            'Listar Pedidos',
            'Listar Todos os Pedidos',
            'Visualizar Pedidos por Status',
        ]);

        $empresa = $this->empresa('Empresa Filtro Pedidos', $this->educacao);
        $pedidoFiltrado = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);
        $pedidoFiltrado->update([
            'descricao_pedido' => 'Infiltracao exclusiva na biblioteca',
            'empresa_contratada_id' => $empresa->id,
        ]);
        $pedidoFiltrado->forceFill(['created_at' => '2026-06-20 08:00:00'])->save();

        $pedidoFora = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);
        $pedidoFora->update([
            'descricao_pedido' => 'Troca de lampadas no refeitorio',
        ]);
        $pedidoFora->forceFill(['created_at' => '2026-07-20 08:00:00'])->save();

        $pedidoAnterior = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);
        $pedidoAnterior->forceFill(['created_at' => '2026-05-20 08:00:00'])->save();

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->set('activeTab', 'todos')
            ->searchTable('Infiltracao exclusiva')
            ->assertCanSeeTableRecords([$pedidoFiltrado])
            ->assertCanNotSeeTableRecords([$pedidoFora]);

        Livewire::actingAs($usuario)
            ->test(ListPedidos::class)
            ->set('activeTab', 'todos')
            ->filterTable('empresa_contratada_id', $empresa->id)
            ->filterTable('data_criacao', [
                'data_inicio' => '2026-06-01',
                'data_fim' => '2026-06-30',
            ])
            ->assertCanSeeTableRecords([$pedidoFiltrado])
            ->assertCanNotSeeTableRecords([$pedidoFora, $pedidoAnterior]);
    }

    public function test_tipo_prints_rejeita_documento_no_modelo_e_remove_do_storage(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pedidos/print-renomeado.webp', '%PDF-1.4 teste');

        $usuario = User::factory()->create();
        $pedido = $this->pedido(status: 'Em Aberto', setor: $this->educacao, escola: $this->escola);

        try {
            PedidoArquivo::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $usuario->id,
                'tipo_arquivo' => TipoArquivoPedido::PRINTS,
                'caminho' => 'pedidos/print-renomeado.webp',
            ]);

            $this->fail('O documento deveria ter sido rejeitado como print.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('caminho', $exception->errors());
        }

        Storage::disk('public')->assertMissing('pedidos/print-renomeado.webp');
        $this->assertDatabaseCount('pedido_arquivos', 0);
    }

    public function test_relation_manager_de_adicionais_aparece_somente_no_pedido_principal(): void
    {
        $usuario = $this->usuarioComRoleSetor('Manutenção: Educação', $this->educacao, [
            'Editar Pedidos',
            'Vincular Pedidos Adicionais',
        ]);
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
        return [UploadedFile::fake()->image('foto-adicional.jpg')];
    }

    private function pedidoAdicional(Pedido $principal): Pedido
    {
        $adicional = Pedido::create([
            'pedido_principal_id' => $principal->id,
            'is_pedido_adicional' => true,
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_status_id' => $this->service->statusPorNome('Pedido Adicional', true)->id,
            'descricao_pedido' => 'Pedido adicional para teste.',
            'nome_solicitante' => 'Solicitante',
            'nivel_prioridade' => NivelEmergenciaPedido::INDEFINIDO,
            'escola_id' => $principal->escola_id,
            'solicitante_id' => $principal->solicitante_id,
            'setor_id' => $principal->setor_id,
            'setor_origem_id' => $principal->setor_origem_id,
            'data_solicitacao' => now(),
            'data_identificacao_problema' => now(),
            'ativo' => true,
        ]);

        $adicional->problemas()->create([
            'tipo_manutencao_id' => $this->tipo->id,
            'tipo_manutencao_opcao_id' => $this->opcaoDisjuntor->id,
            'texto_problema' => $this->opcaoDisjuntor->texto,
        ]);

        return $adicional;
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
