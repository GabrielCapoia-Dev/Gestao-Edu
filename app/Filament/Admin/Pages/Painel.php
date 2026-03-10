<?php

namespace App\Filament\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;

class Painel extends Page
{
    protected string $view = 'filament.admin.pages.painel';

    protected static ?string $slug = 'painel';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-plus';

}
