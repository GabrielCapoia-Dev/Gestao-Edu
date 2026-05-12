<?php

namespace App\Services\Exports;

class ExportFileResult
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly string $fileName,
        public readonly ?string $mime,
        public readonly ?int $sizeBytes,
        public readonly ?string $checksum,
    ) {}

    /**
     * @return array{disk:string,path:string,file_name:string,mime:string|null,size_bytes:int|null,checksum:string|null}
     */
    public function toDatabasePayload(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'file_name' => $this->fileName,
            'mime' => $this->mime,
            'size_bytes' => $this->sizeBytes,
            'checksum' => $this->checksum,
        ];
    }
}
