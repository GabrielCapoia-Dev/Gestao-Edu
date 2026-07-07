<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;

use App\Services\UserService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
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

    protected static ?string $navigationLabel = 'Início';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    public static function canAccess(): bool
    {
        return Gate::allows('viewDashboard', User::class);
    }

    public static function userService(): UserService
    {
        return app(UserService::class);
    }
}
