<?php

namespace App\Filament\Admin\Components;

use Filament\Forms\Components\FileUpload;

class PedidoImageUpload extends FileUpload
{
    protected string $view = 'forms.components.pedido-image-upload';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->hiddenLabel()
            ->image()
            ->appendFiles();
    }
}
