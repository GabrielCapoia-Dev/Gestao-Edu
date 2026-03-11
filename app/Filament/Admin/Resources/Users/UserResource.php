<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Schemas\UserForm;
use App\Filament\Admin\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Resources\UserResource\Pages;
use App\Services\UserService as Service;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Users;
    public static ?string $slug = 'usuarios';
    public static ?string $pluralModelLabel = 'Usuários';
    protected static string | UnitEnum | null $navigationGroup = 'Acesso';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return app(Service::class)->badgeNavegacaoParaNovosUsuarios(Auth::user());
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Novos Usuários';
    }


    /** Mantém sua sincronização antes da query base */
    protected function getTableQuery()
    {
        if ($admin = Auth::user()) {
            app(Service::class)->sincronizarIgnoradosParaAdmin($admin);
        }
        return parent::getTableQuery();
    }


    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    /** TABLE delega à service */
    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return app(Service::class)->listarUsuariosQuery(
            parent::getEloquentQuery(),
            Auth::user()
        );
    }
}
