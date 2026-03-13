<?php

namespace App\Filament\Admin\Components;

use Filament\Forms\Components\Field;

class SliderRating extends Field
{
    protected string $view = 'filament.components.forms.slider-rating-field';

    public function setUp(): void
    {
        parent::setUp();

        $this->default(3)
            ->dehydrated(true);
    }
}