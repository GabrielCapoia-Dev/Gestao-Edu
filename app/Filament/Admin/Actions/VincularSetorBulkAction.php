<?php

namespace App\Filament\Admin\Actions;

use App\Services\UserSetorAccessService;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VincularSetorBulkAction
{
    public static function make(
        ?string $permission,
        string $recordsLabel = 'registros selecionados',
        ?Closure $updateRecord = null,
        ?Closure $visible = null,
        string $name = 'vincular_setor',
    ): BulkAction {
        return BulkAction::make($name)
            ->label('Vincular ao setor')
            ->icon('heroicon-o-building-office')
            ->color('primary')
            ->visible($visible ?? fn (): bool => $permission === null || (Auth::user()?->hasPermissionTo($permission) ?? false))
            ->form([
                Select::make('setor_id')
                    ->label('Setor')
                    ->options(fn () => app(UserSetorAccessService::class)->optionsForSelect(Auth::user()))
                    ->searchable()
                    ->required(),
            ])
            ->requiresConfirmation()
            ->modalHeading('Vincular registros ao setor')
            ->modalDescription("Os {$recordsLabel} passarão a usar o setor informado.")
            ->action(function (array $data, $records) use ($recordsLabel, $updateRecord): void {
                $setorId = (int) ($data['setor_id'] ?? 0);

                app(UserSetorAccessService::class)->assertCanUseSetor(Auth::user(), $setorId);

                $afetados = 0;

                DB::transaction(function () use ($records, $setorId, $updateRecord, &$afetados): void {
                    foreach ($records as $record) {
                        $updated = true;

                        if ($updateRecord) {
                            $updated = $updateRecord($record, $setorId) !== false;
                        } else {
                            $record->update(['setor_id' => $setorId]);
                        }

                        if ($updated) {
                            $afetados++;
                        }
                    }
                });

                Notification::make()
                    ->title('Setor vinculado')
                    ->body("{$afetados} {$recordsLabel} foram vinculados ao setor informado.")
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
