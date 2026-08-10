<?php

namespace App\Models;

use App\Support\UserActorSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;


class PedidoHistorico extends Model
{
    protected $table = 'pedido_historicos';

    protected $fillable = [

        'pedido_id', // Pedido que sofreu alteração

        'status_anterior_id', // Status antes da alteração
        'status_novo_id', // Status após alteração

        'usuario_id', // Último usuário responsável que realizou a alteração
        'setor_id', // Setor que realizou a alteração

        'descricao_alteracao', // Descrição detalhando o que aconteceu
        'usuario_id_legado',
        'usuario_nome_snapshot',
        'usuario_email_snapshot',
    ];

    protected static function booted(): void
    {
        static::creating(function (PedidoHistorico $historico): void {
            $userId = (int) $historico->usuario_id;

            if (! $userId) {
                return;
            }

            $historico->usuario_id_legado ??= $userId;
            $snapshot = UserActorSnapshot::values(UserActorSnapshot::find($userId));
            $historico->usuario_nome_snapshot ??= $snapshot['nome'];
            $historico->usuario_email_snapshot ??= $snapshot['email'];
        });
    }


    public static function registrarAlteracaoArquivo(
        Pedido $pedido,
        string $descricao,
        ?int $usuarioId = null
    ): self {
        return self::create([
            'pedido_id'          => $pedido->id,
            'status_anterior_id' => null,
            'status_novo_id'     => $pedido->tipo_status_id,
            'usuario_id'         => $usuarioId ?? Auth::id() ?? $pedido->responsavel_id ?? $pedido->solicitante_id,
            'setor_id'           => $pedido->setor_id,
            'descricao_alteracao' => $descricao,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function statusAnterior()
    {
        return $this->belongsTo(TipoStatus::class, 'status_anterior_id');
    }

    public function statusNovo()
    {
        return $this->belongsTo(TipoStatus::class, 'status_novo_id');
    }

    public function usuario()
    {
        return UserActorSnapshot::relation($this->belongsTo(User::class, 'usuario_id'));
    }

    public function setor()
    {
        return $this->belongsTo(Setor::class, 'setor_id');
    }

    public function usuarioNomeExibicao(): string
    {
        return UserActorSnapshot::displayName(
            $this->usuario,
            $this->usuario_nome_snapshot,
            $this->usuario_id_legado,
            $this->usuario_id,
        );
    }

    public function usuarioEmailExibicao(): ?string
    {
        return UserActorSnapshot::displayEmail($this->usuario, $this->usuario_email_snapshot);
    }
}
