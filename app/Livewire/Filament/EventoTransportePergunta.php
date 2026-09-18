<?php

namespace App\Livewire\Filament;

use Livewire\Component;

class EventoTransportePergunta extends Component
{
    public string $selecionado = 'nao';

    public function selecionar(string $opcao): void
    {
        if (! in_array($opcao, ['sim', 'nao'], true)) {
            return;
        }

        $this->selecionado = $opcao;
    }

    public function render()
    {
        return view('livewire.filament.evento-transporte-pergunta');
    }
}
