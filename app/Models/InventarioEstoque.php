<?php

namespace App\Models;

use App\Models\Enums\MotivoBaixa;
use App\Models\Enums\TipoMovimentacao;
use App\Services\Inventario\BalancoInventarioBloqueioService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventarioEstoque extends Model
{
    protected $table = 'inventario_estoques';

    protected $fillable = [
        'inventario_id',
        'item_id',
        'quantidade',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
    ];

    protected function user()
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user;
    }

    public function inventario()
    {
        return $this->belongsTo(Inventario::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function movimentacoes()
    {
        return $this->hasMany(InventarioMovimentacao::class, 'inventario_estoque_id');
    }

    public function baixas()
    {
        return $this->hasMany(InventarioBaixa::class, 'inventario_estoque_id');
    }

    public function balancoItens()
    {
        return $this->hasMany(BalancoInventarioItem::class, 'inventario_estoque_id');
    }

    public function entrada(
        float $quantidade,
        ?int $inventarioPedidoId = null,
        ?string $observacao = null,
        ?int $ignorarBalancoId = null,
    ): InventarioMovimentacao {
        // Impacto: conferirEntrega() usa esta entrada para refletir o recebimento da escola. Alterar aqui muda saldo escolar, relatorios e balancos de inventario.
        $this->assertItemDisponivel($ignorarBalancoId);
        $this->increment('quantidade', $quantidade);

        return $this->movimentacoes()->create([
            'tipo' => TipoMovimentacao::Entrada,
            'quantidade' => $quantidade,
            'inventario_pedido_id' => $inventarioPedidoId,
            'observacao' => $observacao,
            'registrado_por' => $this->user()?->name,
        ]);
    }

    public function saida(
        float $quantidade,
        ?int $inventarioPedidoId = null,
        ?string $observacao = null,
        ?int $ignorarBalancoId = null,
    ): InventarioMovimentacao {
        // Impacto: baixas e balancos de inventario dependem desta validacao para nao permitir saldo escolar negativo.
        $this->assertItemDisponivel($ignorarBalancoId);
        $saldoAtual = (float) $this->quantidade;

        if ($quantidade > $saldoAtual) {
            throw new \DomainException('Quantidade de saída maior que o saldo em inventário.');
        }

        $this->decrement('quantidade', $quantidade);

        return $this->movimentacoes()->create([
            'tipo' => TipoMovimentacao::Saida,
            'quantidade' => $quantidade,
            'inventario_pedido_id' => $inventarioPedidoId,
            'observacao' => $observacao,
            'registrado_por' => $this->user()?->name,
        ]);
    }

    public function registrarBaixa(float $quantidade, MotivoBaixa $motivo, string $descricao, ?int $ignorarBalancoId = null): InventarioBaixa
    {
        // Impacto: esta baixa combina movimentacao e historico. Separar as duas gravacoes quebra as telas de baixas e o saldo posterior auditado.
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade de baixa inválida.');
        }

        if ($quantidade > (float) $this->quantidade) {
            throw new \DomainException('Quantidade de baixa maior que o saldo em inventário.');
        }

        return DB::transaction(function () use ($quantidade, $motivo, $descricao, $ignorarBalancoId) {
            $this->refresh();

            $saldoAnterior = (float) $this->quantidade;

            if ($quantidade > $saldoAnterior) {
                throw new \DomainException('Quantidade de baixa maior que o saldo em inventário.');
            }

            $this->saida(
                quantidade: $quantidade,
                inventarioPedidoId: null,
                observacao: "Baixa de inventario: {$motivo->label()} - {$descricao}",
                ignorarBalancoId: $ignorarBalancoId,
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

    protected function assertItemDisponivel(?int $ignorarBalancoId = null): void
    {
        // Impacto: protege o snapshot do balanco escolar. Se ignorado em operacoes comuns, a contagem pode fechar sobre um saldo que mudou durante o processo.
        if (! $this->inventario_id || ! $this->item_id) {
            return;
        }

        app(BalancoInventarioBloqueioService::class)->assertItemDisponivel(
            (int) $this->inventario_id,
            (int) $this->item_id,
            $ignorarBalancoId,
        );
    }
}
