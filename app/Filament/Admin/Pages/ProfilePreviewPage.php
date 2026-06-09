<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Services\ProfilePreviewService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ProfilePreviewPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.profile-preview';

    protected static ?string $title = 'Trocar de Usuário';

    protected static ?string $slug = 'profile-preview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Users;

    protected static bool $navigationIsHidden = true;

    public ?int $targetUserId = null;

    public function mount(): void
    {
        $preview = app(ProfilePreviewService::class);

        if ($preview->isActive()) {
            $this->targetUserId = $preview->targetUserId();
        }

        $this->form->fill([
            'target_user_id' => $this->targetUserId,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('target_user_id')
                    ->label('Usuário para visualizar')
                    ->options(fn () => User::query()
                        ->where('email_approved', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->mapWithKeys(fn ($name, $id) => [$id => $name . ' (#' . $id . ')']))
                    ->searchable()
                    ->required()
                    ->placeholder('Selecione um usuário...'),
            ])
            ->statePath('data');
    }

    public function startPreview(): void
    {
        $preview = app(ProfilePreviewService::class);
        $user = $preview->controlUser();

        if (! $user || ! $preview->canControl($user)) {
            Notification::make()
                ->title('Sem permissão')
                ->body('Você não tem permissão para visualizar perfis.')
                ->danger()
                ->send();

            return;
        }

        if ($this->targetUserId === null) {
            Notification::make()
                ->title('Selecione um usuário')
                ->body('Escolha um usuário para visualizar.')
                ->warning()
                ->send();

            return;
        }

        try {
            $preview->start((int) $this->targetUserId, $user);

            Notification::make()
                ->title('Modo visualização ativado')
                ->body('Você está navegando como outro usuário. Ações de escrita serão bloqueadas.')
                ->success()
                ->send();

            $this->redirect(route('filament.admin.pages.profile-preview'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Erro')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function stopPreview(): void
    {
        $preview = app(ProfilePreviewService::class);
        $preview->stop();

        $this->targetUserId = null;
        $this->form->fill(['target_user_id' => null]);

        Notification::make()
            ->title('Modo visualização finalizado')
            ->body('Seu acesso normal foi restaurado.')
            ->success()
            ->send();

        $this->redirect(route('filament.admin.pages.profile-preview'));
    }

    public static function canAccess(): bool
    {
        $preview = app(ProfilePreviewService::class);

        return $preview->canControl();
    }
}
