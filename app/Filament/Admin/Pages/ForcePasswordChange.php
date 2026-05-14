<?php

namespace App\Filament\Admin\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class ForcePasswordChange extends Page
{
    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static ?string $slug = 'alterar-senha-obrigatoria';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.force-password-change';

    protected Width | string | null $maxContentWidth = Width::Full;

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string
    {
        try {
            return parent::getUrl($parameters, $isAbsolute, $panel, $tenant);
        } catch (RouteNotFoundException) {
            return $isAbsolute
                ? URL::to('/admin/alterar-senha-obrigatoria')
                : '/admin/alterar-senha-obrigatoria';
        }
    }

    public function mount(): void
    {
        if (! (Filament::auth()->user()?->must_change_password ?? false)) {
            $this->redirect(Filament::getUrl());
        }
    }

    public function getTitle(): string
    {
        return 'Redefinir senha';
    }

    public function getHeading(): string
    {
        return '';
    }
}
