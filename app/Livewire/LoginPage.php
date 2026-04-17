<?php

namespace App\Livewire;

use Caresome\FilamentAuthDesigner\Pages\Auth\Login as BaseLogin;
use Filament\Forms;
use Filament\Actions;
use Filament\Schemas\Schema;


class LoginPage extends BaseLogin
{


    protected static string $layout = 'components.layouts.login-page';


    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->type('email')
                ->required()
                ->placeholder('exemplo@exemplo.com')
                ->autocomplete('username'),

            Forms\Components\TextInput::make('password')
                ->label('Senha')
                ->password()
                ->revealable()
                ->required()
                ->placeholder('************')
                ->autocomplete('current-password'),
        ]);
    }


    protected function getFormActions(): array
    {
        return [
            // Botão de login padrão (manual)
            Actions\Action::make('authenticate')  // troque 'login' por 'authenticate'
                ->label('Entrar no Sistema')
                ->color('primary')
                ->submit('authenticate'),


            // Botão de login com Google
            Actions\Action::make('googleLogin')
                ->label('Entrar com Google')
                ->action(function () {
                    return redirect(route('google.redirect'));
                })
                ->color('gray')
                ->extraAttributes([
                    'class' => 'inline-flex items-center justify-center gap-2 w-full px-4 py-2 mt-2 rounded-md border 
                               border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm shadow-sm 
                               transition-all duration-150',
                ])
                ->icon(fn() => new \Illuminate\Support\HtmlString(
                    '<img src="' . asset('images/google-logo.svg') . '" class="w-5 h-5" alt="Google">'
                )),
        ];
    }
}
