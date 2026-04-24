<?php

namespace App\Models;

use App\Models\Enums\MotivoBaixa;
use App\Services\Estoque\BalancoEstoqueBloqueioService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class Estoque extends Model
{
    protected $table = 'estoque';

    protected $fillable = [
        'item_id',
        'quantidade',
        'quantidade_reservada',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'quantidade_reservada' => 'decimal:3',
    ];

    protected function user()
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function movimentacoes()
    {
        return $this->hasMany(EstoqueMovimentacao::class);
    }

    public function baixas()
    {
        return $this->hasMany(BaixasEstoques::class);
    }

    public function getQuantidadeDisponivelAttribute(): float
    {
        // Impacto: este calculo e usado por baixas, reservas de romaneio e relatorios; alterar para permitir saldo negativo ou ignorar reservas pode liberar entregas sem estoque fisico.
        return round(max(0, (float) $this->quantidade - (float) $this->quantidade_reservada), 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Adiciona quantidade ao saldo do estoque e registra a movimentação.
     */
    public function entrada(
        float $quantidade,
        ?int $pedidoMerendaId = null,
        ?string $observacao = null,
        ?int $ignorarBalancoId = null,
    ): EstoqueMovimentacao
    {
        // Impacto: BalancoEstoqueService::concluir() usa este metodo para corrigir sobra de contagem. Se a movimentacao deixar de ser registrada aqui, relatorios e auditoria perdem rastreabilidade.
        $this->assertItemDisponivel($ignorarBalancoId);
        $this->increment('quantidade', $quantidade);

        return $this->movimentacoes()->create([
            'tipo'               => \App\Models\Enums\TipoMovimentacao::Entrada,
            'quantidade'         => $quantidade,
            'pedido_merenda_id'  => $pedidoMerendaId,
            'observacao'         => $observacao,
            'registrado_por'     => $this->user()?->name,
        ]);
    }

    /**
     * Remove quantidade do saldo do estoque e registra a movimentação.
     */
    public function saida(
        float $quantidade,
        ?int $pedidoMerendaId = null,
        ?string $observacao = null,
        ?int $ignorarBalancoId = null,
    ): EstoqueMovimentacao
    {
        // Impacto: baixas, conclusao de balanco e confirmacao de entrega reservada dependem desta saida. Alterar o decremento ou o bloqueio por balanco pode quebrar saldo e historico.
        $this->assertItemDisponivel($ignorarBalancoId);
        $this->decrement('quantidade', $quantidade);

        return $this->movimentacoes()->create([
            'tipo'               => \App\Models\Enums\TipoMovimentacao::Saida,
            'quantidade'         => $quantidade,
            'pedido_merenda_id'  => $pedidoMerendaId,
            'observacao'         => $observacao,
            'registrado_por'     => $this->user()?->name,
        ]);
    }

    /**
     * Registra uma baixa operacional de estoque.
     */
    public function registrarBaixa(float $quantidade, MotivoBaixa $motivo, string $descricao): BaixasEstoques
    {
        // Impacto: a baixa cria historico proprio e tambem chama saida(). Se este fluxo for separado, telas de historico podem mostrar saldo diferente da movimentacao real.
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade de baixa invalida.');
        }

        $this->assertItemDisponivel();

        if ($quantidade > $this->quantidade_disponivel) {
            throw new \DomainException('Quantidade de baixa maior que o saldo em estoque.');
        }

        return DB::transaction(function () use ($quantidade, $motivo, $descricao) {
            $this->refresh();

            $saldoAnterior = (float) $this->quantidade;

            // Impacto: esta segunda validacao ocorre dentro da transacao apos refresh; remover pode permitir corrida entre reserva/baixa e gerar saldo reservado inconsistente.
            if ($quantidade > $this->quantidade_disponivel) {
                throw new \DomainException('Quantidade de baixa maior que o saldo em estoque.');
            }

            $this->saida(
                quantidade: $quantidade,
                pedidoMerendaId: null,
                observacao: "Baixa de estoque: {$motivo->label()} - {$descricao}",
            );

            $this->refresh();

            return $this->baixas()->create([
                'quantidade' => $quantidade,
                'motivo' => $motivo,
                'descricao' => $descricao,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => (float) $this->quantidade,
                'registrado_por' => $this->user()?->name,
            ]);
        });
    }

    public function reservar(float $quantidade, ?string $observacao = null): void
    {
        // Impacto: InventarioPedidoService::gerarRomaneio() depende desta reserva para bloquear saldo da matriz antes da conferencia da escola.
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade de reserva invalida.');
        }

        $this->assertItemDisponivel();
        $this->refresh();

        if ($quantidade > $this->quantidade_disponivel) {
            throw new \DomainException('Quantidade de reserva maior que o saldo disponivel em estoque.');
        }

        $this->increment('quantidade_reservada', $quantidade);
    }

    public function confirmarEntregaReservada(float $quantidadeReservada, float $quantidadeEntregue, ?string $observacao = null): void
    {
        // Impacto: InventarioPedidoService::conferirEntrega() usa este metodo para baixar a matriz e liberar reserva. Alterar a ordem pode deixar reserva presa ou duplicar saida.
        if ($quantidadeReservada <= 0) {
            throw new \InvalidArgumentException('Quantidade reservada invalida.');
        }

        if ($quantidadeEntregue < 0) {
            throw new \InvalidArgumentException('Quantidade entregue invalida.');
        }

        DB::transaction(function () use ($quantidadeReservada, $quantidadeEntregue, $observacao) {
            $this->assertItemDisponivel();
            $this->refresh();

            if ($quantidadeReservada > (float) $this->quantidade_reservada) {
                throw new \DomainException('Quantidade reservada maior que o saldo reservado em estoque.');
            }

            if ($quantidadeEntregue > (float) $this->quantidade) {
                throw new \DomainException('Quantidade entregue maior que o saldo em estoque.');
            }

            $this->decrement('quantidade_reservada', $quantidadeReservada);

            if ($quantidadeEntregue > 0) {
                $this->saida(
                    quantidade: $quantidadeEntregue,
                    pedidoMerendaId: null,
                    observacao: $observacao,
                );
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeDoItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    protected function assertItemDisponivel(?int $ignorarBalancoId = null): void
    {
        // Impacto: este guard protege entrada, saida, reserva e baixa durante balancos. Ignorar este bloqueio fora do BalancoEstoqueService pode corromper o snapshot da contagem.
        if (! $this->item_id) {
            return;
        }

        app(BalancoEstoqueBloqueioService::class)->assertItemDisponivel((int) $this->item_id, $ignorarBalancoId);
    }
}
