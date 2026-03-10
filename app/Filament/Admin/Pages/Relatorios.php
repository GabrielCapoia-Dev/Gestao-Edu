<?php

// namespace App\Filament\Pages;

// use App\Models\Aluno;
// use App\Models\Escola;
// use App\Models\Serie;
// use App\Models\Laudo;
// use App\Relatorios\Relatorios as RelatorioPdf; // mesma classe onde está TIPO_BULK_LIST
// use App\Services\Relatorios\AlunoRelatorioService;
// use Filament\Pages\Page;
// use Filament\Infolists\Infolist;
// use Filament\Infolists\Contracts\HasInfolists;
// use Filament\Infolists\Concerns\InteractsWithInfolists;
// use Filament\Infolists\Components\Section;
// use Filament\Infolists\Components\TextEntry;
// use Filament\Infolists\Components\Actions\Action;
// use Filament\Forms;
// use Filament\Forms\Components\Grid;
// use Filament\Forms\Components\Select;
// use Filament\Forms\Components\TextInput;
// use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Support\Facades\Auth;

// class Relatorios extends Page implements HasInfolists
// {
//     use InteractsWithInfolists;
//     protected static bool $shouldRegisterNavigation = false;

//     // protected static ?string $navigationIcon = 'heroicon-o-document-text';
//     // public static ?string $navigationGroup = 'Administrativo';

//     protected static string $view = 'filament.pages.relatorios';

//     public static ?string $modelLabel = 'Relatório';

//     public static ?string $pluralModelLabel = 'Relatórios';

//     public static ?string $slug = 'relatorios';


//     public static ?int $navigationSort = 4;

//     public static function alunoRelatorioService()
//     {
//         return app(AlunoRelatorioService::class);
//     }

//     public static function canAccess(): bool
//     {
//         return false;

//         // /** @var \App\Models\User $user */
//         // $user = Auth::user();

//         // if (! $user) {
//         //     return false; 
//         // }

//         // return $user->hasPermissionTo('Exportar Relatório de Alunos');
//     }

//     /**
//      * Infolist principal da página de relatórios
//      */

//     // public function relatoriosInfolist(Infolist $infolist): Infolist
//     // {
//     //     return $infolist
//     //         ->schema([
//     //             Section::make('Relatórios de Alunos')
//     //                 ->collapsible()
//     //                 ->collapsed()
//     //                 ->schema([
//     //                     TextEntry::make('bulk_list_alunos')
//     //                         ->label('Bulk list de alunos')
//     //                         ->icon('heroicon-o-users')
//     //                         ->state('Listagem de alunos com CGM, série, turma, laudos etc. (com filtros)')
//     //                         ->columnSpanFull()
//     //                         ->hintAction(
//     //                             Action::make('abrir_bulk_list_alunos')
//     //                                 ->label('Abrir relatório')
//     //                                 ->icon('heroicon-o-arrow-top-right-on-square')
//     //                                 // FORMULÁRIO DE FILTROS
//     //                                 ->form([
//     //                                     Grid::make(4)
//     //                                         ->schema([
//     //                                             Select::make('id_escola')
//     //                                                 ->label('Escola')
//     //                                                 ->options(
//     //                                                     Escola::query()
//     //                                                         ->orderBy('nome')
//     //                                                         ->pluck('nome', 'id')
//     //                                                 )
//     //                                                 ->searchable()
//     //                                                 ->native(false)
//     //                                                 ->columnSpan(2),

//     //                                             Select::make('id_serie')
//     //                                                 ->label('Série')
//     //                                                 ->options(
//     //                                                     Serie::query()
//     //                                                         ->orderBy('nome')
//     //                                                         ->pluck('nome', 'id')
//     //                                                 )
//     //                                                 ->searchable()
//     //                                                 ->native(false)
//     //                                                 ->columnSpan(2),
//     //                                         ]),

//     //                                     Grid::make(8)
//     //                                         ->schema([
//     //                                             TextInput::make('idade_min')
//     //                                                 ->label('Idade mínima')
//     //                                                 ->numeric()
//     //                                                 ->minValue(1)
//     //                                                 ->maxValue(25)
//     //                                                 ->columnSpan(4),

//     //                                             TextInput::make('idade_max')
//     //                                                 ->label('Idade máxima')
//     //                                                 ->numeric()
//     //                                                 ->minValue(1)
//     //                                                 ->maxValue(25)
//     //                                                 ->columnSpan(4),
//     //                                         ]),

//     //                                     Grid::make(4)
//     //                                         ->schema([
//     //                                             Select::make('laudos')
//     //                                                 ->label('Laudos')
//     //                                                 ->options(
//     //                                                     Laudo::query()
//     //                                                         ->orderBy('nome')
//     //                                                         ->pluck('nome', 'id')
//     //                                                 )
//     //                                                 ->multiple()
//     //                                                 ->searchable()
//     //                                                 ->native(false)
//     //                                                 ->columnSpan(2),

//     //                                             Select::make('tem_laudos')
//     //                                                 ->label('Crianças com laudos')
//     //                                                 ->options([
//     //                                                     'true'  => 'Apenas com laudos',
//     //                                                     'false' => 'Apenas sem laudos',
//     //                                                 ])
//     //                                                 ->native(false)
//     //                                                 ->placeholder('Todos')
//     //                                                 ->columnSpan(1),

