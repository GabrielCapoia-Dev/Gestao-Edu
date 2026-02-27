<?php

namespace App\Filament\Components\Forms;

use Filament\Forms\Components\Field;

class SliderRating extends Field
{
    protected string $view = 'filament.components.forms.slider-rating-field';

    public function setUp(): void
    {
        parent::setUp();

        $this->default(5)
            ->dehydrated(true);
    }
}