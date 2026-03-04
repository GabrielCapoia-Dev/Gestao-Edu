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
    private int $numeroPedidosEmManutencaoExtra = 1000;
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
        $statuses = TipoStatus::where('ativo', true)->get();

        $sEmManutencao = TipoStatus::where('nome', 'Em Manutenção')->first();

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

        $sAberto = TipoStatus::where('nome', 'Em Aberto')->first();

        foreach ($statuses as $statusAtual) {

            for ($i = 1; $i <= $this->numeroDePedidos; $i++) {

                $solicitanteId = $this->rand($users);
                $responsavelId = $this->rand(array_values(array_filter($users, fn($id) => $id !== $solicitanteId)));

                $setorId = $this->rand($setores);

                $diasAtras    = rand(1, 90);
                $dataSolicit  = now()->subDays($diasAtras);
                $dataPrevista = (clone $dataSolicit)->addDays(rand(13, 25));

                $dataEntrega = $statusAtual->finaliza_pedido
                    ? (clone $dataPrevista)->addDays(rand(0, 5))
                    : null;

                $descPool = $statusAtual->finaliza_pedido
                    ? $this->descricoesConcluidas
                    : $this->descricoes;

                $descricao = sprintf($this->rand($descPool), rand(1, 20));

                $pedido = Pedido::create([
                    'descricao_pedido'      => $descricao,
                    'nome_solicitante'      => fake()->name(),
                    'tipo_manutencao_id'    => $this->rand($tipos),
                    'tipo_status_id'        => $statusAtual->id,
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
                ]);

                PedidoHistorico::create(
                    $this->hist(
                        $pedido,
                        null,
                        $sAberto,
                        $solicitanteId,
                        $setorId,
                        'Pedido criado.'
                    )
                );

                if ($statusAtual->id !== $sAberto?->id) {

                    PedidoHistorico::create(
                        $this->hist(
                            $pedido,
                            $sAberto,
                            $statusAtual,
                            $responsavelId,
                            $setorId,
                            "Alterado para {$statusAtual->nome}."
                        )
                    );
                }

                if ($statusAtual->finaliza_pedido) {

                    FeedbackPedido::create([
                        'pedido_id' => $pedido->id,
                        'valor' => $this->gerarNotaRealista(),
                        'descricao' => fake()->optional(0.8)->sentence(12),
                    ]);
                }
            }

            $this->command->info("✔ {$this->numeroDePedidos} pedidos criados — {$statusAtual->nome}");
        }

        if ($sEmManutencao) {

            $this->command->info("Gerando pedidos para status Em Manutenção garantindo 1 por escola...");

            $totalCriados = 0;

            // 1 pedido por escola
            foreach ($escolas as $escolaId) {

                $solicitanteId = $this->rand($users);
                $responsavelId = $this->rand(array_values(array_filter($users, fn($id) => $id !== $solicitanteId)));

                $setorId = $this->rand($setores);

                $diasAtras    = rand(1, 90);
                $dataSolicit  = now()->subDays($diasAtras);
                $dataPrevista = (clone $dataSolicit)->addDays(rand(10, 20));

                $descricao = sprintf($this->rand($this->descricoes), rand(1, 20));

                $pedido = Pedido::create([
                    'descricao_pedido'      => $descricao,
                    'nome_solicitante'      => fake()->name(),
                    'tipo_manutencao_id'    => $this->rand($tipos),
                    'tipo_status_id'        => $sEmManutencao->id,
                    'nivel_prioridade'      => $this->rand($prioridades),
                    'escola_id'             => $escolaId,
                    'solicitante_id'        => $solicitanteId,
                    'responsavel_id'        => $responsavelId,
                    'setor_id'              => $setorId,
                    'empresa_contratada_id' => $this->rand($empresas),
                    'data_solicitacao'      => $dataSolicit,
                    'data_prevista'         => $dataPrevista,
                    'data_entrega'          => null,
                    'ativo'                 => true,
                ]);

                PedidoHistorico::create(
                    $this->hist(
                        $pedido,
                        null,
                        $sAberto,
                        $solicitanteId,
                        $setorId,
                        'Pedido criado.'
                    )
                );

                PedidoHistorico::create(
                    $this->hist(
                        $pedido,
                        $sAberto,
                        $sEmManutencao,
                        $responsavelId,
                        $setorId,
                        'Pedido enviado para manutenção.'
                    )
                );

                $totalCriados++;
            }

            // Completar até numeroDePedidos se necessário
            while ($totalCriados < $this->numeroPedidosEmManutencaoExtra) {

                $escolaId = $this->rand($escolas);

                $solicitanteId = $this->rand($users);
                $responsavelId = $this->rand(array_values(array_filter($users, fn($id) => $id !== $solicitanteId)));

                $setorId = $this->rand($setores);

                $diasAtras    = rand(1, 90);
                $dataSolicit  = now()->subDays($diasAtras);
                $dataPrevista = (clone $dataSolicit)->addDays(rand(10, 20));

                $descricao = sprintf($this->rand($this->descricoes), rand(1, 20));

                $pedido = Pedido::create([
                    'descricao_pedido'      => $descricao,
                    'nome_solicitante'      => fake()->name(),
                    'tipo_manutencao_id'    => $this->rand($tipos),
                    'tipo_status_id'        => $sEmManutencao->id,
                    'nivel_prioridade'      => $this->rand($prioridades),
                    'escola_id'             => $escolaId,
                    'solicitante_id'        => $solicitanteId,
                    'responsavel_id'        => $responsavelId,
                    'setor_id'              => $setorId,
                    'empresa_contratada_id' => $this->rand($empresas),
                    'data_solicitacao'      => $dataSolicit,
                    'data_prevista'         => $dataPrevista,
                    'data_entrega'          => null,
                    'ativo'                 => true,
                ]);

                PedidoHistorico::create(
                    $this->hist(
                        $pedido,
                        null,
                        $sAberto,
                        $solicitanteId,
                        $setorId,
                        'Pedido criado.'
                    )
                );

                PedidoHistorico::create(
                    $this->hist(
                        $pedido,
                        $sAberto,
                        $sEmManutencao,
                        $responsavelId,
                        $setorId,
                        'Pedido enviado para manutenção.'
                    )
                );

                $totalCriados++;
            }

            $this->command->info("✔ {$totalCriados} pedidos criados — Em Manutenção");
        }

        $sConcluido = TipoStatus::where('nome', 'Concluído')->first();

        if ($sConcluido) {

            $this->command->info("Gerando {$this->numeroPedidosConcluidosExtras} pedidos concluídos extras...");

            for ($i = 0; $i < $this->numeroPedidosConcluidosExtras; $i++) {

                $solicitanteId = $this->rand($users);
                $responsavelId = $this->rand(array_values(array_filter($users, fn($id) => $id !== $solicitanteId)));

                $setorId = $this->rand($setores);

                $dataSolicit = now()
                    ->subMonths(rand(0, 24))
                    ->subDays(rand(0, 30))
                    ->setTime(rand(8, 18), rand(0, 59));

                $dataPrevista = (clone $dataSolicit)->addDays(rand(7, 30));
                $dataEntrega  = (clone $dataPrevista)->addDays(rand(-5, 10));

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

                PedidoHistorico::create([
                    'pedido_id' => $pedido->id,
                    'status_anterior_id' => null,
                    'status_novo_id' => $sAberto?->id,
                    'usuario_id' => $solicitanteId,
                    'setor_id' => $setorId,
                    'descricao_alteracao' => 'Pedido criado.',
                    'created_at' => $dataSolicit,
                    'updated_at' => $dataSolicit,
                ]);

                PedidoHistorico::create([
                    'pedido_id' => $pedido->id,
                    'status_anterior_id' => $sAberto?->id,
                    'status_novo_id' => $sConcluido->id,
                    'usuario_id' => $responsavelId,
                    'setor_id' => $setorId,
                    'descricao_alteracao' => 'Pedido concluído.',
                    'created_at' => $dataEntrega,
                    'updated_at' => $dataEntrega,
                ]);

                FeedbackPedido::create([
                    'pedido_id' => $pedido->id,
                    'valor' => $this->gerarNotaRealista(),
                    'descricao' => fake()->optional(0.8)->sentence(12),
                    'created_at' => $dataEntrega,
                    'updated_at' => $dataEntrega,
                ]);
            }

            $this->command->info("✔ {$this->numeroPedidosConcluidosExtras} pedidos concluídos extras criados.");
        }
    }

    private function gerarNotaRealista(): int
    {
        $rand = random_int(1, 100);

        return match (true) {
            $rand <= 10 => 1,
            $rand <= 30 => random_int(2, 3),
            default => random_int(4, 5),
        };
    }

    private function hist(
        Pedido $pedido,
        ?object $anterior,
        ?object $novo,
        int $userId,
        int $setorId,
        string $descricao
    ): array {
        return [
            'pedido_id' => $pedido->id,
            'status_anterior_id' => $anterior?->id,
            'status_novo_id' => $novo?->id,
            'usuario_id' => $userId,
            'setor_id' => $setorId,
            'descricao_alteracao' => $descricao,
        ];
    }

    private function rand(array $items): mixed
    {
        return $items[array_rand($items)];
    }
}
