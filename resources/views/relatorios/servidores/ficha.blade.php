<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ficha funcional — {{ $servidor->nome }}</title>
    <style>
        @page { margin: 30px 34px 48px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172b4d; font: 10px DejaVu Sans, sans-serif; line-height: 1.45; }
        .top { padding: 18px 20px; color: #fff; background: #17477d; border-radius: 9px; }
        .brand { margin: 0 0 5px; color: #c8e2ff; font-size: 8px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; }
        h1 { margin: 0; font-size: 20px; }
        .subtitle { margin: 5px 0 0; color: #e5f1ff; font-size: 9px; }
        .meta { margin-top: 8px; color: #d1e5fa; font-size: 8px; }
        .stats { width: 100%; margin: 12px 0 17px; border-spacing: 6px 0; }
        .stats td { width: 33.33%; padding: 9px 11px; border: 1px solid #d8e4f2; border-radius: 7px; background: #f5f8fc; }
        .stats small { display: block; color: #647b9a; font-size: 7px; text-transform: uppercase; }
        .stats strong { display: block; margin-top: 2px; color: #153d70; font-size: 12px; }
        .section { margin: 15px 0 0; page-break-inside: avoid; }
        h2 { margin: 0 0 7px; padding-bottom: 5px; border-bottom: 1px solid #ccdaeb; color: #184a80; font-size: 12px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { width: 50%; padding: 5px 8px 5px 0; vertical-align: top; }
        .label { display: block; color: #7185a0; font-size: 8px; }
        .value { display: block; color: #1a2e49; font-size: 9px; overflow-wrap: anywhere; }
        table.list { width: 100%; border-collapse: collapse; }
        .list th, .list td { padding: 5px 6px; border-bottom: 1px solid #e2eaf3; text-align: left; vertical-align: top; }
        .list th { color: #5d7391; background: #f3f7fb; font-size: 8px; }
        .list td { font-size: 8px; overflow-wrap: anywhere; }
        .muted { color: #7185a0; }
        .pill { display: inline-block; padding: 2px 6px; border-radius: 8px; color: #315d8a; background: #eaf2fb; }
        .footer { position: fixed; right: 0; bottom: -28px; left: 0; padding-top: 6px; border-top: 1px solid #d8e4f2; color: #70839d; font-size: 8px; }
        .footer .page { float: right; }
    </style>
</head>
<body>
    <header class="top">
        <p class="brand">Prefeitura Municipal de Umuarama · Secretaria de Educação</p>
        <h1>Ficha funcional</h1>
        <p class="subtitle">Cadastro consolidado de {{ $servidor->nome }}</p>
        <p class="meta">Documento gerado em {{ $geradoEm->format('d/m/Y H:i') }} · Registro {{ $servidor->getKey() }}</p>
    </header>

    <table class="stats">
        <tr>
            <td><small>Situação cadastral</small><strong>{{ ucfirst($servidor->status ?? 'Não informada') }}</strong></td>
            <td><small>Saldo eleitoral disponível</small><strong>{{ $saldoDisponivel }} dia(s)</strong></td>
            <td><small>Saldo líquido aprovado</small><strong>{{ $saldoAprovado }} dia(s)</strong></td>
        </tr>
    </table>

    <section class="section">
        <h2>Identificação e contato</h2>
        <table class="grid"><tr>
            <td><span class="label">Nome completo</span><span class="value">{{ $servidor->nome }}</span></td>
            <td><span class="label">CPF</span><span class="value">{{ \App\Models\Pessoa::formatarCpf($servidor->cpf) ?: 'Não informado' }}</span></td>
        </tr><tr>
            <td><span class="label">E-mail</span><span class="value">{{ $servidor->email ?: 'Não informado' }}</span></td>
            <td><span class="label">Telefone</span><span class="value">{{ $servidor->telefone ?: 'Não informado' }}</span></td>
        </tr><tr>
            <td><span class="label">Cadastro criado</span><span class="value">{{ $servidor->created_at?->format('d/m/Y H:i') ?: '—' }}</span></td>
            <td><span class="label">Última atualização</span><span class="value">{{ $servidor->updated_at?->format('d/m/Y H:i') ?: '—' }}</span></td>
        </tr></table>
        @if (filled($servidor->observacoes))
            <p><span class="label">Observações</span><span class="value">{{ $servidor->observacoes }}</span></p>
        @endif
    </section>

    <section class="section">
        <h2>Vínculos funcionais e lotação</h2>
        <table class="grid"><tr>
            <td><span class="label">Carga horária</span><span class="value">{{ $servidor->cargaHorariaLabel() }}</span></td>
            <td><span class="label">Jornada</span><span class="value">{{ $servidor->jornadaLabel() }}</span></td>
        </tr><tr>
            <td><span class="label">Lotação</span><span class="value">{{ $servidor->lotacaoLabel() }}</span></td>
            <td><span class="label">Setor</span><span class="value">{{ $servidor->setor?->nome ?: 'Não informado' }}</span></td>
        </tr><tr>
            <td colspan="2"><span class="label">Escolas vinculadas / assessoradas</span><span class="value">{{ $escolas !== [] ? implode(' · ', $escolas) : 'Nenhuma escola vinculada' }}</span></td>
        </tr></table>
        @if ($funcoes !== [])
            <table class="list"><thead><tr><th>Cargo</th><th>Situação</th><th>Escola / setor</th><th>Matrícula</th><th>Portaria</th><th>Período</th><th>Escolas assessoradas</th></tr></thead><tbody>
                @foreach ($funcoes as $funcao)
                    <tr>
                        <td>{{ $funcao['cargo'] }}</td><td>{{ ucfirst($funcao['status'] ?? '—') }}</td>
                        <td>{{ collect([$funcao['escola'], $funcao['setor']])->filter()->join(' · ') ?: '—' }}</td>
                        <td>{{ $funcao['matricula'] ?: '—' }}</td><td>{{ $funcao['portaria'] ?: '—' }}</td>
                        <td>{{ $funcao['inicio'] ?: '—' }} – {{ $funcao['fim'] ?: 'Atual' }}</td>
                        <td>{{ ($funcao['escolas_assessoradas'] ?? []) !== [] ? implode(', ', $funcao['escolas_assessoradas']) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody></table>
        @endif
        @if (($snapshot['cargo'] ?? []) !== [])
            <h2>Histórico consolidado de cargos e matrículas</h2>
            <table class="list"><thead><tr><th>Cargo</th><th>Escola / setor</th><th>Matrícula</th><th>Turno</th><th>Situação</th><th>Período</th></tr></thead><tbody>
                @foreach ($snapshot['cargo'] as $vinculo)
                    <tr>
                        <td>{{ $vinculo['cargo'] ?: '—' }}</td>
                        <td>{{ collect([$vinculo['escola'] ?? null, $vinculo['setor'] ?? null])->filter()->join(' · ') ?: '—' }}</td>
                        <td>{{ $vinculo['matricula'] ?: '—' }}</td>
                        <td>{{ $vinculo['turno'] ?? '—' }}</td>
                        <td>{{ ucfirst($vinculo['status'] ?? '—') }}</td>
                        <td>{{ filled($vinculo['inicio'] ?? null) ? \Illuminate\Support\Carbon::parse($vinculo['inicio'])->format('d/m/Y') : '—' }} – {{ filled($vinculo['fim'] ?? null) ? \Illuminate\Support\Carbon::parse($vinculo['fim'])->format('d/m/Y') : 'Atual' }}</td>
                    </tr>
                @endforeach
            </tbody></table>
        @endif
    </section>

    <section class="section">
        <h2>Matrículas</h2>
        @if ($snapshot['matriculas'] !== [])
            <table class="list"><thead><tr><th>Número</th><th>Turno</th><th>Jornada</th><th>Situação</th></tr></thead><tbody>
                @foreach ($snapshot['matriculas'] as $matricula)
                    <tr><td>{{ $matricula['matricula'] ?: '—' }}</td><td>{{ $matricula['turno'] ?: '—' }}</td><td>{{ $matricula['jornada'] ? 'Sim' : 'Não' }}</td><td>{{ $matricula['arquivada'] ? 'Arquivada' : 'Ativa' }}</td></tr>
                @endforeach
            </tbody></table>
        @else <p class="muted">Nenhuma matrícula registrada.</p> @endif
    </section>

    <section class="section">
        <h2>Atuação pedagógica</h2>
        @if ($snapshot['pedagogico'] !== [])
            <table class="list"><thead><tr><th>Escola</th><th>Série</th><th>Turma</th><th>Componente</th><th>Vínculo</th></tr></thead><tbody>
                @foreach ($snapshot['pedagogico'] as $vinculo)
                    <tr><td>{{ $vinculo['escola'] ?: '—' }}</td><td>{{ $vinculo['serie'] ?: '—' }}</td><td>{{ $vinculo['turma'] ?: '—' }}</td><td>{{ $vinculo['componente'] ?: '—' }}</td><td>{{ $vinculo['ativo'] ? 'Ativo' : 'Inativo' }}</td></tr>
                @endforeach
            </tbody></table>
        @else <p class="muted">Nenhuma atribuição pedagógica registrada.</p> @endif
    </section>

    <section class="section">
        <h2>Acesso ao sistema</h2>
        @if ($acesso)
            <table class="grid"><tr>
                <td><span class="label">Conta</span><span class="value">{{ $acesso['email'] ?: 'Não informada' }}</span></td>
                <td><span class="label">Situação da conta</span><span class="value">{{ $acesso['status'] }}</span></td>
            </tr><tr>
                <td><span class="label">E-mail aprovado</span><span class="value">{{ $acesso['aprovado'] ? 'Sim' : 'Não' }}</span></td>
                <td><span class="label">Perfis</span><span class="value">{{ $acesso['perfis'] !== [] ? implode(', ', $acesso['perfis']) : 'Nenhum perfil atribuído' }}</span></td>
            </tr></table>
        @else <p class="muted">Nenhuma conta de acesso vinculada.</p> @endif
    </section>

    <section class="section">
        <h2>Histórico do saldo eleitoral</h2>
        @if ($movimentacoesSaldo->isNotEmpty())
            <table class="list"><thead><tr><th>Data</th><th>Movimentação</th><th>Dias</th><th>Datas</th><th>Situação</th><th>Solicitado por / analisado por</th><th>Justificativa / observação</th></tr></thead><tbody>
                @foreach ($movimentacoesSaldo as $movimento)
                    <tr>
                        <td>{{ ($movimento->decidido_em ?? $movimento->created_at)?->format('d/m/Y H:i') }}</td>
                        <td>{{ match ($movimento->tipo) { 'adicao' => 'Adição', 'estorno' => 'Estorno', default => 'Uso' } }}@if($movimento->movimentoOrigem) <span class="muted">(uso original #{{ $movimento->movimento_origem_id }})</span>@endif</td>
                        <td>{{ $movimento->dias }}</td>
                        <td>{{ collect($movimento->datas ?? [])->map(fn ($data) => \Illuminate\Support\Carbon::parse($data)->format('d/m/Y'))->join(', ') ?: '—' }}</td>
                        <td>{{ ucfirst($movimento->status) }}</td>
                        <td>{{ $movimento->solicitante?->name ?? '—' }} / {{ $movimento->aprovador?->name ?? '—' }}</td>
                        <td>{{ $movimento->observacao ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody></table>
        @else <p class="muted">Nenhuma movimentação de saldo eleitoral.</p> @endif
    </section>

    <section class="section">
        <h2>Histórico de movimentações funcionais</h2>
        @if ($movimentacoesServidor->isNotEmpty())
            <table class="list"><thead><tr><th>Data</th><th>Responsável</th><th>Campo</th><th>Antes</th><th>Depois</th></tr></thead><tbody>
                @foreach ($movimentacoesServidor as $movimentacao)
                    @foreach ($movimentacao->alteracoes ?? [] as $campo => $alteracao)
                        <tr>
                            <td>{{ $movimentacao->ocorrido_em?->format('d/m/Y H:i') }}</td><td>{{ $movimentacao->usuario?->name ?? 'Sistema' }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $campo)) }}</td>
                            <td>{{ is_array($alteracao['antes'] ?? null) ? json_encode($alteracao['antes'], JSON_UNESCAPED_UNICODE) : ($alteracao['antes'] ?? '—') }}</td>
                            <td>{{ is_array($alteracao['depois'] ?? null) ? json_encode($alteracao['depois'], JSON_UNESCAPED_UNICODE) : ($alteracao['depois'] ?? '—') }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody></table>
        @else <p class="muted">Nenhuma movimentação funcional registrada.</p> @endif
    </section>

    <footer class="footer">Documento interno · Dados conforme registros disponíveis no Gestão Edu <span class="page">Ficha funcional</span></footer>
</body>
</html>
