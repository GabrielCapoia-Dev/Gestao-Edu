<?php

namespace App\Filament\Admin\Resources\Pedidos\Schemas;

use App\Models\TipoManutencao;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;


class PedidoCriacaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Novo Pedido')
                    ->description('Informe o problema encontrado')
                    ->schema([

                        Select::make('tipo_manutencao_id')
                            ->label('Tipo de Manutenção')
                            ->options(
                                TipoManutencao::where('ativo', true)
                                    ->orderBy('nome')
                                    ->pluck('nome', 'id')
                            )
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('nome_solicitante')
                            ->label('Nome de quem será o responsável pela solicitação')
                            ->required()
                            ->maxLength(150)
                            ->helperText('Essa pessoa será o responsável pela solicitação em caso de dúvidas')
                            ->placeholder('Informe o nome do responsável pela solicitação')
                            ->columnSpanFull(),

                        Textarea::make('descricao_pedido')
                            ->label('Descrição do Problema')
                            ->required()
                            ->rows(6)
                            ->maxLength(2000)
                            ->placeholder('Descreva o problema de forma detalhada, informando quando o problema começou, onde ocorreu, e caso saiba, informe qual foi o motivo')
                            ->columnSpanFull(),

                        FileUpload::make('arquivos')
                            ->label('Fotos do Problema')
                            ->multiple()
                            ->image()
                            ->maxFiles(10)
                            ->maxSize(5120)
                            ->directory('pedidos')
                            ->disk('public')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Até 10 imagens (JPEG, PNG ou WEBP) - máximo 5MB cada')
                            ->columnSpanFull(),

                    ])
                    ->columns(1),
            ]);
    }
}