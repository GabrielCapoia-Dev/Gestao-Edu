<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Services\UserService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

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

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'eyebrow' => 'Início',
            'title' => 'Bem-vindo ao Gestão Edu',
            'description' => 'Acompanhe avisos, eventos e atividades importantes para a sua rotina escolar.',
        ]);
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
