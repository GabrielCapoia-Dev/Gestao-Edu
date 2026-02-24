<?php

namespace App\Http\Controllers;

use App\Models\PedidoArquivo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

class PedidoArquivoController extends Controller
{
    protected function pedidosDisk(): FilesystemAdapter
    {
        return Storage::disk('public');
    }

    public function download(PedidoArquivo $pedidoArquivo)
    {
        $this->authorize('download', $pedidoArquivo);

        $disk = $this->pedidosDisk();
        $path = $pedidoArquivo->caminho;

        if (blank($path) || ! is_string($path)) {
            return abort(404);
        }

        if (! $disk->exists($path)) {
            return abort(404);
        }

        $filename = $this->makeFilename($pedidoArquivo);

        return $disk->download($path, $filename);
    }

    protected function makeFilename(PedidoArquivo $arquivo): string
    {
        $pedido = $arquivo->pedido;

        $protocolo = str($pedido->numero_protocolo)
            ->replace('/', '-');

        $tipo = str($arquivo->tipo_arquivo->value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-');

        $ext = pathinfo($arquivo->caminho, PATHINFO_EXTENSION);

        return "{$protocolo}-{$tipo}.{$ext}";
    }
}