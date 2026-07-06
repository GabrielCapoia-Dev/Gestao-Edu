<?php

namespace App\Support;

use App\Models\PedidoArquivo;
use Illuminate\Contracts\Filesystem\Filesystem;

class PedidoPdfImageDataUri
{
    /**
     * @var array<string, bool>
     */
    private const SUPPORTED_MIMES = [
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
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
        $contents = self::contentsFromStorage($disk, $arquivo);

        if ($contents === null) {
            return null;
        }

        $mime = self::detectSupportedMime($contents);

        if ($mime === null) {
            return null;
        }

        if (isset(self::DIRECTLY_SUPPORTED_MIMES[$mime])) {
            return sprintf('data:%s;base64,%s', $mime, base64_encode($contents));
        }

        return self::convertToPngDataUri($contents);
    }

    public static function isSupportedImage(Filesystem $disk, PedidoArquivo $arquivo): bool
    {
        $contents = self::contentsFromStorage($disk, $arquivo);

        return $contents !== null && self::detectSupportedMime($contents) !== null;
    }

    private static function contentsFromStorage(Filesystem $disk, PedidoArquivo $arquivo): ?string
    {
        $path = (string) $arquivo->caminho;

        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        try {
            $contents = $disk->get($path);

            return is_string($contents) && $contents !== '' ? $contents : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function detectSupportedMime(string $contents): ?string
    {
        $imageInfo = @getimagesizefromstring($contents);
        $mime = is_array($imageInfo) ? mb_strtolower((string) ($imageInfo['mime'] ?? '')) : '';

        return isset(self::SUPPORTED_MIMES[$mime]) ? $mime : null;
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
