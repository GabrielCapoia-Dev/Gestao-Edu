<?php

namespace App\Filament\Admin\Resources\Avisos\Schemas;

use App\Filament\Admin\Support\PublicoAlvoForm;
use App\Models\Aviso;
use App\Models\Enums\DashboardPrioridade;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class AvisoForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User|null $user */
        $user = Auth::user();
        $podePublicar = $user
            ? Gate::forUser($user)->allows('publish', Aviso::class)
            : false;
        $podeGerenciarPublico = $user
            ? Gate::forUser($user)->allows('manageAudience', Aviso::class)
            : false;

        return $schema->components([
            Section::make('Conteúdo do aviso')
                ->description('Use uma mensagem curta e objetiva. O link de ação é opcional.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('titulo')
                        ->label('Título')
                        ->required()
                        ->maxLength(160)
                        ->live(debounce: 400)
                        ->columnSpanFull(),

                    Textarea::make('descricao')
                        ->label('Descrição')
                        ->required()
                        ->rows(5)
                        ->maxLength(3000)
                        ->live(debounce: 400)
                        ->columnSpanFull(),

                    TextInput::make('link_acao')
                        ->label('Link de ação')
                        ->placeholder('/admin/... ou https://...')
                        ->maxLength(2048)
                        ->rules(['nullable', 'regex:/^(https?:\\/\\/|\\/(?!\\/))/i'])
                        ->validationMessages([
                            'regex' => 'Informe um endereço iniciado por /, http:// ou https://.',
                        ])
                        ->live(debounce: 400),

                    TextInput::make('texto_botao')
                        ->label('Texto do botão')
                        ->placeholder('Saiba mais')
                        ->requiredWith('link_acao')
                        ->maxLength(80)
                        ->live(debounce: 400),
                ]),

            Section::make('Exibição')
                ->description('Defina a vigência, a prioridade e a posição desejada no banner.')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    DateTimePicker::make('inicio_exibicao')
                        ->label('Início da exibição')
                        ->default(fn () => now())
                        ->seconds(false)
                        ->required()
                        ->live(),

                    DateTimePicker::make('fim_exibicao')
                        ->label('Fim da exibição')
                        ->default(fn () => now()->addDays(7))
                        ->seconds(false)
                        ->afterOrEqual('inicio_exibicao')
                        ->required()
                        ->live(),

                    Select::make('prioridade')
                        ->label('Prioridade')
                        ->options(DashboardPrioridade::options())
                        ->default(DashboardPrioridade::Normal->value)
                        ->native(false)
                        ->required()
                        ->live(),

                    Select::make('posicao_preferencial')
                        ->label('Posição preferencial')
                        ->options([
                            1 => '1ª posição',
                            2 => '2ª posição',
                            3 => '3ª posição',
                        ])
                        ->placeholder('Sem preferência')
                        ->native(false)
                        ->live(),

                    TextInput::make('ordem_manual')
                        ->label('Ordem manual')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(9999)
                        ->default(0)
                        ->required(),

                    Toggle::make('ativo')
                        ->label('Publicado')
                        ->helperText('Avisos não publicados permanecem visíveis somente na administração.')
                        ->default(false)
                        ->visible($podePublicar)
                        ->dehydrated($podePublicar)
                        ->live(),
                ]),

            ...PublicoAlvoForm::schema($user, $podeGerenciarPublico),

            Section::make('Pré-visualização')
                ->description('Representação aproximada do aviso na página inicial.')
                ->columnSpanFull()
                ->schema([
                    ViewField::make('preview_aviso')
                        ->hiddenLabel()
                        ->dehydrated(false)
                        ->view('filament.admin.resources.avisos.preview')
                        ->viewData(fn (Get $get): array => [
                            'aviso' => self::avisoParaPreview($get),
                        ]),
                ]),
        ]);
    }

    private static function avisoParaPreview(Get $get): Aviso
    {
        return Aviso::make([
            'titulo' => filled($get('titulo')) ? $get('titulo') : 'Título do aviso',
            'descricao' => filled($get('descricao'))
                ? $get('descricao')
                : 'A descrição do aviso será apresentada neste espaço.',
            'link_acao' => $get('link_acao'),
            'texto_botao' => $get('texto_botao'),
            'prioridade' => $get('prioridade') ?: DashboardPrioridade::Normal->value,
            'inicio_exibicao' => $get('inicio_exibicao') ?: now(),
            'fim_exibicao' => $get('fim_exibicao') ?: now()->addDays(7),
            'ativo' => (bool) $get('ativo'),
        ]);
    }
}
