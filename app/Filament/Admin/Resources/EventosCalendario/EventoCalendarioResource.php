<?php

namespace App\Filament\Admin\Resources\EventosCalendario;

use App\Filament\Admin\Resources\EventosCalendario\Pages\ImportarEventosCalendario;
use App\Filament\Admin\Resources\EventosCalendario\Pages\ListEventosCalendario;
use App\Filament\Admin\Resources\EventosCalendario\Schemas\EventoCalendarioForm;
use App\Filament\Admin\Resources\EventosCalendario\Tables\EventosCalendarioTable;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\Dashboard\EventoCalendarioAccessService;
use App\Services\ProfilePreviewService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EventoCalendarioResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = EventoCalendario::class;

    protected static ?string $slug = 'eventos-calendario';

    protected static ?string $modelLabel = 'Evento';

    protected static ?string $pluralModelLabel = 'Eventos da agenda';

    protected static ?string $recordTitleAttribute = 'titulo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Administração';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return EventoCalendarioForm::configure($schema, static::usuarioEfetivo());
    }

    public static function table(Table $table): Table
    {
        return EventosCalendarioTable::configure($table, static::usuarioEfetivo());
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'publicoAlvo',
            'escolasAgendadas.escola:id,nome',
            'criadoPor:id,name', 'atualizadoPor:id,name',
        ]);
        $user = static::usuarioEfetivo();

        if ($user) {
            return app(EventoCalendarioAccessService::class)->aplicarEscopo($user, $query);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventosCalendario::route('/'),
            'import' => ImportarEventosCalendario::route('/importar'),
        ];
    }

    public static function usuarioEfetivo(): ?User
    {
        return app(ProfilePreviewService::class)->effectiveUser();
    }
}
