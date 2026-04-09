@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Empenho de Pedido de Merenda')
@section('reportSubtitle', $reportSubtitle ?? ('Pedido #' . ($pedido->id ?? 'N/A')))

@section('styles')
    .cards {
        width: 100%;
        margin-bottom: 16px;
        border-collapse: separate;
        border-spacing: 6px;
    }

    .cards td {
        width: 25%;
        border: 1px solid #d1d5db;
        background: #f8fafc;
        padding: 10px;
        vertical-align: top;
    }

    .cards .label {
        font-size: 9px;
        text-transform: uppercase;
        color: #64748b;
    }

    .cards .value {
        font-size: 17px;
        font-weight: bold;
        margin-top: 4px;
    }

    .section-title {
        font-size: 13px;
        font-weight: bold;
        margin: 16px 0 8px;
        color: #0f172a;
    }

    .company-block {
        page-break-inside: avoid;
    }

    .info-table,
    .listagem {
        width: 100%;
        border-collapse: collapse;
        font-size: 9px;
    }

    .info-table th,
    .info-table td,
    .listagem th,
    .listagem td {
        border: 1px solid #d1d5db;
        padding: 6px;
        text-align: left;
        vertical-align: top;
    }

    .info-table th,
    .listagem th {
        background: #f1f5f9;
        font-size: 10px;
    }

    .listagem thead {
        display: table-header-group;
    }

    .right {
        text-align: right;
    }

    .note-box {
        border: 1px solid #dbe4ee;
        background: #f8fafc;
        padding: 10px;
        margin-bottom: 12px;
        border-radius: 6px;
        line-height: 1.55;
    }

    .page-break {
        page-break-before: always;
    }
@endsection

@section('content')
    <table class="cards">
        <tr>
            <td>
                <div class="label">Pedido</div>
                <div class="value">#{{ $pedido->id }}</div>
            </td>
            <td>
                <div class="label">Status</div>
                <div class="value" style="font-size: 14px;">{{ $pedido->status?->label() ?? '-' }}</div>
            </td>
            <td>
                <div class="label">Empresas</div>
                <div class="value">{{ $gruposEmpresa->count() }}</div>
            </td>
            <td>
                <div class="label">Qtd. Pedida</div>
                <div class="value">{{ number_format((float) $pedido->itens->sum('quantidade_pedida'), 3, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    @if(filled($pedido->observacoes))
        <div class="note-box">
            <strong>Observacoes do pedido:</strong><br>
            {{ $pedido->observacoes }}
        </div>
    @endif

    @foreach($gruposEmpresa as $index => $grupo)
        @php
            $empresa = $grupo['empresa'];
            $endereco = trim(collect([
                $empresa?->logradouro,
                $empresa?->numero,
                $empresa?->complemento,
                $empresa?->bairro,
                $empresa?->cidade ? ($empresa->cidade . '/' . $empresa->estado) : null,
                $empresa?->cep ? ('CEP: ' . $empresa->cep) : null,
            ])->filter()->implode(', '));
        @endphp

        <div class="{{ $index > 0 ? 'page-break ' : '' }}company-block">
            <div class="section-title">Encaminhamento para Empresa {{ $index + 1 }}</div>

            <div class="note-box">
                Este empenho informa ao setor responsavel que os itens abaixo foram solicitados e devem ser encaminhados para a empresa destacada nesta secao.
            </div>

            <table class="info-table">
                <tbody>
                    <tr>
                        <th style="width: 22%;">Empresa</th>
                        <td>{{ $empresa?->nome ?: 'Empresa nao informada' }}</td>
                        <th style="width: 18%;">CNPJ</th>
                        <td>{{ $empresa?->cnpj ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Responsavel</th>
                        <td>{{ $empresa?->responsavel ?: '-' }}</td>
                        <th>Telefone</th>
                        <td>{{ $empresa?->telefone ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{ $empresa?->email ?: '-' }}</td>
                        <th>Endereco</th>
                        <td>{{ $endereco !== '' ? $endereco : '-' }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="section-title">Contratos relacionados</div>
            <table class="listagem">
                <thead>
                    <tr>
                        <th>Contrato</th>
                        <th>Inicio</th>
                        <th>Vencimento</th>
                        <th>Observacoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($grupo['contratos'] as $contrato)
                        <tr>
                            <td>{{ $contrato->numero_contrato ?: '-' }}</td>
                            <td>{{ $contrato->data_inicio?->format('d/m/Y') ?: '-' }}</td>
                            <td>{{ $contrato->data_vencimento?->format('d/m/Y') ?: '-' }}</td>
                            <td>{{ $contrato->observacoes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Nenhum contrato relacionado encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="section-title">Itens a encaminhar</div>
            <table class="listagem">
                <thead>
                    <tr>
                        <th>Contrato</th>
                        <th>Item</th>
                        <th>Unidade</th>
                        <th class="right">Qtd. Pedida</th>
                        <th class="right">Qtd. Entregue</th>
                        <th class="right">Qtd. Pendente</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grupo['itens'] as $pedidoItem)
                        <tr>
                            <td>{{ $pedidoItem->contratoItem?->contrato?->numero_contrato ?: '-' }}</td>
                            <td>{{ $pedidoItem->contratoItem?->item?->nome ?: 'Item nao encontrado' }}</td>
                            <td>{{ $pedidoItem->contratoItem?->item?->unidade_medida?->value ?: '-' }}</td>
                            <td class="right">{{ number_format((float) $pedidoItem->quantidade_pedida, 3, ',', '.') }}</td>
                            <td class="right">{{ number_format((float) $pedidoItem->quantidade_entregue, 3, ',', '.') }}</td>
                            <td class="right">{{ number_format((float) $pedidoItem->quantidade_pendente, 3, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th colspan="3" class="right">Totais da empresa</th>
                        <th class="right">{{ number_format((float) $grupo['quantidade_pedida'], 3, ',', '.') }}</th>
                        <th class="right">{{ number_format((float) $grupo['quantidade_entregue'], 3, ',', '.') }}</th>
                        <th class="right">{{ number_format((float) $grupo['quantidade_pendente'], 3, ',', '.') }}</th>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
@endsection