//     //                                             Select::make('com_apoio')
//     //                                                 ->label('Profissional de apoio')
//     //                                                 ->options([
//     //                                                     'true'  => 'Apenas com apoio',
//     //                                                     'false' => 'Apenas sem apoio',
//     //                                                 ])
//     //                                                 ->native(false)
//     //                                                 ->placeholder('Todos')
//     //                                                 ->columnSpan(1),
//     //                                         ]),
//     //                                 ])
//     //                                 // AÇÃO QUE GERA O RELATÓRIO USANDO OS FILTROS
//     //                                 ->action(function (array $data) {
//     //                                     /** @var \App\Models\User $user */
//     //                                     $user = Auth::user();

//     //                                     if (! $user || ! $user->hasPermissionTo('Exportar Relatório de Alunos')) {
//     //                                         abort(403);
//     //                                     }

//     //                                     // Começa com o query base
//     //                                     $query = Aluno::query()
//     //                                         ->with([
//     //                                             'turma.escola',
//     //                                             'turma.serie',
//     //                                             'laudos',
//     //                                             'professor',
//     //                                         ]);

//     //                                     // === ESCOLA ===
//     //                                     if (! empty($data['id_escola'])) {
//     //                                         $idEscola = (int) $data['id_escola'];

//     //                                         $query->whereHas('turma.escola', function (Builder $q) use ($idEscola) {
//     //                                             $q->where('id', $idEscola);
//     //                                         });
//     //                                     }

//     //                                     // === SÉRIE ===
//     //                                     if (! empty($data['id_serie'])) {
//     //                                         $idSerie = (int) $data['id_serie'];

//     //                                         $query->whereHas('turma.serie', function (Builder $q) use ($idSerie) {
//     //                                             $q->where('id', $idSerie);
//     //                                         });
//     //                                     }

//     //                                     // === IDADE (mesma lógica do Filter::make('idade')) ===
//     //                                     $min = $data['idade_min'] ?? null;
//     //                                     $max = $data['idade_max'] ?? null;

//     //                                     if (filled($min) || filled($max)) {
//     //                                         $min = filled($min) ? (int) $min : null;
//     //                                         $max = filled($max) ? (int) $max : null;

//     //                                         if ($min && $max) {
//     //                                             if ($min > $max) {
//     //                                                 [$min, $max] = [$max, $min];
//     //                                             }

//     //                                             $start = now()->subYears($max)->startOfDay(); // mais velho
//     //                                             $end   = now()->subYears($min)->endOfDay();   // mais novo

//     //                                             $query->whereBetween('data_nascimento', [$start, $end]);
//     //                                         } elseif ($min) {
//     //                                             $border = now()->subYears($min)->endOfDay();
//     //                                             $query->whereDate('data_nascimento', '<=', $border);
//     //                                         } elseif ($max) {
//     //                                             $border = now()->subYears($max)->startOfDay();
//     //                                             $query->whereDate('data_nascimento', '>=', $border);
//     //                                         }
//     //                                     }

//     //                                     // === LAUDOS ESPECÍFICOS ===
//     //                                     if (! empty($data['laudos']) && is_array($data['laudos'])) {
//     //                                         $laudosIds = array_filter($data['laudos']);

//     //                                         if (! empty($laudosIds)) {
//     //                                             $query->whereHas('laudos', function (Builder $q) use ($laudosIds) {
//     //                                                 $q->whereIn('laudos.id', $laudosIds);
//     //                                             });
//     //                                         }
//     //                                     }

//     //                                     // === TERNÁRIO: TEM / NÃO TEM LAUDOS ===
//     //                                     if (! empty($data['tem_laudos'])) {
//     //                                         if ($data['tem_laudos'] === 'true') {
//     //                                             $query->whereHas('laudos');
//     //                                         } elseif ($data['tem_laudos'] === 'false') {
//     //                                             $query->whereDoesntHave('laudos');
//     //                                         }
//     //                                     }

//     //                                     // === TERNÁRIO: COM / SEM APOIO (id_professor) ===
//     //                                     if (! empty($data['com_apoio'])) {
//     //                                         if ($data['com_apoio'] === 'true') {
//     //                                             $query->whereNotNull('id_professor');
//     //                                         } elseif ($data['com_apoio'] === 'false') {
//     //                                             $query->whereNull('id_professor');
//     //                                         }
//     //                                     }

//     //                                     $records = $query->get();

//     //                                     if ($records->isEmpty()) {
//     //                                         abort(400, 'Nenhum aluno encontrado com os filtros selecionados.');
//     //                                     }

//     //                                     $primeiro = $records->first();

//     //                                     if (! $user->can('exportarRelatorio', $primeiro)) {
//     //                                         abort(403);
//     //                                     }

//     //                                     $payload = static::alunoRelatorioService()->payloadBulkList($records);

//     //                                     $view        = $payload['view'];   // ex.: 'relatorios.BulkList.alunos-bulklist'
//     //                                     $dataPayload = $payload['data'];   // ['alunos' => Collection]

//     //                                     // Guarda tudo na sessão
//     //                                     session()->put('relatorios.bulk_list', [
//     //                                         'view' => $view,
//     //                                         'data' => $dataPayload,
//     //                                         'tipo' => RelatorioPdf::TIPO_BULK_LIST,
//     //                                     ]);

//     //                                     // Redireciona sem payload gigante na URL
//     //                                     $this->redirectRoute('relatorios.bulkList');
//     //                                 })
//     //                         ),
//     //                 ]),
//     //         ])
//     //         ->columns(1);
//     // }
// }
