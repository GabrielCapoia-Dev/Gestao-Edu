<?php

namespace App\Models;

use App\Models\Enums\TipoArquivoPedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\PedidoHistorico;


class PedidoArquivo extends Model
{
    use HasFactory;

    protected $table = 'pedido_arquivos';

    protected $fillable = [

        'pedido_id', // Pedido relacionado
        'usuario_id', // Usuário que enviou

        'tipo_arquivo', // Classificação do arquivo (ex: fotos_problema, laudo, etc.)

        'caminho', // Caminho no storage
        'nome_original', // Nome enviado
        'mime_type', // image/jpeg, application/pdf

        'descricao', // Descrição opcional
    ];

    protected $casts = [
        'tipo_arquivo' => TipoArquivoPedido::class,
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }


    protected static function booted()
    {
        static::created(function ($arquivo) {

            PedidoHistorico::registrarAlteracaoArquivo(
                $arquivo->pedido,
                "Arquivo adicionado: {$arquivo->nome_original}"
            );
        });

        static::updated(function ($arquivo) {

            $mudouArquivo   = $arquivo->wasChanged('caminho');
            $mudouDescricao = $arquivo->wasChanged('descricao');
            $mudouTipo      = $arquivo->wasChanged('tipo_arquivo');

            if (! $mudouArquivo && ! $mudouDescricao && ! $mudouTipo) {
                return;
            }

            $descricao = null;

            // 🔵 Se trocou o arquivo
            if ($mudouArquivo) {

                $original = basename($arquivo->getOriginal('caminho'));
                $novo     = basename($arquivo->caminho);

                $descricao = "Arquivo substituído, De: {$original} | Para: {$novo}";
            }

            // 🔵 Se só alterou descrição
            elseif ($mudouDescricao) {

                $descricao = "Descrição do arquivo atualizada: {$arquivo->nome_original}";
            }

            // 🔵 Se alterou tipo
            elseif ($mudouTipo) {

                $original = $arquivo->getOriginal('tipo_arquivo')?->label() ?? '—';
                $novo     = $arquivo->tipo_arquivo?->label() ?? '—';

                $descricao = "Tipo do arquivo alterado, De: {$original} | Para: {$novo}";
            }

            if ($descricao) {
                PedidoHistorico::registrarAlteracaoArquivo(
                    $arquivo->pedido,
                    $descricao
                );
            }
        });

        static::deleted(function ($arquivo) {

            PedidoHistorico::registrarAlteracaoArquivo(
                $arquivo->pedido,
                "Arquivo removido: {$arquivo->nome_original}"
            );
        });
    }
}
