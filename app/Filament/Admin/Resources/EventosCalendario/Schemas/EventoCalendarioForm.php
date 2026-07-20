<?php

namespace App\Filament\Admin\Resources\EventosCalendario\Schemas;

use App\Filament\Admin\Support\PublicoAlvoForm;
use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\UserSetorAccessService;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class EventoCalendarioForm
{
    public static function configure(Schema $schema, ?User $user): Schema
    {
        $canPublish = $user && Gate::forUser($user)->allows('publish', EventoCalendario::class);
        $canManageAudience = $user && Gate::forUser($user)->allows('manageAudience', EventoCalendario::class);

        return $schema->components([
            Section::make('Evento')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('titulo')
                        ->label('Título')
                        ->required()
                        ->maxLength(160)
                        ->columnSpanFull(),
                    Textarea::make('descricao')
                        ->label('Descrição')
                        ->rows(4)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                    Select::make('categoria')
                        ->label('Categoria')
                        ->options(collect(EventoCalendarioCategoria::cases())->mapWithKeys(
                            fn ($item): array => [$item->value => $item->label()],
                        )->all())
                        ->required()
                        ->native(false),
                    TextInput::make('assunto')
                        ->label('Assunto')
                        ->maxLength(100),
                    Select::make('prioridade')
                        ->label('Prioridade')
                        ->options(DashboardPrioridade::options())
                        ->default(DashboardPrioridade::Normal->value)
                        ->required()
                        ->native(false),
                    Select::make('status')
                        ->label('Status')
                        ->options(collect(EventoCalendarioStatus::cases())->mapWithKeys(
                            fn ($item): array => [$item->value => $item->label()],
                        )->all())
                        ->default(EventoCalendarioStatus::AGENDADO->value)
                        ->required()
                        ->native(false),
                    DateTimePicker::make('data_inicio')
                        ->label('Início')
                        ->seconds(false)
                        ->required(),
                    DateTimePicker::make('data_fim')
                        ->label('Fim')
                        ->seconds(false)
                        ->afterOrEqual('data_inicio')
                        ->required(),
                    TextInput::make('progresso')
                        ->label('Progresso')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%'),
                    Select::make('cor')
                        ->label('Identificação visual')
                        ->options(collect(EventoCalendarioCor::cases())->mapWithKeys(
                            fn ($item): array => [$item->value => $item->label()],
                        )->all())
                        ->default(EventoCalendarioCor::AZUL->value)
                        ->required()
                        ->native(false),
                    Select::make('escola_id')
                        ->label('Escola relacionada')
                        ->options(fn (): array => self::schoolOptions($user))
                        ->searchable()
                        ->native(false),
                    Select::make('setor_id')
                        ->label('Setor relacionado')
                        ->options(fn (): array => app(UserSetorAccessService::class)->optionsForSelect($user))
                        ->searchable()
                        ->native(false),
                    Toggle::make('ativo')
                        ->label('Publicado')
                        ->helperText($canPublish ? 'Eventos publicados aparecem na agenda durante o período.' : 'Você não possui permissão para publicar eventos.')
                        ->default(false)
                        ->disabled(! $canPublish)
                        ->dehydrated($canPublish)
                        ->columnSpanFull(),
                ]),
            Section::make('Ação opcional')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('link_acao')
                        ->label('Link de ação')
                        ->placeholder('https://... ou /admin/...')
                        ->maxLength(2048)
                        ->rule(static function (): Closure {
                            return static function (string $attribute, mixed $value, Closure $fail): void {
                                if (blank($value)) {
                                    return;
                                }

                                $link = trim((string) $value);
                                $relative = str_starts_with($link, '/') && ! str_starts_with($link, '//');
                                $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

                                if (! $relative && ! in_array($scheme, ['http', 'https'], true)) {
                                    $fail('Informe uma URL HTTP(S) ou um caminho interno iniciado por /.');
                                }
                            };
                        })
                        ->columnSpanFull(),
                    TextInput::make('texto_botao')
                        ->label('Texto do botão')
                        ->maxLength(80),
                ]),
            ...PublicoAlvoForm::schema($user, (bool) $canManageAudience),
        ]);
    }

    /** @return array<int, string> */
    private static function schoolOptions(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $context = app(DashboardUserContextFactory::class)->make($user);
        $query = Escola::query()->where('ativo', true)->orderBy('nome');

        if (! $context->escopoGlobal) {
            $query->whereKey($context->escolaIds);
        }

        return $query->pluck('nome', 'id')->all();
    }
}
