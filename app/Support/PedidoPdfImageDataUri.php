<?php

namespace App\Support;

use App\Models\PedidoArquivo;
use Illuminate\Contracts\Filesystem\Filesystem;

class PedidoPdfImageDataUri
{
    /**
     * @var array<string, string>
     */
    private const MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
    ];

    /**
     * @var array<string, bool>
     */
    private const DIRECTLY_SUPPORTED_MIMES = [
        'image/jpeg' => true,
        'image/png' => true,
    ];

    public static function fromStorage(Filesystem $disk, PedidoArquivo $arquivo): ?string
    {
        $path = (string) $arquivo->caminho;

        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        try {
            $contents = $disk->get($path);
        } catch (\Throwable) {
            return null;
        }

        if ($contents === '') {
            return null;
        }

        $mime = self::resolveMime($disk, $arquivo, $path);

        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        if (isset(self::DIRECTLY_SUPPORTED_MIMES[$mime])) {
            return sprintf('data:%s;base64,%s', $mime, base64_encode($contents));
        }

        return self::convertToPngDataUri($contents);
    }

    private static function resolveMime(Filesystem $disk, PedidoArquivo $arquivo, string $path): string
    {
        $mime = mb_strtolower(trim((string) $arquivo->mime_type));

        if (str_starts_with($mime, 'image/')) {
            return $mime;
        }

        try {
            $storageMime = mb_strtolower((string) $disk->mimeType($path));

            if (str_starts_with($storageMime, 'image/')) {
                return $storageMime;
            }
        } catch (\Throwable) {
            //
        }

        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::MIME_BY_EXTENSION[$extension] ?? 'application/octet-stream';
    }

    private static function convertToPngDataUri(string $contents): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            return null;
        }

        try {
            ob_start();
            imagepng($image);
            $png = ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        if (! is_string($png) || $png === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
