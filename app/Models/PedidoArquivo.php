<?php

namespace App\Models;

use App\Models\Enums\TipoArquivoPedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
