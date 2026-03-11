<?php

// namespace App\Filament\Resources;

// use App\Filament\Resources\UserResource\Pages;
// use App\Models\User;
// use App\Services\UserService as Service;
// use Filament\Schemas\Schema;
// use Filament\Resources\Resource;
// use Filament\Tables\Table;
// use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Support\Facades\Auth;

// class UserResource extends Resource
// {
//     protected static ?string $model = User::class;
//     // protected static ?string $navigationIcon = 'heroicon-o-users';
//     // protected static ?string $navigationGroup = "Acesso";
//     public static ?string $modelLabel = 'Usuário';
//     public static ?string $pluralModelLabel = 'Usuários';
//     public static ?string $slug = 'usuarios';

//     public static function getNavigationBadge(): ?string
//     {
//         return app(Service::class)->badgeNavegacaoParaNovosUsuarios(Auth::user());
//     }

//     public static function getNavigationBadgeTooltip(): ?string
//     {
//         return 'Novos Usuários';
//     }

//     /** FORM delega à service */
//     public static function form(Schema $schema): Schema
//     {
//         return app(Pages\UserForm::class)->configurarFormulario($schema, Auth::user());
//     }

//     /** TABLE delega à service */
//     public static function table(Table $table): Table
//     {
//         return app(Service::class)->configurarTabela($table, Auth::user());
//     }

//     /** Mantém sua sincronização antes da query base */
//     protected function getTableQuery()
//     {
//         if ($admin = Auth::user()) {
//             app(Service::class)->sincronizarIgnoradosParaAdmin($admin);
//         }
//         return parent::getTableQuery();
//     }

//     public static function getRelations(): array
//     {
//         return [];
//     }

//     public static function getPages(): array
//     {
//         return [
//             'index'  => Pages\ListUsers::route('/'),
//             'create' => Pages\CreateUser::route('/create'),
//             'edit'   => Pages\EditUser::route('/{record}/edit'),
//         ];
//     }

//     public static function getEloquentQuery(): Builder
//     {
//         return app(Service::class)->listarUsuariosQuery(
//             parent::getEloquentQuery(),
//             Auth::user()
//         );
//     }
// }
