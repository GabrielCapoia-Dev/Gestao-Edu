<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PedidoImageUpload
{
    public const MESSAGE = 'Envie uma imagem JPEG, PNG ou WEBP.';

    public const MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    public static function assertValid(
        string|UploadedFile $arquivo,
        string $attribute,
        bool $deleteInvalidStoredFile = true
    ): void
    {
        if (static::isValid($arquivo)) {
            return;
        }

        if ($deleteInvalidStoredFile) {
            static::deleteStoredFile($arquivo);
        }

        throw ValidationException::withMessages([
            $attribute => static::MESSAGE,
        ]);
    }

    /**
     * @param  iterable<string|UploadedFile>  $arquivos
     */
    public static function assertAllValid(iterable $arquivos, string $attribute): void
    {
        $arquivos = collect($arquivos)->filter()->values();

        foreach ($arquivos as $arquivo) {
            if (static::isValid($arquivo)) {
                continue;
            }

            $arquivos->each(fn (string|UploadedFile $item) => static::deleteStoredFile($item));

            throw ValidationException::withMessages([
                $attribute => static::MESSAGE,
            ]);
        }
    }

    public static function isValid(string|UploadedFile $arquivo): bool
    {
        $extension = static::extension($arquivo);

        if (! in_array($extension, static::EXTENSIONS, true)) {
            return false;
        }

        $contents = static::contents($arquivo);

        if ($contents === null || $contents === '') {
            return false;
        }

        $imageInfo = @getimagesizefromstring($contents);
        $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? null) : null;

        return is_string($mime) && in_array(mb_strtolower($mime), static::MIME_TYPES, true);
    }

    private static function extension(string|UploadedFile $arquivo): string
    {
        $name = $arquivo instanceof UploadedFile
            ? $arquivo->getClientOriginalName()
            : $arquivo;

        return mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    private static function contents(string|UploadedFile $arquivo): ?string
    {
        try {
            if ($arquivo instanceof UploadedFile) {
                $contents = file_get_contents($arquivo->getPathname());

                return is_string($contents) ? $contents : null;
            }

            $path = trim(str_replace('\\', '/', $arquivo), '/');
            $disk = Storage::disk('public');

            return $disk->exists($path) ? $disk->get($path) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function deleteStoredFile(string|UploadedFile $arquivo): void
    {
        if ($arquivo instanceof UploadedFile) {
            return;
        }

        try {
            $path = trim(str_replace('\\', '/', $arquivo), '/');

            if (filled($path)) {
                Storage::disk('public')->delete($path);
            }
        } catch (\Throwable) {
            //
        }
    }
}
