<?php

namespace App\Filament\Themes;

use App\Services\UserService;
use Hasnayeen\Themes\Themes\Nord;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Hasnayeen\Themes\Contracts\CanModifyPanelConfig;
use Hasnayeen\Themes\Contracts\Theme;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;

class TemaSME extends Nord implements CanModifyPanelConfig, Theme
{
    public static function getName(): string
    {
        return 'nord';
    }

    public static function getPath(): string
    {
        return __DIR__ . '/../../resources/dist/nord.css';
    }

    public static function setThemeType($auth)
    {
        $isAdmin = app(UserService::class)->ehAdmin($auth);

        if (!$isAdmin) {
            return [
                TemaSME::class,
            ];
        }
        return \Hasnayeen\Themes\Themes\Sunset::class;
    }

    public static function setOverrideTheme($auth): ?bool
    {

        $isAdmin = app(UserService::class)->ehAdmin($auth);
        if (!$isAdmin) {
            return true;
        }
        return false;
    }

    public function getThemeColor(): array
    {
        $panel = Filament::getCurrentPanel()?->getId();
        if ($panel === 'administrativo') {
            return [
                'primary' => MyColors::violet(),
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'gray' => MyColors::darkGray(),
            ];
        }

        return [
            'primary' => MyColors::smeBlue(),
            'info' => Color::Sky,
            'success' => Color::Green,
            'warning' => Color::Orange,
            'danger' => Color::Red,
            'gray' => MyColors::blue(),
        ];
    }

    public function modifyPanelConfig(Panel $panel): Panel
    {
        return $panel
            ->topNavigation()
            ->sidebarCollapsibleOnDesktop(false)
            ->renderHook('panels::page.start', fn() => view('themes::filament.hooks.tenant-menu'));
    }
}
