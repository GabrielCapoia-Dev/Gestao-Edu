<?php

namespace App\Models;

use App\Models\Enums\NivelEmergenciaPedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Enums\TipoArquivoPedido;
use App\Models\EmpresaContratada;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = [

        'numero_protocolo', // Gerado automaticamente
        'pedido_principal_id',
        'is_pedido_adicional',
        'descricao_pedido', // Descrição do problema

        'nome_solicitante', // Nome do solicitante do problema

        'tipo_manutencao_id', // Tipo de manutenção
        'tipo_status_id', // Status atual do workflow

        'nivel_prioridade', // Enum NivelEmergenciaPedido

        'escola_id', // Escola relacionada
        'solicitante_id', // Usuário que criou
        'responsavel_id', // Primeiro responsável

        'setor_id', // Setor atual
        'empresa_contratada_id', // Empresa externa (se houver)
        'valor_custo', // Valor gasto no pedido

        'data_solicitacao',
        'data_identificacao_problema',
        'data_prevista',
        'data_entrega',

        'quantidade_dias_prorrogado',

        'ativo',
    ];

    protected $casts = [
        'data_solicitacao' => 'date',
        'data_identificacao_problema' => 'date',
        'data_prevista' => 'date',
        'data_entrega' => 'date',
        'valor_custo' => 'decimal:2',
        'ativo' => 'boolean',
        'is_pedido_adicional' => 'boolean',
        'nivel_prioridade' => NivelEmergenciaPedido::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeNenhum(Builder $query): Builder
    {
        return $query->whereKey(-1);
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::creating(function ($pedido) {

            if (empty($pedido->numero_protocolo)) {

                $ano = $pedido->created_at
                    ? $pedido->created_at->year
                    : now()->year;

                $pedido->numero_protocolo = self::gerarProtocolo($ano);
            }
        });
    }

    // public static function gerarProtocolo(): string
    // {
    //     $ano = now()->year;

    //     $ultimo = self::whereYear('created_at', $ano)->count();

    //     return sprintf('%s/%05d', $ano, $ultimo + 1);
    // }


    public static function gerarProtocolo(?int $ano = null): string
    {
        $ano = $ano ?? now()->year;

        $ultimoNumero = self::query()
            ->whereYear('created_at', $ano)
            ->pluck('numero_protocolo')
            ->map(fn (?string $protocolo): int => (int) Str::afterLast((string) $protocolo, '/'))
            ->max();

        $proximo = ($ultimoNumero ?? 0) + 1;

        return sprintf('%s/%05d', $ano, $proximo);
    }


    public function ultimoHistorico()
    {
        return $this->hasOne(PedidoHistorico::class)
            ->latestOfMany('created_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function feedbacks()
    {
        return $this->hasMany(FeedbackPedido::class);
    }

    public function ultimoFeedback()
    {
        return $this->hasOne(FeedbackPedido::class)->latestOfMany();
    }

    public function pedidoPrincipal()
    {
        return $this->belongsTo(self::class, 'pedido_principal_id');
    }

    public function pedidosAdicionais()
    {
        return $this->hasMany(self::class, 'pedido_principal_id')->latest();
    }

    public function problemas()
    {
        return $this->hasMany(PedidoProblema::class);
    }

    public function tipoManutencao()
    {
        return $this->belongsTo(TipoManutencao::class);
    }

    public function tipoStatus()
    {
        return $this->belongsTo(TipoStatus::class);
    }

    public function escola()
    {
        return $this->belongsTo(Escola::class);
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function responsavel()
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class);
    }

    public function historicos()
    {
        return $this->hasMany(PedidoHistorico::class)
            ->with(['statusAnterior', 'statusNovo', 'usuario'])
            ->orderByDesc('created_at');
    }

    public function arquivos()
    {
        return $this->hasMany(PedidoArquivo::class)->latest();
    }

    public function fotos()
    {
        return $this->hasMany(PedidoArquivo::class)
            ->where('tipo_arquivo', TipoArquivoPedido::FOTOS_PROBLEMA);
    }
    public function arquivos_sem_fotos_problema()
    {
        return $this->hasMany(PedidoArquivo::class)
            ->where('tipo_arquivo', '!=', TipoArquivoPedido::FOTOS_PROBLEMA);
    }

    public function empresaContratada()
    {
        return $this->belongsTo(EmpresaContratada::class, 'empresa_contratada_id');
    }
}
