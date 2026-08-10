<?php

namespace App\Filament\Admin\Resources\Pedidos\Schemas;

use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Services\PedidoService;
use App\Support\PedidoImageUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

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

                        Select::make('escola_id')
                            ->label('Escola')
                            ->options(fn (): array => app(PedidoService::class)
                                ->escolasDisponiveisParaCriacao(Auth::user())
                                ->pluck('nome', 'id')
                                ->all())
                            ->default(function (): ?int {
                                $escolas = app(PedidoService::class)
                                    ->escolasDisponiveisParaCriacao(Auth::user());

                                return $escolas->count() === 1
                                    ? (int) $escolas->first()->id
                                    : null;
                            })
                            ->helperText('Quando houver mais de uma escola disponível, selecione a unidade à qual o pedido pertence.')
                            ->required()
                            ->searchable()
                            ->preload(),

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

                        FileUpload::make('arquivos')
                            ->label('Fotos do Problema')
                            ->acceptedFileTypes(PedidoImageUpload::MIME_TYPES)
                            ->validationMessages([
                                'mimetypes' => PedidoImageUpload::MESSAGE,
                            ])
                            ->helperText(PedidoImageUpload::MESSAGE)
                            ->multiple()
                            ->required()
                            ->maxFiles(10)
                            ->directory('pedidos')
                            ->disk('public')
                            ->storeFiles()
                            ->visibility('public')
                            ->columnSpanFull(),

                    ])
                    ->columns(1),
            ]);
    }
}
