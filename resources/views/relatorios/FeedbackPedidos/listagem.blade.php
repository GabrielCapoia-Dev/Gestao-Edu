@php
    $cores = [5 => '#10b981', 4 => '#3b82f6', 3 => '#f59e0b', 2 => '#ef4444', 1 => '#dc2626'];
@endphp
@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Listagem de Feedbacks de Pedidos')
@section('reportSubtitle', $reportSubtitle ?? 'Avaliações detalhadas')

@section('styles')
    .feedback-table { width:100%; border-collapse:collapse; font-size:8px; }
    .feedback-table th { background:#1e3a8a; color:white; padding:5px 3px; text-align:left; }
    .feedback-table td { border:1px solid #d1d5db; padding:4px 3px; vertical-align:top; }
    .feedback-table tbody tr:nth-child(odd) { background:#f9fafb; }
    .badge { display:inline-block; color:white; border-radius:3px; padding:2px 4px; font-size:8px; }
@endsection

@section('content')
    <div class="section-title">Avaliações detalhadas</div>
    @if(empty($feedbacks))
        <p style="text-align:center;color:#6b7280;">Nenhuma avaliação registrada para os filtros selecionados.</p>
    @else
        <table class="feedback-table">
            <thead><tr><th>Protocolo</th><th>Escola</th><th>Nota</th><th>Reaberto</th><th>Por problema</th><th>Descrição</th><th>Data</th><th>Tipo</th></tr></thead>
            <tbody>
                @foreach($feedbacks as $feedback)
                    <tr>
                        <td><strong>{{ $feedback['protocolo'] ?? '-' }}</strong></td>
                        <td>{{ $feedback['escola'] ?? '-' }}</td>
                        <td><span class="badge" style="background:{{ $cores[$feedback['nota']] ?? '#6b7280' }}">{{ $feedback['nota'] }}/5</span></td>
                        <td>{{ !empty($feedback['reaberto']) ? 'Sim' : 'Não' }}</td>
                        <td>@forelse($feedback['itens'] as $item){{ $item['problema'] }}: {{ $item['valor'] }}/5 - {{ $item['resultado'] }}<br>@empty-@endforelse</td>
                        <td>{{ $feedback['descricao'] ?? '-' }}</td>
                        <td>{{ $feedback['data'] ?? '-' }}</td>
                        <td>{{ $feedback['tipo'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="text-align:right;font-size:9px;color:#6b7280;">Total no filtro: {{ $totalFeedbacks }} avaliação(ões)</p>
    @endif
@endsection
