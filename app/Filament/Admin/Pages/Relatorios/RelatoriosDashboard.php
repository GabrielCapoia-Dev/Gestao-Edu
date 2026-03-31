<?php

namespace App\Filament\Admin\Pages\Relatorios;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;
use Illuminate\Support\Facades\Auth;

class RelatoriosDashboard extends Page
{
    protected string $view = 'filament.pages.relatorios.relatorios-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;
    protected static ?string $navigationLabel = 'Relatórios';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Dashboard de Relatórios';
    protected static ?string $slug = 'relatorios-dashboard';


    public int $totalProfessores = 0;
    public int $totalTurmas = 0;
    public int $totalComponentes = 0;
    public int $totalEscolas = 0;

    public function mount(): void
    {
        $this->totalProfessores = \App\Models\Professor::count();
        $this->totalTurmas      = \App\Models\Turma::count();
        $this->totalComponentes = \App\Models\ComponenteCurricular::count();
        $this->totalEscolas     = \App\Models\Escola::count();
    }


    public static function canAccess(): bool
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $user->hasPermissionTo('Listar Relatórios: Dashboard');
    }
}
