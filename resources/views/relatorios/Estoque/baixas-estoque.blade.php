<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatorio de Baixas de Estoque</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .muted { color: #6b7280; font-size: 11px; }
        .cards { width: 100%; margin: 14px 0 18px; border-collapse: collapse; }
        .cards td { width: 25%; border: 1px solid #d1d5db; padding: 10px; vertical-align: top; }
        .cards .label { font-size: 10px; text-transform: uppercase; color: #6b7280; }
        .cards .value { font-size: 18px; font-weight: bold; margin-top: 4px; }
        table.listagem { width: 100%; border-collapse: collapse; }
        table.listagem th, table.listagem td { border: 1px solid #d1d5db; padding: 7px; text-align: left; vertical-align: top; }
        table.listagem th { background: #f3f4f6; font-size: 11px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Relatorio de Baixas de Estoque</h1>
    <p class="muted">
        Exportado em {{ $dataExportacao->format('d/m/Y H:i') }}
        @if($usuarioExportacao)
            por {{ $usuarioExportacao->name }}
        @endif
    </p>

    @if(!empty($filtros))
        <p class="muted">
            Filtros:
            @foreach($filtros as $chave => $valor)
                {{ ucfirst($chave) }}: {{ $valor }}@if(!$loop->last) | @endif
            @endforeach
        </p>
    @endif

    <table class="cards">
        <tr>
            <td>
                <div class="label">Registros</div>
                <div class="value">{{ $metricas->total_registros }}</div>
            </td>
            <td>
                <div class="label">Qtd. Baixada</div>
                <div class="value">{{ number_format($metricas->quantidade_total, 3, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Itens Afetados</div>
                <div class="value">{{ $metricas->itens_afetados }}</div>
            </td>
            <td>
                <div class="label">Motivo Frequente</div>
                <div class="value" style="font-size: 14px">{{ $metricas->motivo_mais_frequente }}</div>
            </td>
        </tr>
    </table>

    <table class="listagem">
        <thead>
            <tr>
                <th>Data</th>
                <th>Item</th>
                <th>Categoria</th>
                <th>Motivo</th>
                <th>Descricao</th>
                <th>Qtd.</th>
                <th>Saldo Antes</th>
                <th>Saldo Depois</th>
                <th>Registrado Por</th>
            </tr>
        </thead>
        <tbody>
            @forelse($baixas as $baixa)
                <tr>
                    <td>{{ $baixa->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $baixa->estoque?->item?->nome ?? 'N/A' }}</td>
                    <td>{{ $baixa->estoque?->item?->tipo_item?->label() ?? 'N/A' }}</td>
                    <td>{{ $baixa->motivo_label }}</td>
                    <td>{{ $baixa->descricao }}</td>
                    <td class="right">{{ number_format((float) $baixa->quantidade, 3, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $baixa->saldo_anterior, 3, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $baixa->saldo_posterior, 3, ',', '.') }}</td>
                    <td>{{ $baixa->registrado_por ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">Nenhuma baixa encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
