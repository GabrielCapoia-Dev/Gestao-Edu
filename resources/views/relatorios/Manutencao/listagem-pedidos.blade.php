@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Listagem de Pedidos de Manutenção')
@section('reportSubtitle', $reportSubtitle ?? 'Registros detalhados')

@section('styles')
    .listing-table { width: 100%; border-collapse: collapse; font-size: 8px; }
    .listing-table th { background: #1e3a8a; color: white; padding: 5px 3px; text-align: left; }
    .listing-table td { border: 1px solid #d1d5db; padding: 4px 3px; vertical-align: top; }
    .listing-table tbody tr:nth-child(odd) { background: #f9fafb; }
    .badge { display: inline-block; padding: 2px 4px; color: white; border-radius: 3px; font-size: 7px; }
    .text-gray { color: #6b7280; }
    .text-ok { color: #047857; }
@endsection

@section('content')
    <div class="section-title">Pedidos detalhados</div>

    @if(empty($pedidos))
        <p style="text-align:center;color:#6b7280;">Nenhum pedido encontrado para os filtros selecionados.</p>
    @else
        <table class="listing-table">
            <thead>
                <tr>
                    <th>Protocolo</th><th>Escola</th><th>Tipo</th><th>Status</th><th>Registro</th>
                    <th>Identificado</th><th>Solicitado</th><th>Concluído</th><th>Problemas</th><th>Empresa</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pedidos as $pedido)
                    @php $statusCor = '#' . ltrim($pedido['status_cor'] ?? '6b7280', '#'); @endphp
                    <tr>
                        <td><strong>{{ $pedido['numero_protocolo'] ?? '-' }}</strong>
                            @if($pedido['pedido_principal_protocolo'] ?? null)<br><span class="text-gray">Origem: {{ $pedido['pedido_principal_protocolo'] }}</span>@endif
                            @if(($pedido['adicionais_count'] ?? 0) > 0)<br><span class="text-gray">{{ $pedido['adicionais_count'] }} adicional(is)</span>@endif
                        </td>
                        <td>{{ $pedido['escola_nome'] ?? '-' }}</td>
                        <td>{{ $pedido['tipo_manutencao_nome'] ?? '-' }}</td>
                        <td><span class="badge" style="background:{{ $statusCor }}">{{ $pedido['status_nome'] ?? '-' }}</span></td>
                        <td>{{ !empty($pedido['is_pedido_adicional']) ? 'Adicional' : 'Principal' }}</td>
                        <td>{{ !empty($pedido['data_identificacao_problema']) ? \Carbon\Carbon::parse($pedido['data_identificacao_problema'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ !empty($pedido['data_solicitacao']) ? \Carbon\Carbon::parse($pedido['data_solicitacao'])->format('d/m/Y') : '-' }}</td>
                        <td class="{{ !empty($pedido['data_entrega']) ? 'text-ok' : 'text-gray' }}">{{ !empty($pedido['data_entrega']) ? \Carbon\Carbon::parse($pedido['data_entrega'])->format('d/m/Y') : '-' }}</td>
                        <td>{{ $pedido['problemas'] ?? '-' }} @if($pedido['resultados_feedback'])<br><span class="text-gray">{{ $pedido['resultados_feedback'] }}</span>@endif</td>
                        <td>{{ $pedido['empresa_nome'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="text-align:right;font-size:9px;color:#6b7280;">Total no filtro: {{ $totalPedidos }} pedido(s)</p>
    @endif
@endsection
