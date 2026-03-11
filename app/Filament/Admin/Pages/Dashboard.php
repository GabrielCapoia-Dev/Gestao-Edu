<?php

namespace App\Filament\Admin\Pages;

use App\Services\UserService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use Filament\Support\Icons\Heroicon;


class Dashboard extends Page
{
    // protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;


    public static function userService(): UserService
    {
        return app(UserService::class);
    }

    public static function canAccess(): bool
    {

        return static::userService()->ehAdmin(Auth::user());
    }
}
