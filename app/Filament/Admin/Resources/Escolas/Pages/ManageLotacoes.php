<?php

namespace App\Filament\Admin\Resources\Escolas\Pages;

use App\Filament\Admin\Resources\Escolas\EscolaResource;
use App\Models\LocalTrabalho;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class ManageLotacoes extends ManageRelatedRecords
{
    protected static string $resource = EscolaResource::class;

    protected static string $relationship = 'lotacoes';

    protected static ?string $relationshipTitle = 'Lotações';

    public function getTitle(): string|Htmlable
    {
        /** @var LocalTrabalho $local */
        $local = $this->getRecord();

        return "Lotações — {$local->nome}";
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codigo')
                    ->label('Número da lotação')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),

                TextInput::make('nome')
                    ->label('Nome da lotação')
                    ->required()
                    ->maxLength(150),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nome')
                    ->label('Nome da lotação')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Atualizada')
                    ->since()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar lotação'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('codigo')
            ->paginated([10, 25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('voltar')
                ->label('Voltar para locais de trabalho')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(EscolaResource::getUrl()),
        ];
    }
}
