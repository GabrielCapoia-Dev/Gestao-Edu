@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Romaneio de Inventarios')
@section('reportSubtitle', $reportSubtitle ?? 'Separacao consolidada dos pedidos aprovados')

@section('styles')
    table.listagem {
        width: 100%;
        border-collapse: collapse;
        font-size: 9px;
    }

    table.listagem th,
    table.listagem td {
        border: 1px solid #d1d5db;
        padding: 6px;
        text-align: left;
        vertical-align: top;
    }

    table.listagem th {
        background: #f1f5f9;
        font-size: 10px;
    }

    .right {
        text-align: right;
    }

    .resumo {
        margin-bottom: 10px;
        font-size: 10px;
    }

    .campo-em-branco {
        display: block;
        min-height: 14px;
    }

    .assinatura-recebimento {
        margin-top: 18px;
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }

    .assinatura-recebimento td {
        padding: 10px 8px 0 0;
        vertical-align: bottom;
    }

    .linha-assinatura {
        border-top: 1px solid #111827;
        height: 26px;
    }

    .label-assinatura {
        display: block;
        margin-top: 4px;
        font-size: 9px;
        color: #4b5563;
    }
@endsection

@section('content')
    @foreach($romaneio->pedidos as $pedido)
        <div class="{{ $loop->first ? '' : 'page-break' }}">
            <div class="section-title">Pedido #{{ $pedido->id }} - {{ $pedido->escola?->nome ?? 'Escola nao informada' }}</div>

            <div class="resumo">
                <strong>Status:</strong> {{ $pedido->status?->label() ?? 'N/A' }}
                <br>
                <strong>Observacao da Escola:</strong> {{ $pedido->observacao_escola ?: '-' }}
                <br>
                <strong>Observacao do Gestor:</strong> {{ $pedido->observacao_gestor ?: '-' }}
            </div>

            <table class="listagem">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Unidade</th>
                        <th class="right">Qtd. Solicitada</th>
                        <th class="right">Qtd. Aprovada</th>
                        <th class="right">Qtd. Recebida</th>
                        <th>Obs. Solicitacao</th>
                        <th>Obs. Aprovacao</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pedido->itens as $item)
                        <tr>
                            <td>{{ $item->item?->nome ?? 'Item' }}</td>
                            <td>{{ strtoupper($item->item?->unidade_medida?->value ?? 'N/A') }}</td>
                            <td class="right">{{ number_format((float) $item->quantidade_solicitada, 3, ',', '.') }}</td>
                            <td class="right">{{ number_format((float) ($item->quantidade_aprovada ?? 0), 3, ',', '.') }}</td>
                            <td><span class="campo-em-branco">&nbsp;</span></td>
                            <td>{{ $item->observacao_solicitacao ?: '-' }}</td>
                            <td>{{ $item->observacao_aprovacao ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="assinatura-recebimento">
                <tr>
                    <td style="width: 45%;">
                        <div class="linha-assinatura"></div>
                        <span class="label-assinatura">Assinatura de recebimento</span>
                    </td>
                    <td style="width: 35%;">
                        <div class="linha-assinatura"></div>
                        <span class="label-assinatura">Nome do responsavel</span>
                    </td>
                    <td style="width: 20%;">
                        <div class="linha-assinatura"></div>
                        <span class="label-assinatura">Data</span>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach

    <div class="page-break">
        <div class="section-title">Total Consolidado do Romaneio</div>
        <table class="listagem">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Unidade</th>
                    <th class="right">Qtd. Total Aprovada</th>
                </tr>
            </thead>
            <tbody>
                @forelse($totais as $total)
                    <tr>
                        <td>{{ $total['item_nome'] }}</td>
                        <td>{{ $total['unidade'] }}</td>
                        <td class="right">{{ number_format($total['quantidade_total'], 3, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">Nenhum item aprovado neste romaneio.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
