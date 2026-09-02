<?php

namespace App\Livewire\Avaliacoes;

use Livewire\Component;

class ComponenteTabs extends Component
{
    public array $tabs = [];

    public ?int $activeId = null;

    public function render()
    {
        return view('livewire.avaliacoes.componente-tabs');
    }
}
