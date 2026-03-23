<?php

namespace App\Filament\Pages;

use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\TurmaComponenteProfessor;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Services\UserService;
use BackedEnum;
use UnitEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class Relatorios extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;
    protected static ?string $navigationLabel = 'Relatórios';
    protected static ?string $title = 'Relatório de Turmas';
    protected static string|UnitEnum|null $navigationGroup = 'Gestão Escolar';
    protected string $view = 'filament.pages.relatorios';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        return $user->hasPermissionTo('Listar Relatórios');
    }


    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar_tudo')
                ->label('Exportar Relatório Geral')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(function () {
                    /** @var \App\Models\User */
                    $user = Auth::user();
                    return $user->hasPermissionTo('Exportar Relatórios');
                })
                ->modalHeading('Exportar Relatório Geral')
                ->modalDescription('Esta ação irá exportar TODOS os registros de Turmas. Dependendo da quantidade de dados, isso pode causar lentidão temporária. Deseja continuar?')
                ->modalSubmitActionLabel('Sim, exportar tudo')
                ->action(function () {
                    return $this->exportarRelatorioGeral();
                }),
        ];
    }

    public function exportarRelatorioGeral()
    {
        $dados = DB::table('turma_componente_professor as tcp')
            ->join('turmas', 'turmas.id', '=', 'tcp.turma_id')
            ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
            ->join('series', 'series.id', '=', 'turmas.id_serie')
            ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
            ->leftJoin('professores', 'professores.id', '=', 'tcp.professor_id')
            ->select([
                'escolas.nome as escola',
                'series.nome as serie',
                'turmas.nome as turma',
                DB::raw("CASE turmas.turno 
                                    WHEN 'manha' THEN 'Manhã'
                                    WHEN 'tarde' THEN 'Tarde'
                                    WHEN 'noite' THEN 'Noite'
                                    WHEN 'integral' THEN 'Integral'
                                    ELSE turmas.turno
                                END as turno"),
                'cc.nome as componente',
                DB::raw("COALESCE(professores.nome, 'Sem professor') as professor"),
                DB::raw("COALESCE(professores.matricula, 'Não informado') as matricula"),
                DB::raw("COALESCE(professores.email, 'Não informado') as email"),
            ])

            ->orderBy('escolas.nome')
            ->orderBy('series.nome')
            ->orderBy('turmas.nome')
            ->orderBy('cc.nome')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Relatório de Turmas');

        // Cabeçalhos
        $headers = [
            'Escola',
            'Série',
            'Turma',
            'Turno',
            'Componente Curricular',
            'Professor',
            'Matrícula',
            'E-mail',
        ];
        $sheet->fromArray($headers, null, 'A1');

        // Estilo do cabeçalho
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '074f9b'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);


        // Dados
        $row = 2;
        foreach ($dados as $item) {
            $sheet->setCellValue("A{$row}", $item->escola);
            $sheet->setCellValue("B{$row}", $item->serie);
            $sheet->setCellValue("C{$row}", $item->turma);
            $sheet->setCellValue("D{$row}", $item->turno);
            $sheet->setCellValue("E{$row}", $item->componente);
            $sheet->setCellValue("F{$row}", $item->professor);
            $sheet->setCellValue("G{$row}", $item->matricula);
            $sheet->setCellValue("H{$row}", $item->email);
            $row++;
        }

        // Estilo dos dados
        $lastRow = $row - 1;
        $dataStyle = [
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];
        $sheet->getStyle("A2:H{$lastRow}")->applyFromArray($dataStyle);

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        // Gerar arquivo
        $filename = 'relatorio_turmas_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $path = storage_path("app/public/{$filename}");

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        Notification::make()
            ->title('Relatório gerado com sucesso!')
            ->body("Total de registros: " . ($row - 2))
            ->success()
            ->send();

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TurmaComponenteProfessor::query()
                    ->join('turmas', 'turmas.id', '=', 'turma_componente_professor.turma_id')
                    ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
                    ->join('series', 'series.id', '=', 'turmas.id_serie')
                    ->join('componentes_curriculares', 'componentes_curriculares.id', '=', 'turma_componente_professor.componente_curricular_id')
                    ->leftJoin('professores', 'professores.id', '=', 'turma_componente_professor.professor_id')
                    ->select([
                        'turma_componente_professor.id',
                        'escolas.nome as escola_nome',
                        'series.nome as serie_nome',
                        'turmas.nome as turma_nome',
                        'turmas.turno',
                        'componentes_curriculares.nome as componente_nome',
                        'professores.nome as professor_nome',
                        'turmas.id_escola',
                        'turmas.id_serie',
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('escola_nome')
                    ->label('Escola')
                    ->searchable(
                        query: fn(Builder $query, string $search) =>
                        $query->where('escolas.nome', 'like', "%{$search}%")
                    )
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                        $query->orderBy('escolas.nome', $direction)
                    ),

                Tables\Columns\TextColumn::make('serie_nome')
                    ->label('Série')
                    ->searchable(
                        query: fn(Builder $query, string $search) =>
                        $query->where('series.nome', 'like', "%{$search}%")
                    )
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                        $query->orderBy('series.nome', $direction)
                    ),

                Tables\Columns\TextColumn::make('turma_nome')
                    ->label('Turma')
                    ->searchable(
                        query: fn(Builder $query, string $search) =>
                        $query->where('turmas.nome', 'like', "%{$search}%")
                    )
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                        $query->orderBy('turmas.nome', $direction)
                    ),

                Tables\Columns\TextColumn::make('turno')
                    ->label('Turno')
                    ->badge()
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'manha' => 'Manhã',
                        'tarde' => 'Tarde',
                        'noite' => 'Noite',
                        'integral' => 'Integral',
                        default => $state,
                    })
                    ->color(fn(?string $state) => match ($state) {
                        'manha' => 'info',
                        'tarde' => 'warning',
                        'noite' => 'gray',
                        'integral' => 'success',
                        default => 'secondary',
                    })
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                        $query->orderBy('turmas.turno', $direction)
                    ),

                Tables\Columns\TextColumn::make('componente_nome')
                    ->label('Componente Curricular')
                    ->searchable(
                        query: fn(Builder $query, string $search) =>
                        $query->where('componentes_curriculares.nome', 'like', "%{$search}%")
                    )
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                        $query->orderBy('componentes_curriculares.nome', $direction)
                    ),

                Tables\Columns\TextColumn::make('professor_nome')
                    ->label('Professor')
                    ->default('Sem professor')
                    ->searchable(
                        query: fn(Builder $query, string $search) =>
                        $query->where('professores.nome', 'like', "%{$search}%")
                    )
                    ->sortable(
                        query: fn(Builder $query, string $direction) =>
                        $query->orderBy('professores.nome', $direction)
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('escola')
                    ->label('Escola')
                    ->options(fn() => Escola::pluck('nome', 'id'))
                    ->searchable()
                    ->query(
                        fn(Builder $query, array $data) =>
                        $data['value']
                            ? $query->where('turmas.id_escola', $data['value'])
                            : $query
                    ),

                Tables\Filters\SelectFilter::make('serie')
                    ->label('Série')
                    ->options(fn() => Serie::pluck('nome', 'id'))
                    ->searchable()
                    ->query(
                        fn(Builder $query, array $data) =>
                        $data['value']
                            ? $query->where('turmas.id_serie', $data['value'])
                            : $query
                    ),

                Tables\Filters\SelectFilter::make('turno')
                    ->label('Turno')
                    ->options([
                        'manha' => 'Manhã',
                        'tarde' => 'Tarde',
                        'noite' => 'Noite',
                        'integral' => 'Integral',
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $data['value']
                            ? $query->where('turmas.turno', $data['value'])
                            : $query
                    ),
            ])
            ->bulkActions([
                FilamentExportBulkAction::make('exportar')
                    ->label('Exportar XLSX')
                    ->defaultFormat('xlsx')
                    ->directDownload()
                    ->visible(function ($records) {
                        /** @var \App\Models\User */
                        $user = Auth::user();
                        return $user->hasPermissionTo('Exportar Relatórios');
                    })
                    ->defaultPageOrientation('landscape'),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
