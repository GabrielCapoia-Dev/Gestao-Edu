<?php

namespace App\Services\Relatorios;

use App\Models\EmpresaContratada;
use App\Models\Escola;
use App\Models\FeedbackPedido;
use App\Models\FeedbackPedidoItem;
use App\Models\Enums\ResultadoFeedbackPedido;
use App\Models\Pedido;
use App\Models\PedidoProblema;
use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Models\TipoStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class FeedbackPedidoAnalyticsService
{
    public const REPORT_GERAL = 'geral_satisfacao';

    public const REPORT_EMPRESAS = 'desempenho_empresas';

    public const REPORT_ESCOLAS = 'satisfacao_escolas';

    public const REPORT_LISTAGEM = 'listagem_filtrada';

    /** @var array<int, int>|null */
    private ?array $reabertoStatusIds = null;

    /**
     * @return array<string, string>
     */
    public function reportTypeOptions(): array
    {
        return [
            self::REPORT_GERAL => 'Satisfação geral',
            self::REPORT_EMPRESAS => 'Desempenho das empresas',
            self::REPORT_ESCOLAS => 'Satisfação das escolas',
            self::REPORT_LISTAGEM => 'Listagem filtrada',
        ];
    }

    public function reportTypeLabel(?string $reportType): string
    {
        return $this->reportTypeOptions()[$reportType] ?? $this->reportTypeOptions()[self::REPORT_GERAL];
    }

    public function normalizeReportType(?string $reportType): string
    {
        return match ($reportType) {
            'geral', 'graficos', self::REPORT_GERAL => self::REPORT_GERAL,
            'terceirizada', self::REPORT_EMPRESAS => self::REPORT_EMPRESAS,
            'escolas', self::REPORT_ESCOLAS => self::REPORT_ESCOLAS,
            'listagem', self::REPORT_LISTAGEM => self::REPORT_LISTAGEM,
            default => self::REPORT_GERAL,
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        if (isset($filters['periodo']) && is_array($filters['periodo'])) {
            $filters['data_inicio'] ??= $filters['periodo']['inicio'] ?? null;
            $filters['data_fim'] ??= $filters['periodo']['fim'] ?? null;
            unset($filters['periodo']);
        }

        $normalized = [];

        foreach ([
            'report_type',
            'data_inicio',
            'data_fim',
            'valor',
            'nivel_prioridade',
            'tipo_manutencao_id',
            'tipo_manutencao_opcao_id',
            'escola_id',
            'empresa_contratada_id',
            'resultado',
            'reabrir_pedido',
        ] as $key) {
            if (array_key_exists($key, $filters) && $filters[$key] !== null && $filters[$key] !== '') {
                $normalized[$key] = $filters[$key];
            }
        }

        $normalized['report_type'] = $this->normalizeReportType($normalized['report_type'] ?? null);

        foreach (['data_inicio', 'data_fim'] as $dateKey) {
            if (isset($normalized[$dateKey])) {
                $normalized[$dateKey] = Carbon::parse($normalized[$dateKey])->toDateString();
            }
        }

        if (array_key_exists('reabrir_pedido', $normalized)) {
            $normalized['reabrir_pedido'] = filter_var($normalized['reabrir_pedido'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return array_filter($normalized, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function assertReportFilters(array $filters): void
    {
        if (blank($filters['data_inicio'] ?? null) || blank($filters['data_fim'] ?? null)) {
            throw new InvalidArgumentException('Informe data de início e data de fim para gerar o relatório.');
        }

        if (Carbon::parse($filters['data_inicio'])->gt(Carbon::parse($filters['data_fim']))) {
            throw new InvalidArgumentException('A data de início não pode ser maior que a data de fim.');
        }
    }

    public function baseQuery(): Builder
    {
        return FeedbackPedido::query()
            ->with([
                'pedido.escola',
                'pedido.tipoManutencao',
                'pedido.empresaContratada',
                'pedido.historicos.statusNovo',
                'itens.problema',
            ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function query(array $filters = []): Builder
    {
        return $this->applyFilters($this->baseQuery(), $filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $filters = $this->normalizeFilters($filters);

        return $query
            ->when($filters['data_inicio'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereHas('pedido', fn (Builder $pedido): Builder => $pedido->whereDate('data_solicitacao', '>=', $date)))
            ->when($filters['data_fim'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereHas('pedido', fn (Builder $pedido): Builder => $pedido->whereDate('data_solicitacao', '<=', $date)))
            ->when($filters['valor'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->where('valor', $value))
            ->when(array_key_exists('reabrir_pedido', $filters), fn (Builder $builder): Builder => $this->applyReabertoHistoricoFilter($builder, (bool) $filters['reabrir_pedido']))
            ->when($filters['nivel_prioridade'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->whereHas('pedido', fn (Builder $pedido): Builder => $pedido->where('nivel_prioridade', $value)))
            ->when($filters['tipo_manutencao_id'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->whereHas('pedido', fn (Builder $pedido): Builder => $pedido->where('tipo_manutencao_id', $value)))
            ->when($filters['tipo_manutencao_opcao_id'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->whereHas('itens.problema', fn (Builder $problema): Builder => $problema->where('tipo_manutencao_opcao_id', $value)))
            ->when($filters['escola_id'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->whereHas('pedido', fn (Builder $pedido): Builder => $pedido->where('escola_id', $value)))
            ->when($filters['empresa_contratada_id'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->whereHas('pedido', fn (Builder $pedido): Builder => $pedido->where('empresa_contratada_id', $value)))
            ->when($filters['resultado'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->whereHas('itens', fn (Builder $item): Builder => $item->where('resultado', $value)));
    }

    public function firstPedidoDate(): ?string
    {
        $date = Pedido::query()
            ->min('data_solicitacao');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    /**
     * @return array<string, string>
     */
    public function noteOptions(): array
    {
        return FeedbackPedido::query()
            ->whereHas('pedido')
            ->select('valor')
            ->distinct()
            ->orderBy('valor')
            ->pluck('valor', 'valor')
            ->mapWithKeys(fn (int|string $value, int|string $key): array => [(string) $key => "{$value} estrela" . ((int) $value === 1 ? '' : 's')])
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    public function priorityOptions(): array
    {
        return Pedido::query()
            ->whereHas('feedbacks')
            ->whereNotNull('nivel_prioridade')
            ->select('nivel_prioridade')
            ->distinct()
            ->orderBy('nivel_prioridade')
            ->pluck('nivel_prioridade', 'nivel_prioridade')
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [$key instanceof \BackedEnum ? $key->value : $key => $value instanceof \BackedEnum ? $value->value : $value])
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function tipoManutencaoOptions(): array
    {
        $ids = Pedido::query()
            ->whereHas('feedbacks')
            ->whereNotNull('tipo_manutencao_id')
            ->select('tipo_manutencao_id')
            ->distinct()
            ->pluck('tipo_manutencao_id');

        return TipoManutencao::query()
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function tipoManutencaoOpcaoOptions(null|int|string $tipoManutencaoId = null): array
    {
        $ids = PedidoProblema::query()
            ->whereNotNull('tipo_manutencao_opcao_id')
            ->whereHas('pedido.feedbacks')
            ->when($tipoManutencaoId, fn (Builder $query, mixed $tipoId): Builder => $query->where('tipo_manutencao_id', $tipoId))
            ->select('tipo_manutencao_opcao_id')
            ->distinct()
            ->pluck('tipo_manutencao_opcao_id');

        return TipoManutencaoOpcao::query()
            ->whereIn('id', $ids)
            ->orderBy('texto')
            ->pluck('texto', 'id')
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function escolaOptions(): array
    {
        $ids = Pedido::query()
            ->whereHas('feedbacks')
            ->whereNotNull('escola_id')
            ->select('escola_id')
            ->distinct()
            ->pluck('escola_id');

        return Escola::query()
            ->where('ativo', true)
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function empresaOptions(): array
    {
        $ids = Pedido::query()
            ->whereHas('feedbacks')
            ->whereNotNull('empresa_contratada_id')
            ->select('empresa_contratada_id')
            ->distinct()
            ->pluck('empresa_contratada_id');

        return EmpresaContratada::query()
            ->whereIn('id', $ids)
            ->doSetorDoUsuario(Auth::user())
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    public function resultadoOptions(): array
    {
        return FeedbackPedidoItem::query()
            ->whereHas('feedback.pedido')
            ->whereNotNull('resultado')
            ->select('resultado')
            ->distinct()
            ->orderBy('resultado')
            ->pluck('resultado', 'resultado')
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [$key instanceof \BackedEnum ? $key->value : $key => $value instanceof \BackedEnum ? $value->label() : (ResultadoFeedbackPedido::tryFrom($value)?->label() ?? str_replace('_', ' ', ucfirst($value)))])
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    public function reabertoOptions(): array
    {
        if (! FeedbackPedido::query()->whereHas('pedido')->exists()) {
            return [];
        }

        $options = [];

        if ($this->applyReabertoHistoricoFilter(FeedbackPedido::query()->whereHas('pedido'), false)->exists()) {
            $options['0'] = 'Nao';
        }

        if ($this->applyReabertoHistoricoFilter(FeedbackPedido::query()->whereHas('pedido'), true)->exists()) {
            $options['1'] = 'Sim';
        }

        return $options;

        /*
        return FeedbackPedido::query()
            ->whereHas('pedido')
            ->select('reabrir_pedido')
            ->distinct()
            ->orderBy('reabrir_pedido')
            ->pluck('reabrir_pedido')
            ->mapWithKeys(fn (bool|int|string $value): array => [(string) (int) $value => $value ? 'Sim' : 'Não'])
            ->toArray();
    }

        */

    }

    /**
     * @return array{total:int,media:float,satisfacao:int,reabertos:int,criticas:int}
     */
    public function metrics(Builder $query): array
    {
        $total = (clone $query)->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'media' => 0.0,
                'satisfacao' => 0,
                'reabertos' => 0,
                'criticas' => 0,
            ];
        }

        $media = round((float) ((clone $query)->avg('valor') ?? 0), 2);

        return [
            'total' => $total,
            'media' => $media,
            'satisfacao' => $this->satisfactionFromAverage($media),
            'reabertos' => $this->countReabertos(clone $query),
            'criticas' => $this->countReabertos(clone $query),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function noteDistribution(Builder $query): array
    {
        $distribution = array_fill(1, 5, 0);

        (clone $query)
            ->selectRaw('valor, COUNT(*) as total')
            ->groupBy('valor')
            ->pluck('total', 'valor')
            ->each(function (int $total, int|string $nota) use (&$distribution): void {
                $nota = (int) $nota;

                if ($nota >= 1 && $nota <= 5) {
                    $distribution[$nota] = $total;
                }
            });

        return $distribution;
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function monthlyChartData(Collection $feedbacks): array
    {
        $dados = $feedbacks
            ->groupBy(fn (FeedbackPedido $feedback): string => $feedback->created_at?->format('Y-m') ?? 'sem-data')
            ->sortKeys()
            ->map->count();

        return [
            'labels' => $dados->keys()->values()->all(),
            'data' => $dados->values()->all(),
        ];
    }

    /**
     * @return array{labels: array<int, int>, data: array<int, int>}
     */
    public function noteChartData(Builder $query): array
    {
        $distribution = $this->noteDistribution($query);

        return [
            'labels' => array_keys($distribution),
            'data' => array_values($distribution),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rankingEmpresas(Collection $feedbacks): array
    {
        return $this->rankingPorRelacao(
            feedbacks: $feedbacks,
            relation: 'empresa',
            resolver: fn (FeedbackPedido $feedback): ?EmpresaContratada => $feedback->pedido?->empresaContratada,
            emptyLabel: 'Sem empresa'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rankingEscolas(Collection $feedbacks): array
    {
        return $this->rankingPorRelacao(
            feedbacks: $feedbacks,
            relation: 'escola',
            resolver: fn (FeedbackPedido $feedback): ?Escola => $feedback->pedido?->escola,
            emptyLabel: 'Sem escola'
        );
    }

    /**
     * @return array<string, array<string, array<int, int>>>
     */
    public function matrizNotasPorMes(Collection $feedbacks): array
    {
        $matriz = [];

        foreach ($feedbacks as $feedback) {
            $ano = $feedback->created_at?->format('Y') ?? 'Sem data';
            $mes = $feedback->created_at?->format('m/Y') ?? 'Sem data';

            $matriz[$ano][$mes] ??= [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
            $matriz[$ano][$mes][(int) $feedback->valor]++;
        }

        ksort($matriz);

        foreach ($matriz as &$meses) {
            ksort($meses);
        }

        return $matriz;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function matrizPorEmpresa(Collection $feedbacks): array
    {
        $resultado = [];

        foreach ($feedbacks->groupBy(fn (FeedbackPedido $feedback): string|int => $feedback->pedido?->empresaContratada?->getKey() ?? 'sem_empresa') as $empresaId => $grupo) {
            $empresa = $grupo->first()?->pedido?->empresaContratada;

            if (! $empresa) {
                continue;
            }

            $resultado[$empresaId] = [
                'empresa' => $empresa,
                'percentual' => $this->satisfactionPercent($grupo),
                'media' => round((float) $grupo->avg('valor'), 2),
                'total' => $grupo->count(),
                'reabertos' => $this->countReabertosCollection($grupo),
                'criticas' => $this->countReabertosCollection($grupo),
                'anos' => $this->matrizNotasPorMes($grupo),
            ];
        }

        uasort($resultado, fn (array $a, array $b): int => [$b['percentual'], $b['media'], $b['total']] <=> [$a['percentual'], $a['media'], $a['total']]);

        return $resultado;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, string>
     */
    public function formatFilters(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $formatted = [
            'Relatório' => $this->reportTypeLabel($filters['report_type'] ?? null),
        ];

        if (isset($filters['data_inicio'], $filters['data_fim'])) {
            $formatted['Período'] = Carbon::parse($filters['data_inicio'])->format('d/m/Y') . ' a ' . Carbon::parse($filters['data_fim'])->format('d/m/Y');
        }

        if (isset($filters['valor'])) {
            $formatted['Nota'] = (string) $filters['valor'];
        }

        if (isset($filters['nivel_prioridade'])) {
            $formatted['Prioridade'] = (string) $filters['nivel_prioridade'];
        }

        if (isset($filters['tipo_manutencao_id'])) {
            $formatted['Tipo de manutenção'] = TipoManutencao::query()->find($filters['tipo_manutencao_id'])?->nome ?? 'N/A';
        }

        if (isset($filters['tipo_manutencao_opcao_id'])) {
            $formatted['Opção do tipo'] = TipoManutencaoOpcao::query()->find($filters['tipo_manutencao_opcao_id'])?->texto ?? 'N/A';
        }

        if (isset($filters['escola_id'])) {
            $formatted['Escola'] = Escola::query()->find($filters['escola_id'])?->nome ?? 'N/A';
        }

        if (isset($filters['empresa_contratada_id'])) {
            $formatted['Empresa'] = EmpresaContratada::query()->find($filters['empresa_contratada_id'])?->nome ?? 'N/A';
        }

        if (isset($filters['resultado'])) {
            $formatted['Resultado por problema'] = (string) $filters['resultado'];
        }

        if (array_key_exists('reabrir_pedido', $filters)) {
            $formatted['Reabertura'] = $filters['reabrir_pedido'] ? 'Sim' : 'Não';
        }

        return $formatted;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rankingPorRelacao(Collection $feedbacks, string $relation, callable $resolver, string $emptyLabel): array
    {
        $rows = [];

        foreach ($feedbacks->groupBy(function (FeedbackPedido $feedback) use ($resolver, $emptyLabel): string {
            $model = $resolver($feedback);

            return $model?->getKey() ? (string) $model->getKey() : $emptyLabel;
        }) as $key => $grupo) {
            $model = $resolver($grupo->first());
            $nome = $model?->nome ?? $emptyLabel;

            $rows[] = [
                'id' => $model?->getKey(),
                'nome' => $nome,
                'relation' => $relation,
                'total' => $grupo->count(),
                'media' => round((float) $grupo->avg('valor'), 2),
                'satisfacao' => $this->satisfactionPercent($grupo),
                'reabertos' => $this->countReabertosCollection($grupo),
                'criticas' => $this->countReabertosCollection($grupo),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$b['satisfacao'], $b['media'], $b['total']] <=> [$a['satisfacao'], $a['media'], $a['total']]);

        return array_map(function (array $row): array {
            $row['pct_barra'] = $row['satisfacao'];

            return $row;
        }, array_slice($rows, 0, 10));
    }

    private function satisfactionPercent(Collection $feedbacks): int
    {
        $total = $feedbacks->count();

        if ($total === 0) {
            return 0;
        }

        return $this->satisfactionFromAverage((float) $feedbacks->avg('valor'));
    }

    private function satisfactionFromAverage(float $average): int
    {
        return (int) round(($average / 5) * 100);
    }

    private function countReabertos(Builder $query): int
    {
        return $this->applyReabertoHistoricoFilter($query, true)->count();
    }

    private function applyReabertoHistoricoFilter(Builder $query, bool $reaberto): Builder
    {
        $statusIds = $this->reabertoStatusIds();

        if ($statusIds === []) {
            return $reaberto ? $query->whereRaw('1 = 0') : $query;
        }

        $callback = fn (Builder $historico): Builder => $historico->whereIn('status_novo_id', $statusIds);

        return $reaberto
            ? $query->whereHas('pedido.historicos', $callback)
            : $query->whereDoesntHave('pedido.historicos', $callback);
    }

    private function countReabertosCollection(Collection $feedbacks): int
    {
        return $feedbacks
            ->filter(fn (FeedbackPedido $feedback): bool => $this->feedbackPossuiHistoricoReaberto($feedback))
            ->count();
    }

    public function feedbackPossuiHistoricoReaberto(FeedbackPedido $feedback): bool
    {
        $pedido = $feedback->pedido;

        if (! $pedido) {
            return false;
        }

        $statusIds = $this->reabertoStatusIds();

        if ($statusIds === []) {
            return false;
        }

        if ($pedido->relationLoaded('historicos')) {
            return $pedido->historicos
                ->contains(fn ($historico): bool => in_array((int) $historico->status_novo_id, $statusIds, true));
        }

        return $pedido->historicos()
            ->whereIn('status_novo_id', $statusIds)
            ->exists();
    }

    /**
     * @return array<int, int>
     */
    private function reabertoStatusIds(): array
    {
        if ($this->reabertoStatusIds !== null) {
            return $this->reabertoStatusIds;
        }

        return $this->reabertoStatusIds = TipoStatus::query()
            ->where('nome', 'Reaberto')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
