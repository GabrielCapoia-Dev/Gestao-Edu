<?php

namespace Database\Seeders;

use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\EmpresaContratada;
use App\Models\Enums\MotivoBaixa;
use App\Models\Enums\TipoItemContrato;
use App\Models\Escola;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\Inventario;
use App\Models\InventarioEstoque;
use App\Models\InventarioPedido;
use App\Models\Item;
use App\Models\PedidoMerenda;
use App\Models\Setor;
use App\Models\User;
use App\Services\Estoque\BalancoEstoqueService;
use App\Services\Inventario\BalancoInventarioService;
use App\Services\Inventario\InventarioPedidoService;
use App\Support\SecretarioPermissionPreset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EstoqueInventarioOrganicoSeeder extends Seeder
{
    protected Collection $itens;

    protected Collection $escolas;

    protected Collection $gestoresEscola;

    protected User $gestorGeral;

    protected User $almoxarife;

    protected User $conferente;

    protected Carbon $inicioHistorico;

    protected Carbon $marcoBalanco;

    protected array $motivosBaixa = [
        MotivoBaixa::Vencimento,
        MotivoBaixa::Avaria,
        MotivoBaixa::Perda,
        MotivoBaixa::Extravio,
        MotivoBaixa::Contaminacao,
        MotivoBaixa::ConsumoInterno,
        MotivoBaixa::Outro,
    ];

    public function run(): void
    {
        $this->seedBaseCatalogo();

        if ($this->historicoJaExiste()) {
            $this->command?->warn('Historico organico de estoque e inventario ja encontrado. Seeder ignorado para evitar duplicidade.');

            return;
        }

        $this->inicioHistorico = now()->subMonths(7)->startOfMonth()->setTime(8, 0);
        $this->marcoBalanco = now()->subDays(55)->setTime(9, 0);

        $this->bootstrapPermissoes();
        $this->bootstrapUsuarios();
        $this->itens = Item::query()->where('ativo', true)->orderBy('id')->get();
        $this->escolas = Escola::query()->where('ativo', true)->orderBy('nome')->get();

        $this->garantirCoberturaContratual();
        $this->popularPedidosMerendaHistoricos();
        $this->popularEstoqueMatrizBase();
        $this->garantirInventariosEscolares();

        $this->popularPedidosEntreguesHistoricos();
        $this->registrarConsumoInventarios(fase: 'antiga');
        $this->registrarBaixasInventarios(fase: 'antiga');
        $this->registrarBaixasMatriz(fase: 'antiga');

        $this->criarBalancoEstoqueConcluido();
        $this->criarBalancosInventarioConcluidos();

        $this->popularPedidosEntreguesRecentes();
        $this->registrarConsumoInventarios(fase: 'recente');
        $this->registrarBaixasInventarios(fase: 'recente');
        $this->registrarBaixasMatriz(fase: 'recente');

        $this->criarPedidosAtuaisComStatusDiversos();
        $this->criarBalancosPendentes();

        $this->command?->info(sprintf(
            'Seeder organico finalizado: %d estoques matriz, %d inventarios, %d pedidos de inventario, %d romaneios.',
            Estoque::query()->count(),
            Inventario::query()->count(),
            InventarioPedido::query()->count(),
            \App\Models\InventarioRomaneio::query()->count(),
        ));
    }

    protected function seedBaseCatalogo(): void
    {
        $this->call([
            SetorSeeder::class,
            EscolaSeeder::class,
            EmpresaContratadaSeeder::class,
            ItensSeeder::class,
            ContratoSeeder::class,
        ]);
    }

    protected function historicoJaExiste(): bool
    {
        return EstoqueMovimentacao::query()->exists()
            || InventarioPedido::query()->exists()
            || \App\Models\BalancoEstoque::query()->exists()
            || \App\Models\BalancoInventario::query()->exists();
    }

    protected function bootstrapPermissoes(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'Secretário']);

        $permissions = [
            'Listar Gestão de Estoque',
            'Listar Inventários',
            'Listar Gestão de Inventário',
            'Listar Pedidos de Inventário',
            'Criar Pedidos de Inventário',
            'Aprovar Pedidos de Inventário',
            'Gerar Romaneios de Inventário',
            'Conferir Pedidos de Inventário',
            'Listar Balanços de Inventário',
            'Criar Balanços de Inventário',
            'Iniciar Balanços de Inventário',
            'Registrar Contagem de Balanços de Inventário',
            'Concluir Balanços de Inventário',
            'Adiar Balanços de Inventário',
            'Cancelar Balanços de Inventário',
            'Listar Balanços de Estoque',
            'Criar Balanços de Estoque',
            'Iniciar Balanços de Estoque',
            'Registrar Contagem de Balanços de Estoque',
            'Concluir Balanços de Estoque',
            'Adiar Balanços de Estoque',
            'Cancelar Balanços de Estoque',
            'Exportar Relatórios',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }
    }

    protected function bootstrapUsuarios(): void
    {
        $setorRootId = Setor::setorGeral()?->id;
        $adminRole = Role::query()->where('name', 'Admin')->first();
        $secretarioRole = Role::query()->where('name', 'Secretário')->first();

        $this->gestorGeral = User::query()->updateOrCreate(
            ['email' => 'gestor.logistica@gestaoedu.local'],
            [
                'name' => 'Marina Teles',
                'setor_id' => $setorRootId,
                'password' => Hash::make('Senha@123'),
                'email_approved' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->almoxarife = User::query()->updateOrCreate(
            ['email' => 'almoxarife.matriz@gestaoedu.local'],
            [
                'name' => 'Rafael Duarte',
                'setor_id' => $setorRootId,
                'password' => Hash::make('Senha@123'),
                'email_approved' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->conferente = User::query()->updateOrCreate(
            ['email' => 'conferencia.matriz@gestaoedu.local'],
            [
                'name' => 'Luciana Prado',
                'setor_id' => $setorRootId,
                'password' => Hash::make('Senha@123'),
                'email_approved' => true,
                'email_verified_at' => now(),
            ],
        );

        if ($adminRole) {
            $this->gestorGeral->syncRoles([$adminRole]);
            $this->almoxarife->syncRoles([$adminRole]);
            $this->conferente->syncRoles([$adminRole]);
        }

        $schoolPermissions = [
            'Listar Gestão de Inventário',
            'Listar Pedidos de Inventário',
            'Criar Pedidos de Inventário',
            'Conferir Pedidos de Inventário',
            'Listar Balanços de Inventário',
            'Criar Balanços de Inventário',
            'Iniciar Balanços de Inventário',
            'Registrar Contagem de Balanços de Inventário',
            'Concluir Balanços de Inventário',
            'Adiar Balanços de Inventário',
            'Cancelar Balanços de Inventário',
            'Exportar Relatórios',
        ];

        $schoolPermissions = SecretarioPermissionPreset::gestaoEscolar();

        $this->gestoresEscola = Escola::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(function (Escola $escola) use ($setorRootId, $secretarioRole, $schoolPermissions): array {
                $email = 'gestor.' . strtolower($escola->codigo) . '@gestaoedu.local';
                $nomeCurto = Str::of($escola->nome)
                    ->replace('CMEI - ', '')
                    ->replace('ESCOLA - ', '')
                    ->limit(40, '');

                $user = User::query()->updateOrCreate(
                    ['email' => $email],
                    [
                        'id_escola' => $escola->getKey(),
                        'setor_id' => $escola->setor_id ?: $setorRootId,
                        'name' => 'Gestão ' . $nomeCurto,
                        'password' => Hash::make('Senha@123'),
                        'email_approved' => true,
                        'email_verified_at' => now(),
                    ],
                );

                if ($secretarioRole) {
                    $user->syncRoles([$secretarioRole]);
                }

                $user->givePermissionTo($schoolPermissions);

                return [$escola->getKey() => $user];
            });
    }

    protected function garantirCoberturaContratual(): void
    {
        $setorRootId = Setor::setorGeral()?->id;

        $empresa = EmpresaContratada::query()->firstOrCreate(
            ['cnpj' => '98.765.432/0001-10'],
            [
                'nome' => 'Cooperativa Alimentar da Rede',
                'email' => 'licitacoes@cooperativa-alimentar.local',
                'responsavel' => 'Bruna Mendes',
                'telefone' => '(44) 3621-5050',
                'cep' => '87501-120',
                'logradouro' => 'Avenida Paraná',
                'numero' => '1200',
                'bairro' => 'Centro',
                'cidade' => 'Umuarama',
                'estado' => 'PR',
                'setor_id' => $setorRootId,
                'ativo' => true,
                'alterado_por' => $this->gestorGeral->name,
            ],
        );

        if (blank($empresa->setor_id) && $setorRootId) {
            $empresa->forceFill(['setor_id' => $setorRootId])->saveQuietly();
        }

        $contratoAtivo = $this->operarEmContexto($this->gestorGeral, $this->inicioHistorico->copy()->addDays(2), function () use ($empresa): Contrato {
            return Contrato::query()->firstOrCreate(
                ['numero_contrato' => 'MER-ORG-' . now()->format('Y')],
                [
                    'id_empresa_contratada' => $empresa->getKey(),
                    'setor_id' => $empresa->setor_id,
                    'data_inicio' => now()->subMonths(7)->startOfMonth()->toDateString(),
                    'data_vencimento' => now()->addMonths(8)->endOfMonth()->toDateString(),
                    'observacoes' => 'Contrato guarda-chuva para abastecimento organico da rede.',
                    'ativo' => true,
                ],
            );
        });

        if (blank($contratoAtivo->setor_id) && $empresa->setor_id) {
            $contratoAtivo->forceFill(['setor_id' => $empresa->setor_id])->saveQuietly();
        }

        foreach ($this->itens as $item) {
            ContratoItem::query()->firstOrCreate(
                [
                    'contrato_id' => $contratoAtivo->getKey(),
                    'item_id' => $item->getKey(),
                    'tipo' => TipoItemContrato::Compra,
                ],
                [
                    'quantidade_total' => $this->quantidadeContratoBase($item),
                    'quantidade_utilizada' => 0,
                    'quantidade_reservada' => 0,
                    'preco_unitario' => $this->precoReferencia($item),
                ],
            );
        }
    }

    protected function popularPedidosMerendaHistoricos(): void
    {
        $momento = $this->inicioHistorico->copy()->addMonths(2)->setDay(12)->setTime(10, 30);

        $this->operarEmContexto($this->gestorGeral, $momento, function (): void {
            $this->call(PedidoMerendaSeeder::class);
        });

        $pedidos = PedidoMerenda::query()->with('itens')->orderBy('id')->get();

        foreach ($pedidos as $index => $pedido) {
            $dataPedido = $this->inicioHistorico->copy()->addDays(18 + ($index * 3));
            $pedido->forceFill([
                'created_at' => $dataPedido,
                'updated_at' => $dataPedido->copy()->addDays(1),
            ])->saveQuietly();

            foreach ($pedido->itens as $itemIndex => $pedidoItem) {
                $itemDate = $dataPedido->copy()->addHours($itemIndex + 1);

                $pedidoItem->forceFill([
                    'created_at' => $itemDate,
                    'updated_at' => $itemDate->copy()->addHours(2),
                ])->saveQuietly();
            }
        }
    }

    protected function popularEstoqueMatrizBase(): void
    {
        foreach ($this->itens as $index => $item) {
            $estoque = Estoque::query()->firstOrCreate(
                ['item_id' => $item->getKey()],
                ['quantidade' => 0, 'quantidade_reservada' => 0],
            );

            $dataInicial = $this->inicioHistorico->copy()->addDays(($index % 10) + 1)->addHours($index % 6);
            $quantidadeInicial = $this->estoqueInicial($item, $index);

            $this->operarEmContexto($this->almoxarife, $dataInicial, function () use ($estoque, $quantidadeInicial, $item): void {
                $estoque->refresh();
                $estoque->entrada(
                    $quantidadeInicial,
                    null,
                    'Carga inicial da matriz para o item ' . $item->nome,
                );
            });

            if ($index % 3 !== 0) {
                $dataReposicao = $this->inicioHistorico->copy()->addMonths(2)->addDays($index % 14)->setTime(9, 15);
                $reposicao = round(max(18, $quantidadeInicial * 0.35), 3);

                $this->operarEmContexto($this->almoxarife, $dataReposicao, function () use ($estoque, $reposicao, $item): void {
                    $estoque->refresh();
                    $estoque->entrada(
                        $reposicao,
                        null,
                        'Reposicao programada da matriz para o item ' . $item->nome,
                    );
                });
            }

            if ($index % 7 === 0) {
                $dataEmergencia = $this->inicioHistorico->copy()->addMonths(4)->addDays($index % 9)->setTime(15, 20);
                $saida = round(min(max(2, $quantidadeInicial * 0.08), max(1, (float) $estoque->fresh()->quantidade * 0.18)), 3);

                $this->operarEmContexto($this->conferente, $dataEmergencia, function () use ($estoque, $saida): void {
                    $estoque->refresh();

                    if ($saida > 0 && $saida < (float) $estoque->quantidade) {
                        $estoque->saida(
                            $saida,
                            null,
                            'Atendimento emergencial fora do fluxo regular de romaneio',
                        );
                    }
                });
            }
        }
    }

    protected function garantirInventariosEscolares(): void
    {
        foreach ($this->escolas as $escola) {
            Inventario::query()->firstOrCreate(
                ['escola_id' => $escola->getKey()],
                [
                    'nome' => 'Inventario - ' . $escola->nome,
                    'setor_id' => $escola->setor_id,
                    'ativo' => true,
                    'criado_por_id' => $this->gestorGeral->getKey(),
                ],
            );
        }
    }

    protected function popularPedidosEntreguesHistoricos(): void
    {
        $inventarios = Inventario::query()->with('escola')->orderBy('escola_id')->get()->values();

        foreach ($inventarios as $index => $inventario) {
            $dataBase = $this->inicioHistorico->copy()->addDays(16 + (($index * 4) % 36))->setTime(8, 40);
            $this->criarPedidoEntregue($inventario, $dataBase, $index + 10);

            if ($index < 18) {
                $segundaData = $this->inicioHistorico->copy()->addDays(74 + (($index * 5) % 34))->setTime(10, 10);
                $this->criarPedidoEntregue($inventario, $segundaData, $index + 110);
            }
        }
    }

    protected function popularPedidosEntreguesRecentes(): void
    {
        $inventarios = Inventario::query()->with('escola')->orderBy('escola_id')->get()->values();

        foreach ($inventarios as $index => $inventario) {
            if ($index % 3 !== 0) {
                continue;
            }

            $data = now()->subDays(42 - (($index * 2) % 22))->setTime(9, 30);
            $this->criarPedidoEntregue($inventario, $data, $index + 210, permitirDivergencia: $index % 2 === 0);
        }
    }

    protected function criarPedidoEntregue(
        Inventario $inventario,
        Carbon $dataBase,
        int $seed,
        bool $permitirDivergencia = true,
    ): void {
        $gestorEscola = $this->gestoresEscola->get($inventario->escola_id);

        if (! $gestorEscola) {
            return;
        }

        $pedidoService = app(InventarioPedidoService::class);
        $payloadItens = $this->montarItensPedido($inventario, $seed);

        $pedido = $this->operarEmContexto($gestorEscola, $dataBase, function () use ($pedidoService, $gestorEscola, $payloadItens) {
            return $pedidoService->criarPedido($gestorEscola, [
                'observacao_escola' => $this->observacaoEscola(),
                'itens' => $payloadItens,
            ]);
        });

        $pedido = $this->operarEmContexto($this->gestorGeral, $dataBase->copy()->addDay()->setTime(14, 20), function () use ($pedidoService, $pedido, $seed) {
            return $pedidoService->aprovarPedido(
                $pedido->fresh('itens.item'),
                $this->montarAprovacaoPedido($pedido->fresh('itens.item'), $seed),
                $this->observacaoGestor($seed),
                $this->gestorGeral,
            );
        });

        if (! $pedido->isAprovado()) {
            return;
        }

        $this->garantirSaldoMatrizParaPedidos(collect([$pedido]), $dataBase->copy()->addDays(2)->setTime(7, 30));

        $this->operarEmContexto($this->almoxarife, $dataBase->copy()->addDays(2)->setTime(8, 45), function () use ($pedidoService, $pedido): void {
            $pedidoService->gerarRomaneio(
                [$pedido->getKey()],
                'Carga separada para abastecimento regular da unidade.',
                $this->almoxarife,
            );
        });

        $this->operarEmContexto($gestorEscola, $dataBase->copy()->addDays(4)->setTime(16, 0), function () use ($pedidoService, $pedido, $seed, $permitirDivergencia, $gestorEscola): void {
            $pedidoAtual = $pedido->fresh(['itens.item', 'romaneio', 'escola']);
            $conferencia = $this->montarConferenciaPedido($pedidoAtual, $seed, $permitirDivergencia);

            $pedidoService->conferirEntrega(
                $pedidoAtual,
                $conferencia['itens'],
                $conferencia['observacao'],
                $gestorEscola,
            );
        });
    }

    protected function registrarConsumoInventarios(string $fase): void
    {
        $inventarios = Inventario::query()->with(['estoques.item'])->orderBy('escola_id')->get();

        foreach ($inventarios as $index => $inventario) {
            $gestorEscola = $this->gestoresEscola->get($inventario->escola_id);

            if (! $gestorEscola) {
                continue;
            }

            $estoques = $inventario->estoques
                ->filter(fn (InventarioEstoque $estoque): bool => (float) $estoque->quantidade > 4)
                ->sortByDesc('quantidade')
                ->take($fase === 'antiga' ? 3 : 2)
                ->values();

            foreach ($estoques as $estoqueIndex => $estoque) {
                $data = $fase === 'antiga'
                    ? $this->marcoBalanco->copy()->subDays(12 + (($index + $estoqueIndex) % 15))->setTime(11, 0)
                    : now()->subDays(18 - (($index + $estoqueIndex) % 9))->setTime(10, 40);

                $quantidadeAtual = (float) $estoque->fresh()->quantidade;
                $consumo = round(min(max(1.2, $quantidadeAtual * ($fase === 'antiga' ? 0.12 : 0.08)), max(0.8, $quantidadeAtual * 0.22)), 3);

                $this->operarEmContexto($gestorEscola, $data, function () use ($estoque, $consumo, $fase): void {
                    $estoque->refresh();

                    if ($consumo > 0 && $consumo < (float) $estoque->quantidade) {
                        $estoque->saida(
                            $consumo,
                            null,
                            $fase === 'antiga'
                                ? 'Consumo semanal de merenda registrado pela unidade escolar'
                                : 'Consumo recente para atendimento do cardapio da semana',
                        );
                    }
                });
            }
        }
    }

    protected function registrarBaixasInventarios(string $fase): void
    {
        $inventarios = Inventario::query()->with(['estoques.item'])->orderBy('escola_id')->get();

        foreach ($inventarios as $index => $inventario) {
            $gestorEscola = $this->gestoresEscola->get($inventario->escola_id);

            if (! $gestorEscola) {
                continue;
            }

            $estoque = $inventario->estoques
                ->filter(fn (InventarioEstoque $registro): bool => (float) $registro->quantidade > 3)
                ->sortByDesc('quantidade')
                ->values()
                ->get($fase === 'antiga' ? 0 : 1);

            if (! $estoque instanceof InventarioEstoque) {
                continue;
            }

            $data = $fase === 'antiga'
                ? $this->marcoBalanco->copy()->subDays(8 + ($index % 10))->setTime(14, 15)
                : now()->subDays(9 - ($index % 5))->setTime(15, 5);

            $quantidade = round(min(max(0.8, (float) $estoque->quantidade * ($fase === 'antiga' ? 0.06 : 0.05)), 6.5), 3);
            $motivo = $this->motivosBaixa[($index + ($fase === 'antiga' ? 1 : 4)) % count($this->motivosBaixa)];
            $descricao = $fase === 'antiga'
                ? 'Baixa operacional registrada em revisao mensal da despensa escolar.'
                : 'Baixa recente apos conferencia de perdas e vencimentos da unidade.';

            $this->operarEmContexto($gestorEscola, $data, function () use ($estoque, $quantidade, $motivo, $descricao): void {
                $estoque->refresh();

                if ($quantidade > 0 && $quantidade < (float) $estoque->quantidade) {
                    $estoque->registrarBaixa($quantidade, $motivo, $descricao);
                }
            });
        }
    }

    protected function registrarBaixasMatriz(string $fase): void
    {
        $estoques = Estoque::query()
            ->with('item')
            ->orderBy('item_id')
            ->get()
            ->filter(fn (Estoque $estoque): bool => (float) $estoque->quantidade_disponivel > 8)
            ->values();

        $alvo = $fase === 'antiga' ? 16 : 10;

        foreach ($estoques->take($alvo) as $index => $estoque) {
            $data = $fase === 'antiga'
                ? $this->marcoBalanco->copy()->subDays(18 + ($index % 11))->setTime(13, 30)
                : now()->subDays(14 - ($index % 6))->setTime(9, 50);

            $quantidade = round(min(max(1.5, (float) $estoque->quantidade_disponivel * 0.05), 9.5), 3);
            $motivo = $this->motivosBaixa[($index + ($fase === 'antiga' ? 2 : 5)) % count($this->motivosBaixa)];
            $descricao = $fase === 'antiga'
                ? 'Baixa apurada durante saneamento da camara fria e area seca.'
                : 'Baixa recente registrada pela equipe da matriz apos conferencia de lotes.';

            $this->operarEmContexto($this->conferente, $data, function () use ($estoque, $quantidade, $motivo, $descricao): void {
                $estoque->refresh();

                if ($quantidade > 0 && $quantidade < (float) $estoque->quantidade_disponivel) {
                    $estoque->registrarBaixa($quantidade, $motivo, $descricao);
                }
            });
        }
    }

    protected function criarBalancoEstoqueConcluido(): void
    {
        $service = app(BalancoEstoqueService::class);

        $balanco = $this->operarEmContexto($this->gestorGeral, $this->marcoBalanco->copy(), function () use ($service) {
            return $service->agendar([
                'data_agendada' => $this->marcoBalanco->copy()->toDateTimeString(),
                'observacao_inicial' => 'Balanco geral do almoxarifado central apos o fechamento bimestral.',
            ], $this->gestorGeral);
        });

        $itensSelecionados = Estoque::query()
            ->orderByDesc('quantidade')
            ->take(18)
            ->pluck('item_id')
            ->all();

        $balanco = $this->operarEmContexto($this->gestorGeral, $this->marcoBalanco->copy()->addHour(), function () use ($service, $balanco, $itensSelecionados) {
            return $service->iniciar($balanco, $itensSelecionados, $this->gestorGeral);
        });

        foreach ($balanco->itens()->where('incluido_na_contagem', true)->get()->values() as $index => $itemBalanco) {
            $saldo = (float) $itemBalanco->saldo_sistema_antes;
            $contado = match ($index % 4) {
                0 => $saldo,
                1 => round($saldo + max(0.8, $saldo * 0.04), 3),
                2 => round(max(0, $saldo - max(0.6, $saldo * 0.05)), 3),
                default => round($saldo + 0.5, 3),
            };

            $this->operarEmContexto($this->conferente, $this->marcoBalanco->copy()->addHours(2)->addMinutes($index * 3), function () use ($service, $itemBalanco, $contado): void {
                $service->registrarContagem(
                    $itemBalanco->fresh(),
                    $contado,
                    'Contagem reconciliada pela equipe da matriz.',
                    $this->conferente,
                );
            });
        }

        $this->operarEmContexto($this->gestorGeral, $this->marcoBalanco->copy()->addHours(5), function () use ($service, $balanco): void {
            $service->concluir($balanco->fresh(), $this->gestorGeral);
        });
    }

    protected function criarBalancosInventarioConcluidos(): void
    {
        $service = app(BalancoInventarioService::class);
        $inventarios = Inventario::query()->with(['escola', 'estoques'])->orderBy('escola_id')->take(8)->get();

        foreach ($inventarios as $index => $inventario) {
            $gestorEscola = $this->gestoresEscola->get($inventario->escola_id);

            if (! $gestorEscola) {
                continue;
            }

            $itensElegiveis = InventarioEstoque::query()
                ->where('inventario_id', $inventario->getKey())
                ->where('quantidade', '>', 0)
                ->orderByDesc('quantidade')
                ->take(8)
                ->pluck('item_id')
                ->all();

            if ($itensElegiveis === []) {
                continue;
            }

            $dataBase = $this->marcoBalanco->copy()->addMinutes($index * 20);

            $balanco = $this->operarEmContexto($gestorEscola, $dataBase->copy(), function () use ($service, $inventario, $gestorEscola, $dataBase) {
                return $service->agendar($inventario, [
                    'data_agendada' => $dataBase->toDateTimeString(),
                    'observacao_inicial' => 'Balanco interno da unidade para saneamento do inventario escolar.',
                ], $gestorEscola);
            });

            $balanco = $this->operarEmContexto($gestorEscola, $dataBase->copy()->addHour(), function () use ($service, $balanco, $itensElegiveis, $gestorEscola) {
                return $service->iniciar($balanco, $itensElegiveis, $gestorEscola);
            });

            foreach ($balanco->itens()->where('incluido_na_contagem', true)->get()->values() as $itemIndex => $itemBalanco) {
                $saldo = (float) $itemBalanco->saldo_sistema_antes;
                $contado = match ($itemIndex % 3) {
                    0 => $saldo,
                    1 => round(max(0, $saldo - max(0.4, $saldo * 0.08)), 3),
                    default => round($saldo + max(0.5, $saldo * 0.06), 3),
                };

                $this->operarEmContexto($gestorEscola, $dataBase->copy()->addHours(2)->addMinutes($itemIndex * 4), function () use ($service, $itemBalanco, $contado, $gestorEscola): void {
                    $service->registrarContagem(
                        $itemBalanco->fresh(),
                        $contado,
                        'Contagem registrada pela equipe escolar.',
                        $gestorEscola,
                    );
                });
            }

            $this->operarEmContexto($gestorEscola, $dataBase->copy()->addHours(4), function () use ($service, $balanco, $gestorEscola): void {
                $service->concluir($balanco->fresh(), $gestorEscola);
            });
        }
    }

    protected function criarPedidosAtuaisComStatusDiversos(): void
    {
        $inventarios = Inventario::query()->with('escola')->orderBy('escola_id')->get()->values();
        $pedidoService = app(InventarioPedidoService::class);
        $romaneioBuffer = collect();

        foreach ($inventarios as $index => $inventario) {
            $gestorEscola = $this->gestoresEscola->get($inventario->escola_id);

            if (! $gestorEscola) {
                continue;
            }

            if ($index < 6) {
                $this->operarEmContexto($gestorEscola, now()->subDays(4)->setTime(9, 10)->addMinutes($index * 7), function () use ($pedidoService, $gestorEscola, $inventario, $index): void {
                    $pedidoService->criarPedido($gestorEscola, [
                        'observacao_escola' => 'Pedido novo aguardando analise do gestor geral.',
                        'itens' => $this->montarItensPedido($inventario, 320 + $index, 4),
                    ]);
                });

                continue;
            }

            if ($index < 12) {
                $pedido = $this->operarEmContexto($gestorEscola, now()->subDays(6)->setTime(8, 30)->addMinutes($index * 5), function () use ($pedidoService, $gestorEscola, $inventario, $index) {
                    return $pedidoService->criarPedido($gestorEscola, [
                        'observacao_escola' => 'Reposicao solicitada para o fechamento da semana.',
                        'itens' => $this->montarItensPedido($inventario, 410 + $index, 5),
                    ]);
                });

                $this->operarEmContexto($this->gestorGeral, now()->subDays(5)->setTime(14, 40)->addMinutes($index * 4), function () use ($pedidoService, $pedido, $index): void {
                    $pedidoService->aprovarPedido(
                        $pedido->fresh('itens.item'),
                        $this->montarAprovacaoPedido($pedido->fresh('itens.item'), 510 + $index),
                        'Pedido aprovado e aguardando montagem de romaneio da matriz.',
                        $this->gestorGeral,
                    );
                });

                continue;
            }

            if ($index < 20) {
                $pedido = $this->operarEmContexto($gestorEscola, now()->subDays(5)->setTime(7, 50)->addMinutes($index * 6), function () use ($pedidoService, $gestorEscola, $inventario, $index) {
                    return $pedidoService->criarPedido($gestorEscola, [
                        'observacao_escola' => 'Pedido com prioridade para reposicao imediata.',
                        'itens' => $this->montarItensPedido($inventario, 610 + $index, 5),
                    ]);
                });

                $pedido = $this->operarEmContexto($this->gestorGeral, now()->subDays(4)->setTime(13, 25)->addMinutes($index * 3), function () use ($pedidoService, $pedido, $index) {
                    return $pedidoService->aprovarPedido(
                        $pedido->fresh('itens.item'),
                        $this->montarAprovacaoPedido($pedido->fresh('itens.item'), 710 + $index),
                        'Pedido liberado para despacho da proxima rota.',
                        $this->gestorGeral,
                    );
                });

                if ($pedido->isAprovado()) {
                    $romaneioBuffer->push($pedido->fresh('itens.item'));
                }

                continue;
            }

            if ($index < 25) {
                $pedido = $this->operarEmContexto($gestorEscola, now()->subDays(8)->setTime(10, 0)->addMinutes($index * 2), function () use ($pedidoService, $gestorEscola, $inventario, $index) {
                    return $pedidoService->criarPedido($gestorEscola, [
                        'observacao_escola' => 'Pedido anterior foi reajustado e precisa de nova analise.',
                        'itens' => $this->montarItensPedido($inventario, 810 + $index, 4),
                    ]);
                });

                $this->operarEmContexto($this->gestorGeral, now()->subDays(7)->setTime(11, 45)->addMinutes($index * 3), function () use ($pedidoService, $pedido): void {
                    $pedidoService->aprovarPedido(
                        $pedido->fresh('itens.item'),
                        $this->montarAprovacaoPedidoRecusado($pedido->fresh('itens.item')),
                        'Pedido recusado por indisponibilidade temporaria e necessidade de reprogramacao.',
                        $this->gestorGeral,
                    );
                });
            }
        }

        if ($romaneioBuffer->isEmpty()) {
            return;
        }

        foreach ($romaneioBuffer->chunk(4)->values() as $chunkIndex => $pedidos) {
            $dataRomaneio = now()->subDays(3)->setTime(8, 20)->addHours($chunkIndex);
            $this->garantirSaldoMatrizParaPedidos($pedidos, $dataRomaneio->copy()->subHour());

            $this->operarEmContexto($this->almoxarife, $dataRomaneio, function () use ($pedidoService, $pedidos, $chunkIndex): void {
                $pedidoService->gerarRomaneio(
                    $pedidos->pluck('id')->all(),
                    'Romaneio em aberto aguardando conferencia das escolas - rota ' . ($chunkIndex + 1) . '.',
                    $this->almoxarife,
                );
            });
        }
    }

    protected function criarBalancosPendentes(): void
    {
        $balancoEstoqueService = app(BalancoEstoqueService::class);
        $balancoInventarioService = app(BalancoInventarioService::class);

        $balancoCancelado = $this->operarEmContexto($this->gestorGeral, now()->subDays(10)->setTime(9, 0), function () use ($balancoEstoqueService) {
            return $balancoEstoqueService->agendar([
                'data_agendada' => now()->subDays(10)->setTime(9, 0)->toDateTimeString(),
                'observacao_inicial' => 'Balanco extraordinario cancelado por manutencao da matriz.',
            ], $this->gestorGeral);
        });

        $this->operarEmContexto($this->gestorGeral, now()->subDays(9)->setTime(16, 10), function () use ($balancoEstoqueService, $balancoCancelado): void {
            $balancoEstoqueService->cancelar(
                $balancoCancelado->fresh(),
                'Cancelado devido a indisponibilidade operacional da equipe de contagem.',
                $this->gestorGeral,
            );
        });

        $this->operarEmContexto($this->gestorGeral, now()->addDays(6)->setTime(8, 30), function () use ($balancoEstoqueService): void {
            $balancoEstoqueService->agendar([
                'data_agendada' => now()->addDays(6)->setTime(8, 30)->toDateTimeString(),
                'observacao_inicial' => 'Balanco geral agendado para o fechamento do proximo ciclo.',
            ], $this->gestorGeral);
        });

        $inventarios = Inventario::query()->orderBy('escola_id')->take(6)->get()->values();

        foreach ($inventarios as $index => $inventario) {
            $gestorEscola = $this->gestoresEscola->get($inventario->escola_id);

            if (! $gestorEscola) {
                continue;
            }

            if ($index < 3) {
                $balanco = $this->operarEmContexto($gestorEscola, now()->subDays(7)->setTime(10, 15)->addMinutes($index * 15), function () use ($balancoInventarioService, $inventario, $gestorEscola, $index) {
                    return $balancoInventarioService->agendar($inventario, [
                        'data_agendada' => now()->subDays(7)->setTime(10, 15)->addMinutes($index * 15)->toDateTimeString(),
                        'observacao_inicial' => 'Balanco escolar remarcado apos limpeza de estoque.',
                    ], $gestorEscola);
                });

                $this->operarEmContexto($gestorEscola, now()->subDays(6)->setTime(14, 5)->addMinutes($index * 10), function () use ($balancoInventarioService, $balanco, $gestorEscola): void {
                    $balancoInventarioService->cancelar(
                        $balanco->fresh(),
                        'Cancelado por reorganizacao da equipe escolar e conferencias pendentes.',
                        $gestorEscola,
                    );
                });

                continue;
            }

            $dataAgendada = now()->addDays(5 + $index)->setTime(9, 20);

            $this->operarEmContexto($gestorEscola, $dataAgendada, function () use ($balancoInventarioService, $inventario, $gestorEscola, $dataAgendada): void {
                $balancoInventarioService->agendar($inventario, [
                    'data_agendada' => $dataAgendada->toDateTimeString(),
                    'observacao_inicial' => 'Balanco escolar agendado para a proxima virada de estoque.',
                ], $gestorEscola);
            });
        }
    }

    protected function montarItensPedido(Inventario $inventario, int $seed, int $quantidadeItens = 5): array
    {
        $porte = str_contains(mb_strtoupper((string) $inventario->escola?->nome), 'CMEI') ? 0.72 : 1.0;
        $itens = $this->selecionarItens($seed, $quantidadeItens);

        return $itens->values()->map(function (Item $item, int $index) use ($porte, $seed): array {
            return [
                'item_id' => $item->getKey(),
                'quantidade_solicitada' => $this->quantidadeSolicitada($item, $porte, $seed + $index),
                'observacao_solicitacao' => $this->observacaoItemSolicitado($item, $seed + $index),
            ];
        })->all();
    }

    protected function montarAprovacaoPedido(InventarioPedido $pedido, int $seed): array
    {
        $itens = $pedido->itens()->with('item')->get()->values();
        $aprovadosPositivos = 0;

        return $itens->map(function ($pedidoItem, int $index) use ($seed, $itens, &$aprovadosPositivos): array {
            $quantidadeSolicitada = round((float) $pedidoItem->quantidade_solicitada, 3);
            $fator = match (($seed + $index + $pedidoItem->item_id) % 5) {
                0 => 1.0,
                1 => 0.92,
                2 => 0.85,
                3 => 0.78,
                default => 0.0,
            };

            $quantidadeAprovada = round($quantidadeSolicitada * $fator, 3);

            if ($quantidadeAprovada <= 0 && $aprovadosPositivos === 0 && $index === $itens->count() - 1) {
                $quantidadeAprovada = round($quantidadeSolicitada * 0.82, 3);
            }

            if ($quantidadeAprovada > 0) {
                $aprovadosPositivos++;
            }

            return [
                'item_id' => $pedidoItem->item_id,
                'quantidade_aprovada' => $quantidadeAprovada,
                'observacao_aprovacao' => $quantidadeAprovada > 0
                    ? 'Liberado com ajuste conforme saldo da matriz e planejamento da rota.'
                    : 'Item temporariamente indisponivel no lote priorizado para esta semana.',
            ];
        })->all();
    }

    protected function montarAprovacaoPedidoRecusado(InventarioPedido $pedido): array
    {
        return $pedido->itens()->get()->map(fn ($pedidoItem): array => [
            'item_id' => $pedidoItem->item_id,
            'quantidade_aprovada' => 0,
            'observacao_aprovacao' => 'Item recusado no fechamento desta analise.',
        ])->all();
    }

    protected function montarConferenciaPedido(InventarioPedido $pedido, int $seed, bool $permitirDivergencia): array
    {
        $houveDivergencia = false;

        $itens = $pedido->itens
            ->filter(fn ($item) => (float) ($item->quantidade_aprovada ?? 0) > 0)
            ->values()
            ->map(function ($pedidoItem, int $index) use ($seed, $permitirDivergencia, &$houveDivergencia): array {
                $aprovada = round((float) ($pedidoItem->quantidade_aprovada ?? 0), 3);
                $recebida = $aprovada;

                if ($permitirDivergencia && (($seed + $index + $pedidoItem->item_id) % 6) === 0) {
                    $recebida = round(max(0, $aprovada - max(0.5, $aprovada * 0.12)), 3);
                    $houveDivergencia = true;
                }

                return [
                    'item_id' => $pedidoItem->item_id,
                    'quantidade_recebida' => $recebida,
                    'observacao_conferencia' => $recebida === $aprovada
                        ? 'Entrega conferida sem ressalvas.'
                        : 'Conferencia identificou divergencia entre romaneio e volume recebido.',
                ];
            })->all();

        return [
            'itens' => $itens,
            'observacao' => $houveDivergencia
                ? 'Recebimento finalizado com divergencias registradas na conferencia da unidade.'
                : null,
        ];
    }

    protected function garantirSaldoMatrizParaPedidos(Collection $pedidos, Carbon $momento): void
    {
        $necessidades = $pedidos
            ->flatMap(fn (InventarioPedido $pedido) => $pedido->fresh('itens')->itens)
            ->groupBy('item_id')
            ->map(fn (Collection $itens): float => round((float) $itens->sum('quantidade_aprovada'), 3))
            ->filter(fn (float $quantidade): bool => $quantidade > 0);

        foreach ($necessidades as $itemId => $quantidade) {
            $estoque = Estoque::query()->firstOrCreate(
                ['item_id' => $itemId],
                ['quantidade' => 0, 'quantidade_reservada' => 0],
            );

            $faltante = round(($quantidade + max(12, $quantidade * 0.35)) - (float) $estoque->fresh()->quantidade_disponivel, 3);

            if ($faltante <= 0) {
                continue;
            }

            $itemNome = $estoque->item?->nome ?? 'item';

            $this->operarEmContexto($this->almoxarife, $momento->copy(), function () use ($estoque, $faltante, $itemNome): void {
                $estoque->refresh();
                $estoque->entrada(
                    $faltante,
                    null,
                    'Reposicao complementar para atender romaneios e manter buffer do item ' . $itemNome,
                );
            });
        }
    }

    protected function selecionarItens(int $seed, int $quantidade): Collection
    {
        return $this->itens
            ->sortBy(fn (Item $item): int => (($item->getKey() * 37) + ($seed * 19)) % 997)
            ->take(min($quantidade, $this->itens->count()))
            ->values();
    }

    protected function quantidadeSolicitada(Item $item, float $porte, int $seed): float
    {
        $base = match ($item->tipo_item?->value) {
            'cereal_derivado' => 18,
            'hortalica' => 14,
            'fruta' => 12,
            'carne_bovina', 'carne_suina', 'ave' => 16,
            'pescado' => 10,
            'laticinios' => 8,
            'bebida' => 22,
            'industrializado' => 7,
            default => 9,
        };

        $variacao = (($seed % 6) * 2.1) + (($item->getKey() % 5) * 0.9);

        return round(max(1.2, ($base + $variacao) * $porte), 3);
    }

    protected function estoqueInicial(Item $item, int $index): float
    {
        $base = match ($item->tipo_item?->value) {
            'cereal_derivado' => 280,
            'hortalica', 'fruta' => 150,
            'carne_bovina', 'carne_suina', 'ave' => 170,
            'pescado' => 120,
            'laticinios' => 110,
            'bebida' => 230,
            'industrializado' => 135,
            default => 100,
        };

        $variacao = (($index % 8) * 17) + (($item->getKey() % 9) * 6);

        return round(max(30, $base + $variacao), 3);
    }

    protected function quantidadeContratoBase(Item $item): float
    {
        $base = match ($item->tipo_item?->value) {
            'cereal_derivado' => 1800,
            'hortalica', 'fruta' => 1200,
            'carne_bovina', 'carne_suina', 'ave' => 1450,
            'pescado' => 900,
            'laticinios' => 840,
            'bebida' => 1600,
            'industrializado' => 780,
            default => 700,
        };

        return round($base + (($item->getKey() % 7) * 95), 3);
    }

    protected function precoReferencia(Item $item): float
    {
        $base = match ($item->tipo_item?->value) {
            'cereal_derivado' => 5.3,
            'hortalica' => 4.2,
            'fruta' => 4.8,
            'carne_bovina' => 21.5,
            'carne_suina' => 17.8,
            'ave' => 14.6,
            'pescado' => 24.9,
            'laticinios' => 8.9,
            'bebida' => 6.4,
            'industrializado' => 7.1,
            default => 5.5,
        };

        return round($base + (($item->getKey() % 5) * 0.85), 2);
    }

    protected function observacaoEscola(): string
    {
        return collect([
            'Reposicao regular para manter o cardapio planejado da semana.',
            'Solicitacao alinhada com o consumo medio da unidade.',
            'Pedido montado apos conferencia do estoque fisico da escola.',
            'Unidade precisa recompor itens de maior giro para o proximo ciclo.',
        ])->random();
    }

    protected function observacaoGestor(int $seed): string
    {
        return [
            'Pedido aprovado com ajuste fino de quantidades para atender toda a rota.',
            'Analise concluida com priorizacao dos itens mais criticos da unidade.',
            'Pedido liberado conforme saldo atual e cronograma logistico.',
            'Aprovacao concluida com redistribuicao parcial entre escolas da mesma rota.',
        ][$seed % 4];
    }

    protected function observacaoItemSolicitado(Item $item, int $seed): string
    {
        return [
            'Prioridade para o preparo do cardapio semanal com ' . mb_strtolower($item->nome) . '.',
            'Estoque da unidade entrou em faixa critica para este item.',
            'Solicitacao baseada no consumo medio observado no ultimo mes.',
            'Reposicao preventiva para nao comprometer o atendimento da cozinha.',
        ][$seed % 4];
    }

    protected function operarEmContexto(User $user, Carbon $momento, callable $callback): mixed
    {
        $guard = Auth::guard();
        $usuarioAnterior = $guard->user();
        $testNowAnterior = Date::getTestNow();

        Date::setTestNow($momento->copy());
        $guard->setUser($user);

        try {
            return $callback();
        } finally {
            Date::setTestNow($testNowAnterior);

            if ($usuarioAnterior) {
                $guard->setUser($usuarioAnterior);
            } else {
                $guard->logout();
            }
        }
    }
}
