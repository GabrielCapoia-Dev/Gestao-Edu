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

    public function getTitle(): string
    {
        return '';
    }
    public static function canAccess(): bool
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $user->hasPermissionTo('Visualizar Tela de Inicio');
    }

    public function getHeading(): string
    {
        return '';
    }

    protected static ?string $navigationLabel = 'Inicio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;


    public static function userService(): UserService
    {
        return app(UserService::class);
    }
}
