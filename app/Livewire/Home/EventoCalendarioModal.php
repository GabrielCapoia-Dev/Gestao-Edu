<?php

namespace App\Livewire\Home;

use App\Filament\Admin\Pages\Actions\EventoCalendarioCreateAction;
use App\Services\ProfilePreviewService;
use Filament\Actions\CreateAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class EventoCalendarioModal extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function novoEventoAction(): CreateAction
    {
        return EventoCalendarioCreateAction::make(
            'novoEvento',
            app(ProfilePreviewService::class)->effectiveUser(),
        );
    }

    public function render(): View
    {
        return view('livewire.home.evento-calendario-modal');
    }
}
