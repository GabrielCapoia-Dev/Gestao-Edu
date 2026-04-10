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

    protected function getHeaderActions(): array
    {
        $installUrl = route('mobile.install.short');

        return [
            Action::make('instalar_app_mobile')
                ->label('Instalar app mobile')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('primary')
                ->url($installUrl, shouldOpenInNewTab: true),
            Action::make('compartilhar_app_mobile')
                ->label('Compartilhar no WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
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
