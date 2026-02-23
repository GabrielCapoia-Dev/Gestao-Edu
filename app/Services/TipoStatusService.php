<?php

namespace App\Services;

use App\Models\TipoStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TipoStatusService
{
    public function configurarFormulario(Form $form): Form
    {
        return $form->schema($this->schemaFormulario());
    }

    protected function schemaFormulario(): array
    {
        return [
            Forms\Components\TextInput::make('nome')
                ->label('Nome')
                ->required()
                ->maxLength(100),

            Forms\Components\ColorPicker::make('cor')
                ->label('Cor')
                ->required()
                ->format('hex'),

            Forms\Components\Toggle::make('finaliza_pedido')
                ->label('Finaliza o pedido?')
                ->helperText('Marca o pedido como concluído')
                ->inline(false)
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->default(false),

            Forms\Components\Toggle::make('cancela_pedido')
                ->label('Cancela o pedido?')
                ->helperText('Marca o pedido como cancelado')
                ->inline(false)
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->default(false),
        ];
    }

    public function configurarTabela(Table $table): Table
    {
        return $table
            ->query(TipoStatus::query()->where('ativo', true))
            ->columns($this->colunasTabela())
            ->actions($this->acoesTabela())
            ->bulkActions($this->acoesEmMassa())
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    protected function colunasTabela(): array
    {
        return [
            Tables\Columns\TextColumn::make('nome')
                ->label('Nome')
                ->alignCenter()
                ->searchable()
                ->sortable(),

            Tables\Columns\ColorColumn::make('cor')
                ->alignCenter()
                ->label('Cor'),


            Tables\Columns\IconColumn::make('finaliza_pedido')
                ->alignCenter()
                ->label('Finaliza')
                ->boolean(),

            Tables\Columns\IconColumn::make('cancela_pedido')
                ->alignCenter()
                ->label('Cancela')
                ->boolean(),

            Tables\Columns\TextColumn::make('updated_at')
                ->label('Atualizado em')
                ->dateTime('d/m/Y H:i')
                ->alignCenter()
                ->sortable(),
        ];
    }

    protected function acoesTabela(): array
    {
        return [
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ];
    }

    protected function acoesEmMassa(): array
    {
        return [
            Tables\Actions\DeleteBulkAction::make(),
        ];
    }

    public function listarAtivos(): Builder
    {
        return TipoStatus::query()->where('ativo', true)->orderBy('ordem');
    }

    public function obterStatusInicial(): ?TipoStatus
    {
        return TipoStatus::where('ativo', true)
            ->orderBy('ordem')
            ->first();
    }
}
