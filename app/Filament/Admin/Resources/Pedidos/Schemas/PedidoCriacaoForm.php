<?php

namespace App\Filament\Admin\Resources\Pedidos\Schemas;

use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Support\PedidoFotoUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class PedidoCriacaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Novo Pedido')
                    ->columnSpanFull()
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
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('tipo_manutencao_opcao_ids', [])),

                        Select::make('tipo_manutencao_opcao_ids')
                            ->label('Problemas identificados')
                            ->helperText('Selecione uma ou mais frases que melhor descrevem o problema.')
                            ->options(fn (Get $get): array => filled($get('tipo_manutencao_id'))
                                ? TipoManutencaoOpcao::query()
                                    ->where('ativo', true)
                                    ->where('tipo_manutencao_id', $get('tipo_manutencao_id'))
                                    ->orderBy('texto')
                                    ->pluck('texto', 'id')
                                    ->toArray()
                                : [])
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        DatePicker::make('data_identificacao_problema')
                            ->label('Data de identificação do problema')
                            ->helperText('Informe quando o problema começou ou foi identificado.')
                            ->required()
                            ->maxDate(now()),

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

                        PedidoFotoUpload::configure(
                            FileUpload::make('arquivos')
                                ->label('Fotos do Problema')
                                ->multiple()
                                ->required()
                                ->maxFiles(10)
                                ->directory('pedidos')
                                ->disk('public')
                                ->visibility('public'),
                            'pedido-fotos-problema'
                        )
                            ->helperText('Até 10 imagens (JPEG, JPG, PNG ou WEBP) - máximo 5MB cada')
                            ->columnSpanFull(),

                        View::make('components.forms.camera-file-upload-tools')
                            ->viewData([
                                'target' => 'pedido-fotos-problema',
                            ])
                            ->columnSpanFull(),

                    ])
                    ->columns(1),
            ]);
    }
}
