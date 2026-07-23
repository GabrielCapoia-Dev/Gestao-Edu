<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 10px; }
        h1 { color: #123b78; margin: 0 0 4px; font-size: 21px; }
        h2 { margin: 20px 0 8px; font-size: 14px; color: #123b78; }
        p { margin: 3px 0; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .meta td { border: 1px solid #d8e1ee; padding: 7px; width: 25%; }
        .label { display: block; color: #64748b; font-size: 8px; text-transform: uppercase; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th { background: #123b78; color: white; text-align: left; padding: 6px; }
        table.list td { border: 1px solid #d8e1ee; padding: 6px; vertical-align: top; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ $evento->titulo }}</h1>
    @if ($evento->descricao)<p>{{ $evento->descricao }}</p>@endif

    <table class="meta">
        <tr>
            <td><span class="label">Data</span>{{ $evento->data_inicio->format('d/m/Y') }}</td>
            <td><span class="label">Horário</span>{{ $evento->data_inicio->format('H:i') }}–{{ $evento->data_fim->format('H:i') }}</td>
            <td><span class="label">Local</span>{{ $evento->local ?: 'Não informado' }}</td>
            <td><span class="label">Status</span>{{ $evento->status?->label() ?? 'Não informado' }}</td>
        </tr>
    </table>

    <h2>Escolas e turmas participantes</h2>
    <table class="list">
        <thead><tr><th>Escola</th><th>Turmas</th><th>Estimativa de transporte</th></tr></thead>
        <tbody>
        @forelse ($evento->escolasAgendadas as $agendamento)
            @php($turmasEscola = $turmasPorEscola->get($agendamento->escola_id, collect()))
            <tr>
                <td>{{ $agendamento->escola?->nome ?? 'Escola não informada' }}</td>
                <td>{{ $turmasEscola->map(fn ($turma) => trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome))->join(', ') ?: 'Não informadas' }}</td>
                <td>{{ $agendamento->precisa_transporte ? number_format((int) $agendamento->quantidade_estimada_transporte, 0, ',', '.').' estudante(s)' : 'Não solicitado' }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Evento destinado a todas as escolas do escopo.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Veículos, motoristas e turmas</h2>
    <table class="list">
        <thead><tr><th>Veículo</th><th>Motorista</th><th>Escolas e turmas atendidas</th></tr></thead>
        <tbody>
        @forelse ($evento->alocacoesTransporteAtivas as $alocacao)
            <tr>
                <td>{{ $alocacao->veiculo?->identificacao ?: 'Veículo' }} — {{ $alocacao->veiculo?->placa }}</td>
                <td>{{ $alocacao->motoristaNomeExibicao() }}</td>
                <td>
                    {{ $alocacao->turmas->map(fn ($turma) => collect([$turma->escola?->nome, trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome)])->filter()->implode(' — '))->join('; ') ?: 'Nenhuma turma vinculada' }}
                </td>
            </tr>
        @empty
            <tr><td colspan="3">Nenhum veículo alocado.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
