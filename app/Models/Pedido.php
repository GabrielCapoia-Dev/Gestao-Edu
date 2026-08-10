<?php

namespace App\Models;

use App\Models\Enums\NivelEmergenciaPedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Enums\TipoArquivoPedido;
use App\Models\EmpresaContratada;
use App\Support\UserActorSnapshot;
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
        'comentario_gestor',
        'comentario_gestor_user_id',
        'comentario_gestor_at',

        'nome_solicitante', // Nome do solicitante do problema

        'tipo_manutencao_id', // Tipo de manutenção
        'tipo_status_id', // Status atual do workflow

        'nivel_prioridade', // Enum NivelEmergenciaPedido

        'escola_id', // Escola relacionada
        'solicitante_id', // Usuário que criou
        'responsavel_id', // Primeiro responsável

        'setor_id', // Setor atual
        'setor_origem_id', // Setor que originou o pedido
        'empresa_contratada_id', // Empresa externa (se houver)
        'valor_custo', // Valor gasto no pedido

        'data_solicitacao',
        'data_identificacao_problema',
        'data_prevista',
        'data_entrega',

        'quantidade_dias_prorrogado',

        'escola_id_legado',
        'escola_nome_snapshot',
        'escola_codigo_snapshot',
        'solicitante_id_legado',
        'solicitante_nome_snapshot',
        'solicitante_email_snapshot',
        'responsavel_id_legado',
        'responsavel_nome_snapshot',
        'responsavel_email_snapshot',
        'comentario_gestor_user_id_legado',
        'comentario_gestor_user_nome_snapshot',
        'comentario_gestor_user_email_snapshot',
        'ativo',
    ];

    protected $casts = [
        'data_solicitacao' => 'date',
        'data_identificacao_problema' => 'date',
        'data_prevista' => 'date',
        'data_entrega' => 'date',
        'comentario_gestor_at' => 'datetime',
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

            if (blank($pedido->setor_origem_id)) {
                $pedido->setor_origem_id = ($pedido->escola_id
                    ? Escola::query()->whereKey($pedido->escola_id)->value('setor_id')
                    : null)
                    ?: User::query()->whereKey($pedido->solicitante_id)->value('setor_id');
            }

            self::sincronizarSnapshots($pedido);
        });

        static::updating(fn (Pedido $pedido) => self::sincronizarSnapshots($pedido));
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

    public function feedbackItens()
    {
        return $this->hasMany(FeedbackPedidoItem::class);
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
        return UserActorSnapshot::relation($this->belongsTo(User::class, 'solicitante_id'));
    }

    public function responsavel()
    {
        return UserActorSnapshot::relation($this->belongsTo(User::class, 'responsavel_id'));
    }

    public function comentarioGestorUsuario()
    {
        return UserActorSnapshot::relation($this->belongsTo(User::class, 'comentario_gestor_user_id'));
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class);
    }

    public function setorOrigem()
    {
        return $this->belongsTo(Setor::class, 'setor_origem_id');
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

    public function fotosConclusao()
    {
        return $this->hasMany(PedidoArquivo::class)
            ->where('tipo_arquivo', TipoArquivoPedido::FOTOS_CONCLUSAO);
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

    public function solicitanteUsuarioNomeExibicao(): string
    {
        return UserActorSnapshot::displayName(
            $this->solicitante,
            $this->solicitante_nome_snapshot,
            $this->solicitante_id_legado,
            $this->solicitante_id,
        );
    }

    public function solicitanteUsuarioEmailExibicao(): ?string
    {
        return UserActorSnapshot::displayEmail($this->solicitante, $this->solicitante_email_snapshot);
    }

    public function responsavelNomeExibicao(): string
    {
        return UserActorSnapshot::displayName(
            $this->responsavel,
            $this->responsavel_nome_snapshot,
            $this->responsavel_id_legado,
            $this->responsavel_id,
        );
    }

    public function comentarioGestorUsuarioNomeExibicao(): string
    {
        return UserActorSnapshot::displayName(
            $this->comentarioGestorUsuario,
            $this->comentario_gestor_user_nome_snapshot,
            $this->comentario_gestor_user_id_legado,
            $this->comentario_gestor_user_id,
        );
    }

    public function escolaNomeExibicao(): string
    {
        return filled($this->escola?->nome)
            ? (string) $this->escola->nome
            : (filled($this->escola_nome_snapshot) ? (string) $this->escola_nome_snapshot : 'Escola não disponível');
    }

    private static function sincronizarSnapshots(Pedido $pedido): void
    {
        self::sincronizarSnapshotUsuario(
            $pedido,
            'solicitante_id',
            'solicitante_id_legado',
            'solicitante_nome_snapshot',
            'solicitante_email_snapshot',
        );
        self::sincronizarSnapshotUsuario(
            $pedido,
            'responsavel_id',
            'responsavel_id_legado',
            'responsavel_nome_snapshot',
            'responsavel_email_snapshot',
        );
        self::sincronizarSnapshotUsuario(
            $pedido,
            'comentario_gestor_user_id',
            'comentario_gestor_user_id_legado',
            'comentario_gestor_user_nome_snapshot',
            'comentario_gestor_user_email_snapshot',
        );

        $escolaId = (int) $pedido->escola_id;

        if (! $escolaId) {
            return;
        }

        $escolaMudou = $pedido->isDirty('escola_id');
        $pedido->escola_id_legado = $escolaMudou || blank($pedido->escola_id_legado)
            ? $escolaId
            : $pedido->escola_id_legado;

        if (! $escolaMudou && filled($pedido->escola_nome_snapshot) && filled($pedido->escola_codigo_snapshot)) {
            return;
        }

        $escola = Escola::query()->find($escolaId);

        if ($escola) {
            $pedido->escola_nome_snapshot = $escola->nome;
            $pedido->escola_codigo_snapshot = $escola->codigo;
        }
    }

    private static function sincronizarSnapshotUsuario(
        Pedido $pedido,
        string $idColumn,
        string $legacyColumn,
        string $nameColumn,
        string $emailColumn,
    ): void {
        $userId = (int) $pedido->{$idColumn};

        if (! $userId) {
            return;
        }

        $actorMudou = $pedido->isDirty($idColumn);
        $pedido->{$legacyColumn} = $actorMudou || blank($pedido->{$legacyColumn})
            ? $userId
            : $pedido->{$legacyColumn};

        if (! $actorMudou && filled($pedido->{$nameColumn}) && filled($pedido->{$emailColumn})) {
            return;
        }

        $snapshot = UserActorSnapshot::values(UserActorSnapshot::find($userId));

        if ($snapshot['nome']) {
            $pedido->{$nameColumn} = $snapshot['nome'];
        }

        if ($snapshot['email']) {
            $pedido->{$emailColumn} = $snapshot['email'];
        }
    }
}
