<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pedido;
use App\Models\PedidoHistorico;
use App\Models\TipoStatus;
use App\Models\TipoManutencao;
use App\Models\Setor;
use App\Models\User;
use App\Models\Escola;
use App\Models\EmpresaContratada;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\FeedbackPedido;

class PedidoSeeder extends Seeder
{
    private int $numeroDePedidos = 10;
    private int $numeroPedidosConcluidosExtras = 1000;
    private array $descricoes = [
        'Tomada da sala %d não funciona.',
        'Vazamento na torneira do banheiro %d.',
        'Lâmpada queimada no corredor %d.',
        'Porta do bloco %d com dobradiça quebrada.',
        'Infiltração na parede da sala %d.',
        'Goteira no telhado do bloco %d.',
        'Calha obstruída na ala %d.',
        'Interruptor da sala %d com defeito.',
        'Rachaduras na parede do bloco %d.',
        'Janela quebrada na sala %d.',
        'Piso danificado na área %d.',
        'Disjuntor do quadro %d desarmando constantemente.',
        'Fossa do bloco %d transbordando.',
        'Pintura descascando na sala %d.',
        'Maçaneta da porta %d com defeito.',
        'Banheiro %d com descarga quebrada.',
        'Telha danificada no telhado setor %d.',
        'Caixa d\'água do bloco %d com vazamento.',
        'Grade da janela %d solta.',
        'Fiação exposta na sala %d.',
    ];

    private array $descricoesConcluidas = [
        'Reparo elétrico na sala %d concluído com sucesso.',
        'Vazamento na torneira do banheiro %d corrigido.',
        'Troca de lâmpadas no corredor %d realizada.',
        'Dobradiça da porta do bloco %d substituída.',
        'Infiltração na sala %d tratada e impermeabilizada.',
        'Reparo de goteira no telhado do bloco %d finalizado.',
        'Calha da ala %d limpa e desobstruída.',
        'Interruptor da sala %d substituído.',
        'Rachaduras do bloco %d vedadas e pintadas.',
        'Vidro da janela da sala %d trocado.',
    ];

