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

class PedidoSeeder extends Seeder
{
    private int $numeroDePedidos = 10;
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
                $setorId        = $this->rand($setores);

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
            }

            $this->command->info("✔ 60 pedidos criados — {$statusAtual->nome}");
        }
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
