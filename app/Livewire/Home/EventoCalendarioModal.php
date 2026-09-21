<?php

namespace App\Livewire\Home;

use App\Filament\Admin\Pages\Schemas\EventoCalendarioForm;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\ProfilePreviewService;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class EventoCalendarioModal extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $data = [];

    public bool $aberto = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(EventoCalendarioForm::components($this->usuario(), 'fechar'));
    }

    public function abrir(): void
    {
        $usuario = $this->usuario();
        abort_unless(Gate::forUser($usuario)->allows('create', EventoCalendario::class), 403);

        $this->resetValidation();
        $this->data = [];
        $this->aberto = true;
        $this->form->fill();
    }

    public function fechar(): void
    {
        $this->aberto = false;
        $this->data = [];
        $this->resetValidation();
    }

    public function salvar(): void
    {
        $usuario = $this->usuario();
        abort_unless(Gate::forUser($usuario)->allows('create', EventoCalendario::class), 403);

        try {
            $dados = $this->form->getState();
            app(EventoCalendarioService::class)->criar($dados, [], $usuario);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $campo => $mensagens) {
                foreach ($mensagens as $mensagem) {
                    $this->addError("data.{$campo}", $mensagem);
                }
            }

            return;
        }

        $this->fechar();
        $this->dispatch('evento-calendario-criado');

        Notification::make()
            ->title('Evento criado')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.home.evento-calendario-modal', [
            'podeCriar' => Gate::forUser($this->usuario())->allows('create', EventoCalendario::class),
        ]);
    }

    private function usuario(): User
    {
        $usuario = app(ProfilePreviewService::class)->effectiveUser();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
