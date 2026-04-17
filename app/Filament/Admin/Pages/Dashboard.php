<?php

namespace App\Filament\Admin\Pages;

use App\Services\UserService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use Filament\Support\Icons\Heroicon;


class Dashboard extends Page
{
    // protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.dashboard';

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    protected static ?string $navigationLabel = 'Inicio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo('Visualizar Tela de Inicio') ?? false;
    }

    protected function getHeaderActions(): array
    {
        $installUrl = route('mobile.install.short');
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $canDownloadApp = $user?->hasPermissionTo('Baixar App') ?? false;

        return [
            Action::make('instalar_app_mobile')
                ->label('Instalar app mobile')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('primary')
                ->visible($canDownloadApp)
                ->url($installUrl, shouldOpenInNewTab: true),
            Action::make('compartilhar_app_mobile')
                ->label('Compartilhar no WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->visible($canDownloadApp)
                ->url(
                    'https://wa.me/?text=' . rawurlencode(
                        "Instale o Gestao Edu Mobile no celular: {$installUrl}"
                    ),
                    shouldOpenInNewTab: true,
                ),
        ];
    }

    public static function userService(): UserService
    {
        return app(UserService::class);
    }
}
