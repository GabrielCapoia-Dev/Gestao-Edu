<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PedidoFotoUpload
{
    public const MAX_SIZE_KB = 5120;

    public const MAX_SIZE_BYTES = self::MAX_SIZE_KB * 1024;

    public const ACCEPTED_EXTENSIONS = ['jpeg', 'jpg', 'png', 'webp'];

    public const ACCEPTED_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    public static function configure(FileUpload $upload, ?string $cameraTarget = null): FileUpload
    {
        return $upload
            ->rules([self::validationRule()])
            ->maxSize(self::MAX_SIZE_KB)
            ->acceptedFileTypes(self::ACCEPTED_MIME_TYPES)
            ->mimeTypeMap([
                'jpeg' => 'image/jpeg',
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
            ])
            ->extraInputAttributes([
                'accept' => implode(',', [
                    '.jpeg',
                    '.jpg',
                    '.png',
                    '.webp',
                    ...self::ACCEPTED_MIME_TYPES,
                ]),
            ])
            ->extraAttributes($cameraTarget ? [
                'data-camera-upload-target' => $cameraTarget,
            ] : []);
    }

    public static function validationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof TemporaryUploadedFile) {
                return;
            }

            $extension = mb_strtolower($value->getClientOriginalExtension() ?: pathinfo($value->getClientOriginalName(), PATHINFO_EXTENSION));
            $mimeType = mb_strtolower((string) ($value->getMimeType() ?: $value->getClientMimeType()));
            $size = (int) $value->getSize();

            $hasInvalidFormat = ! in_array($extension, self::ACCEPTED_EXTENSIONS, true)
                || ! in_array($mimeType, self::ACCEPTED_MIME_TYPES, true);

            $hasInvalidSize = $size > self::MAX_SIZE_BYTES;

            if (! $hasInvalidFormat && ! $hasInvalidSize) {
                return;
            }

            $fileName = $value->getClientOriginalName() ?: 'arquivo';

            if ($hasInvalidFormat && $hasInvalidSize) {
                $fail("A foto \"{$fileName}\" não foi enviada: formato incorreto e tamanho acima de 5 MB. Use JPEG, JPG, PNG ou WEBP com até 5 MB.");

                return;
            }

            if ($hasInvalidFormat) {
                $fail("A foto \"{$fileName}\" não foi enviada: formato incorreto. Use JPEG, JPG, PNG ou WEBP.");

                return;
            }

            $fail("A foto \"{$fileName}\" não foi enviada: tamanho acima de 5 MB. Envie uma imagem com até 5 MB.");
        };
    }

    public static function acceptedMimeTypes(): array
    {
        return self::ACCEPTED_MIME_TYPES;
    }
}
