<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoArquivo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use ZipArchive;

class PedidoArquivoController extends Controller
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

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

    public function exportImages(Pedido $pedido)
    {
        $this->authorize('exportImages', $pedido);

        $pedido->load('arquivos');

        $disk = $this->pedidosDisk();
        $imagens = $pedido->arquivos
            ->filter(fn (PedidoArquivo $arquivo): bool => $this->isImage($arquivo) && $disk->exists($arquivo->caminho));

        abort_if($imagens->isEmpty(), 404);

        $tmp = tempnam(sys_get_temp_dir(), 'pedido-imagens-');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($imagens as $arquivo) {
            $tipo = str($arquivo->tipo_arquivo?->label() ?? 'Imagens')
                ->ascii()
                ->replaceMatches('/[^A-Za-z0-9\- ]+/', '')
                ->trim()
                ->value() ?: 'Imagens';

            $zip->addFile(
                $disk->path($arquivo->caminho),
                "{$tipo}/{$arquivo->nome_original}"
            );
        }

        $zip->close();

        $nomeArquivo = str($pedido->numero_protocolo)
            ->replace('/', '-')
            ->prepend('Pedido-')
            ->append('-imagens.zip')
            ->value();

        return response()
            ->download($tmp, $nomeArquivo, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
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

    private function isImage(PedidoArquivo $arquivo): bool
    {
        $mime = mb_strtolower((string) $arquivo->mime_type);
        $ext = mb_strtolower(pathinfo((string) $arquivo->caminho, PATHINFO_EXTENSION));

        return str_starts_with($mime, 'image/')
            || in_array($ext, self::IMAGE_EXTENSIONS, true);
    }
}
