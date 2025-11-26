<?php

namespace App\Filament\Pages;

use App\Services\UserService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.dashboard';

    public static function userService(): UserService
    {
        return app(UserService::class);
    }

    public static function canAccess(): bool
    {

        return static::userService()->ehAdmin(Auth::user());
    }
}