    public function run(): void
    {
        // Busca cada status individualmente — evita problema de encoding com keyBy
        $sAberto      = TipoStatus::where('nome', 'Em Aberto')->first();
        $sAnalise     = TipoStatus::where('nome', 'Em Análise')->first();
        $sConcluido   = TipoStatus::where('nome', 'Concluído')->first();
        $sCancelado   = TipoStatus::where('nome', 'Cancelado')->first();

        $tipos    = TipoManutencao::where('ativo', true)->pluck('id')->toArray();
        $setores  = Setor::where('ativo', true)->pluck('id')->toArray();
        $users    = User::pluck('id')->toArray();
        $escolas  = Escola::where('ativo', true)->pluck('id')->toArray();
        $empresas = EmpresaContratada::where('ativo', true)->pluck('id')->toArray();

        $prioridades = [
            NivelEmergenciaPedido::INDEFINIDO,
            NivelEmergenciaPedido::EMERGENCIAL,
            NivelEmergenciaPedido::CORRETIVO,
            NivelEmergenciaPedido::PREVENTIVO,
        ];

        $grupos = [
            ['status' => $sAberto,      'historico' => fn($p, $u, $s) => [
                $this->hist($p, null,          $sAberto,      $u, $s, 'Pedido criado.'),
            ]],
            ['status' => $sAnalise,     'historico' => fn($p, $u, $s) => [
                $this->hist($p, null,          $sAberto,      $u, $s, 'Pedido criado.'),
                $this->hist($p, $sAberto,      $sAnalise,     $u, $s, 'Encaminhado para análise técnica.'),
            ]],
            ['status' => $sConcluido,   'historico' => fn($p, $u, $s) => [
                $this->hist($p, null,          $sAberto,      $u, $s, 'Pedido criado.'),
                $this->hist($p, $sAberto,      $sAnalise,     $u, $s, 'Aprovado para execução.'),
            ]],
            ['status' => $sCancelado,   'historico' => fn($p, $u, $s) => [
                $this->hist($p, null,          $sAberto,      $u, $s, 'Pedido criado.'),
                $this->hist($p, $sAberto,      $sCancelado,   $u, $s, 'Cancelado por duplicidade ou solicitação.'),
            ]],
        ];




        foreach ($grupos as $grupo) {
            $statusAtual = $grupo['status'];

            if (!$statusAtual) {
                $this->command->warn("Status não encontrado no banco. Pulando grupo.");
                continue;
            }

            for ($i = 1; $i <= $this->numeroDePedidos; $i++) {
                $solicitanteId  = $this->rand($users);
                $responsavelId  = $this->rand(array_values(array_filter($users, fn($id) => $id !== $solicitanteId)));
                $setorEducacao = Setor::where('nome', 'Educação')->first();
                $setorObras = Setor::where('nome', 'Obras')->first();
                $setorServicos = Setor::where('nome', 'Serviços Publicos')->first();

                // =========================
                // REGRA DE SETOR POR STATUS
                // =========================

                if ($statusAtual->nome === 'Em Aberto') {

                    // Apenas Educação
                    $setorId = $setorEducacao->id;
                } elseif ($statusAtual->nome === 'Encaminhado ao Setor') {

                    // Apenas Obras
                    $setorId = $setorObras->id;
                } else {

                    // Outros status podem variar
                    $setorId = $this->rand([
                        $setorEducacao->id,
                        $setorObras->id,
                        $setorServicos->id,
                    ]);
                }

                $diasAtras    = rand(1, 90);
                $dataSolicit  = now()->subDays($diasAtras);
                $dataPrevista = (clone $dataSolicit)->addDays(rand(13, 25));
                $dataEntrega  = $statusAtual->finaliza_pedido
                    ? (clone $dataPrevista)->addDays(rand(0, 5))
                    : null;

                $descPool  = $statusAtual->finaliza_pedido ? $this->descricoesConcluidas : $this->descricoes;
                $descricao = sprintf($this->rand($descPool), rand(1, 20));

                $pedido = Pedido::create([
                    'descricao_pedido'      => $descricao,
                    'nome_solicitante' => fake()->name(),
                    'tipo_manutencao_id'    => $this->rand($tipos),
                    'tipo_status_id'        => $statusAtual->id,
                    'nivel_prioridade'      => $this->rand($prioridades),
                    'escola_id'             => $this->rand($escolas),
                    'solicitante_id' => $solicitanteId,
                    'responsavel_id' => $responsavelId,
                    'setor_id'              => $setorId,
                    'empresa_contratada_id' => $this->rand($empresas),
                    'data_solicitacao'      => $dataSolicit,
                    'data_prevista'         => $dataPrevista,
                    'data_entrega'          => $dataEntrega,
                    'ativo'                 => true,
                ]);

                foreach ($grupo['historico']($pedido, $solicitanteId, $setorId) as $h) {
                    PedidoHistorico::create($h);
                }

                // =========================
                // Criar Feedback se Concluído
                // =========================

                if ($statusAtual->nome === 'Concluído') {

                    FeedbackPedido::create([
                        'pedido_id' => $pedido->id,
                        'valor' => $this->gerarNotaRealista(),
                        'descricao' => fake()->optional(0.8)->sentence(12),
                    ]);
                }
            }

            $this->command->info("✔ 60 pedidos criados — {$statusAtual->nome}");
        }

        // =======================================================
        // GERAR 1000 PEDIDOS CONCLUÍDOS COM DISTRIBUIÇÃO TEMPORAL
        // =======================================================

        if ($sConcluido) {

            $this->command->info("Gerando {$this->numeroPedidosConcluidosExtras} pedidos concluídos para gráfico temporal...");

            for ($i = 0; $i < $this->numeroPedidosConcluidosExtras; $i++) {

                $solicitanteId = $this->rand($users);
                $responsavelId = $this->rand(array_values(array_filter($users, fn($id) => $id !== $solicitanteId)));

                $setorId = $this->rand($setores);

                // 🔥 DISTRIBUIÇÃO TEMPORAL REALISTA (últimos 24 meses)
                $dataSolicit = now()
                    ->subMonths(rand(0, 24))
                    ->subDays(rand(0, 30))
                    ->setTime(rand(8, 18), rand(0, 59));

                $dataPrevista = (clone $dataSolicit)->addDays(rand(7, 30));

                // Pode entregar antes ou depois da previsão
                $dataEntrega = (clone $dataPrevista)->addDays(rand(-5, 10));

                $descricao = sprintf(
                    $this->rand($this->descricoesConcluidas),
                    rand(1, 50)
                );

                $pedido = Pedido::create([
                    'descricao_pedido'      => $descricao,
                    'nome_solicitante'      => fake()->name(),
                    'tipo_manutencao_id'    => $this->rand($tipos),
                    'tipo_status_id'        => $sConcluido->id,
                    'nivel_prioridade'      => $this->rand($prioridades),
                    'escola_id'             => $this->rand($escolas),
                    'solicitante_id'        => $solicitanteId,
                    'responsavel_id'        => $responsavelId,
                    'setor_id'              => $setorId,
                    'empresa_contratada_id' => $this->rand($empresas),
                    'data_solicitacao'      => $dataSolicit,
                    'data_prevista'         => $dataPrevista,
                    'data_entrega'          => $dataEntrega,
                    'ativo'                 => true,
                    'created_at'            => $dataSolicit,
                    'updated_at'            => $dataEntrega,
                ]);

                // Histórico coerente com timeline
                PedidoHistorico::create([
                    'pedido_id'           => $pedido->id,
                    'status_anterior_id'  => null,
                    'status_novo_id'      => $sAberto?->id,
                    'usuario_id'          => $solicitanteId,
                    'setor_id'            => $setorId,
                    'descricao_alteracao' => 'Pedido criado.',
                    'created_at'          => $dataSolicit,
                    'updated_at'          => $dataSolicit,
                ]);

                PedidoHistorico::create([
                    'pedido_id'           => $pedido->id,
                    'status_anterior_id'  => $sAberto?->id,
                    'status_novo_id'      => $sConcluido->id,
                    'usuario_id'          => $responsavelId,
                    'setor_id'            => $setorId,
                    'descricao_alteracao' => 'Pedido concluído.',
                    'created_at'          => $dataEntrega,
                    'updated_at'          => $dataEntrega,
                ]);

                // Feedback sincronizado com data de entrega
                FeedbackPedido::create([
                    'pedido_id'  => $pedido->id,
                    'valor'      => $this->gerarNotaRealista(),
                    'descricao'  => fake()->optional(0.8)->sentence(12),
                    'created_at' => $dataEntrega,
                    'updated_at' => $dataEntrega,
                ]);
            }

            $this->command->info("✔ {$this->numeroPedidosConcluidosExtras} pedidos concluídos extras criados.");
        }
    }

    private function gerarNotaRealista(): int
    {
        $rand = rand(1, 100);

        return match (true) {
            $rand <= 10 => rand(0, 4),   // 10% ruim
            $rand <= 30 => rand(5, 7),   // 20% médio
            default => rand(8, 10),      // 70% bom
        };
    }

    private function hist(
        Pedido $pedido,
        ?object $anterior,
        ?object $novo,      // era object, agora ?object
        int $userId,
        int $setorId,
        string $descricao
    ): array {
        return [
            'pedido_id'           => $pedido->id,
            'status_anterior_id'  => $anterior?->id,
            'status_novo_id'      => $novo?->id,
            'usuario_id'          => $userId,
            'setor_id'            => $setorId,
            'descricao_alteracao' => $descricao,
        ];
    }

    private function rand(array $items): mixed
    {
        return $items[array_rand($items)];
    }
}
