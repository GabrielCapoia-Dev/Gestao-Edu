<?php

namespace App\Filament\Admin\Components;

use App\Support\PedidoImageUpload;
use Closure;
use Filament\Forms\Components\FileUpload;

class PedidoArquivoUpload extends FileUpload
{
    public function aceitarSomenteImagensQuando(bool|Closure $condition): static
    {
        $this->acceptedFileTypes = fn (): ?array => $this->evaluate($condition)
            ? PedidoImageUpload::MIME_TYPES
            : null;

        $this->rule(
            'mimetypes:'.implode(',', PedidoImageUpload::MIME_TYPES),
            $condition
        );

        return $this;
    }
}
