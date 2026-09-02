<?php

namespace App\Livewire\Avaliacoes;

use Livewire\Component;

class TurmaTabs extends Component
{
    public array $tabs = [];

    public ?int $activeId = null;

    public function render()
    {
        return view('livewire.avaliacoes.turma-tabs');
    }
}
