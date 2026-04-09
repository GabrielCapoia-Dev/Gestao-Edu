<?php

namespace App\Filament\Admin\Pages;

use App\Services\Inventario\InventarioContextService;
use App\Services\Inventario\InventarioDataService;
use App\Services\Inventario\InventarioService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Inventarios extends Page
{
    protected string $view = 'filament.pages.inventarios';

    protected static ?string $title = 'Panorama Geral dos Inventários';

    protected static ?string $slug = 'inventarios';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';

    public string $busca = '';

    public int $porPagina = 6;

    public int $paginaAtual = 1;

    public string $tipoBaixa = 'todas';

    public bool $slideOverAberto = false;

    public array $movimentacoes = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! ($user?->hasPermissionTo('Listar Inventários') ?? false)) {
            return false;
        }

        return app(InventarioContextService::class)->ehGestorGeral($user);
    }

    public function updatedBusca(): void
    {
        $this->paginaAtual = 1;
    }

    public function updatedPorPagina(): void
    {
        $this->paginaAtual = 1;
    }

    public function getInventariosResumoBaseProperty(): Collection
    {
        return $this->dataService()->inventariosResumo([
            'busca' => $this->busca,
        ]);
    }

    public function getInventariosResumoProperty(): Collection
    {
        return $this->inventariosResumoBase
            ->slice(($this->paginaAtual - 1) * $this->porPagina, $this->porPagina)
            ->values();
    }

    public function getMetricasProperty(): object
    {
        return $this->dataService()->metricasInventarios($this->inventariosResumoBase);
    }

    public function getComparativoValorProperty(): Collection
    {
        return $this->dataService()->comparativoValorPorEscola($this->inventariosResumoBase);
    }

    public function getComparativoBaixasProperty(): Collection
    {
        return $this->dataService()->comparativoBaixasPorEscola(
            $this->inventariosResumoBase,
            $this->tipoBaixa,
        );
    }

    public function getTiposBaixaOptionsProperty(): array
    {
        return $this->dataService()->tiposBaixaDisponiveis();
    }

    public function getTipoBaixaSelecionadaLabelProperty(): string
    {
        return $this->dataService()->rotuloTipoBaixa($this->tipoBaixa);
    }

    public function getPodeExportarProperty(): bool
    {
        return Auth::user()?->hasPermissionTo('Exportar Relatórios') ?? false;
    }

    public function getMesesRelatorioEnviosOptionsProperty(): array
    {
        return $this->dataService()->mesesRelatorioOptions();
    }

    public function getAnosRelatorioEnviosOptionsProperty(): array
    {
        return $this->dataService()->anosRelatorioEnviosOptions();
    }

    public function getEscolasRelatorioEnviosOptionsProperty(): array
    {
        return $this->dataService()->escolasRelatorioEnviosOptions($this->busca);
    }

    public function getPaginacaoProperty(): array
    {
        $total = $this->inventariosResumoBase->count();
        $totalPaginas = $total > 0 ? (int) ceil($total / $this->porPagina) : 1;

        return [
            'total' => $total,
            'porPagina' => $this->porPagina,
            'paginaAtual' => $this->paginaAtual,
            'totalPaginas' => $totalPaginas,
            'de' => $total === 0 ? 0 : ($this->paginaAtual - 1) * $this->porPagina + 1,
            'ate' => min($this->paginaAtual * $this->porPagina, $total),
        ];
    }

    public function abrirSlideOver(): void
    {
        $inventarioIds = $this->inventariosResumoBase->pluck('inventario_id')->all();
        $this->movimentacoes = $this->dataService()
            ->movimentacoesGeraisInventarios($inventarioIds)
            ->take(100)
            ->values()
            ->all();
        $this->slideOverAberto = true;
    }

    public function fecharSlideOver(): void
    {
        $this->slideOverAberto = false;
        $this->movimentacoes = [];
    }

    public function mudarPagina(int $pagina): void
    {
        $total = $this->inventariosResumoBase->count();
        $totalPaginas = max(1, (int) ceil($total / $this->porPagina));

        $this->paginaAtual = max(1, min($pagina, $totalPaginas));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('movimentacoes')
                ->label('Movimentacoes')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->action(fn () => $this->abrirSlideOver()),
            Action::make('exportarEnvios')
                ->label('Exportar envios')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => $this->podeExportar)
                ->modalHeading('Exportar envios para as escolas')
                ->modalDescription('Gere um consolidado agrupado por escola, respeitando o contexto do panorama e com opcao mensal.')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Exportar')
                ->schema([
                    TextInput::make('busca')
                        ->label('Busca')
                        ->default(fn (): string => $this->busca)
                        ->maxLength(255)
                        ->placeholder('Escola ou inventario'),
                    Select::make('escolas')
                        ->label('Escolas')
                        ->options(fn (): array => $this->escolasRelatorioEnviosOptions)
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->placeholder('Todas as escolas visiveis')
                        ->helperText('Se nada for selecionado, o relatorio considera todas as escolas do contexto filtrado.'),
                    Select::make('periodo')
                        ->label('Periodo')
                        ->options([
                            'geral' => 'Geral',
                            'mensal' => 'Por mes',
                        ])
                        ->default('geral')
                        ->native(false)
                        ->live(),
                    Select::make('mes')
                        ->label('Mes de referencia')
                        ->options($this->mesesRelatorioEnviosOptions)
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('periodo') === 'mensal')
                        ->required(fn (Get $get): bool => $get('periodo') === 'mensal'),
                    Select::make('ano')
                        ->label('Ano de referencia')
                        ->options(fn (): array => $this->anosRelatorioEnviosOptions)
                        ->default((string) now()->year)
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('periodo') === 'mensal')
                        ->required(fn (Get $get): bool => $get('periodo') === 'mensal'),
                    Select::make('formato')
                        ->label('Formato')
                        ->options([
                            'xlsx' => 'Planilha (XLSX)',
                            'pdf' => 'PDF',
                        ])
                        ->default('xlsx')
                        ->native(false)
                        ->required(),
                ])
                ->action(fn (array $data): mixed => $this->redirecionarExportacaoEnvios($data)),
            Action::make('novoInventario')
                ->label('Novo inventario')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn (): bool => $this->inventarioService()->escolasSemInventario() !== [])
                ->schema([
                    Select::make('escola_id')
                        ->label('Escola')
                        ->options($this->inventarioService()->escolasSemInventario())
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->inventarioService()->criar(
                            (int) $data['escola_id'],
                            null,
                            Auth::user(),
                        );

                        Notification::make()
                            ->title('Inventario criado com sucesso.')
                            ->body('A escola selecionada agora possui um inventario proprio.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function dataService(): InventarioDataService
    {
        return app(InventarioDataService::class);
    }

    protected function inventarioService(): InventarioService
    {
        return app(InventarioService::class);
    }

    protected function redirecionarExportacaoEnvios(array $data): mixed
    {
        $formato = ($data['formato'] ?? 'xlsx') === 'pdf' ? 'pdf' : 'xlsx';
        $periodo = ($data['periodo'] ?? 'geral') === 'mensal' ? 'mensal' : 'geral';

        $params = array_filter([
            'busca' => trim((string) ($data['busca'] ?? $this->busca)),
            'escolas' => collect($data['escolas'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter()
                ->values()
                ->all(),
            'periodo' => $periodo,
            'mes' => $periodo === 'mensal' ? (int) ($data['mes'] ?? 0) : null,
            'ano' => $periodo === 'mensal' ? (int) ($data['ano'] ?? now()->year) : null,
        ], fn (mixed $value): bool => ! ($value === null || $value === '' || $value === []));

        return $this->redirect(route("inventarios.relatorio.envios.{$formato}", $params), navigate: false);
    }
}
