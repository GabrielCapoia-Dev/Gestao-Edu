@php
    $cores = [
        5 => '#10b981',
        4 => '#3b82f6',
        3 => '#f59e0b',
        2 => '#ef4444',
        1 => '#dc2626',
    ];
@endphp
@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatório de Avaliação de Empresas Terceirizadas')
@section('reportSubtitle', $reportSubtitle ?? 'Consolidado de satisfação por empresa terceirizada')

@section('styles')
    .cards-container {
        width: 100%;
        margin-bottom: 8px;
        border-collapse: collapse;
    }

    .cards-container td {
        padding: 6px 8px;
        border: 1px solid #e5e7eb;
        background: #f9fafb;
        font-size: 10px;
        vertical-align: top;
    }

    .card-label {
        font-size: 9px;
        color: #6b7280;
        margin-bottom: 2px;
    }

    .card-value {
        font-size: 11px;
        font-weight: bold;
        color: #1f2937;
    }

    .matriz-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        font-size: 9px;
        page-break-inside: avoid;
    }

    .matriz-table thead {
        background: #374151;
        color: white;
    }

    .matriz-table th {
        border: 1px solid #4b5563;
        padding: 4px 2px;
        text-align: center;
        font-weight: bold;
        color: white;
        font-size: 8px;
        line-height: 1.1;
        word-break: break-word;
    }

    .matriz-table td {
        border: 1px solid #d1d5db;
        padding: 4px 2px;
        text-align: center;
        font-weight: bold;
        font-size: 9px;
    }

    .matriz-table tbody tr {
        page-break-inside: avoid;
    }

    .nota-label {
        text-align: left;
        font-weight: bold;
        padding-left: 4px !important;
        font-size: 8px;
    }

    .nota-1 {
        background: #ffc9c9;
        color: #7f1d1d;
    }

    .nota-2 {
        background: #fed7aa;
        color: #7c2d12;
    }

    .nota-3 {
        background: #fef3c7;
        color: #78350f;
    }

    .nota-4 {
        background: #d1fae5;
        color: #065f46;
    }

    .nota-5 {
        background: #bbf7d0;
        color: #064e3b;
    }

    .matrix-empty {
        color: #9ca3af;
        font-weight: normal;
    }

    .ano-titulo {
        font-weight: bold;
        font-size: 10px;
        margin-top: 15px;
        margin-bottom: 8px;
        color: #374151;
        page-break-inside: avoid;
        padding-left: 10px;
        border-left: 3px solid #374151;
    }

    .page-break {
        page-break-before: always;
    }
@endsection

@section('content')
    @if(! empty($matrizesEmpresa))
        @foreach($matrizesEmpresa as $empresaData)
            @php
                $empresa = $empresaData['empresa'];
            @endphp

            <div class="{{ ! $loop->first ? 'page-break' : '' }}">
                <table class="cards-container">
                    <tr>
                        <td style="width: 70%;">
                            <div class="card-label">Empresa</div>
                            <div class="card-value">{{ $empresa->nome }}</div>
                        </td>

                        <td style="width: 30%; text-align: center; color: #374151;">
                            <div style="font-size: 9px;">Percentual de Satisfação</div>
                            <div style="font-size: 20px; font-weight: bold;">
                                {{ $empresaData['percentual'] }}%
                            </div>
                        </td>
                    </tr>
                </table>

                <table class="cards-container">
                    <tr>
                        <td>
                            <div class="card-label">Avaliações</div>
                            <div class="card-value">{{ $empresaData['total'] ?? 0 }}</div>
                        </td>
                        <td>
                            <div class="card-label">Media</div>
                            <div class="card-value">{{ $empresaData['media'] ?? 0 }}/5</div>
                        </td>
                        <td>
                            <div class="card-label">Reabertos</div>
                            <div class="card-value">{{ $empresaData['reabertos'] ?? 0 }}</div>
                        </td>
                        <td>
                            <div class="card-label">Criticas</div>
                            <div class="card-value">{{ $empresaData['criticas'] ?? 0 }}</div>
                        </td>
                    </tr>
                </table>

                <table class="cards-container">
                    <tr>
                        <td>
                            <div class="card-label">CNPJ</div>
                            <div class="card-value">{{ $empresa->cnpj }}</div>
                        </td>
                        <td>
                            <div class="card-label">Representante</div>
                            <div class="card-value">{{ $empresa->responsavel ?? '-' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="card-label">Telefone</div>
                            <div class="card-value">{{ $empresa->telefone ?? '-' }}</div>
                        </td>
                        <td>
                            <div class="card-label">E-mail</div>
                            <div class="card-value">{{ $empresa->email ?? '-' }}</div>
                        </td>
                        <td>
                            <div class="card-label">Cidade</div>
                            <div class="card-value">{{ $empresa->cidade }}/{{ $empresa->estado }}</div>
                        </td>
                    </tr>
                </table>

                <table class="cards-container">
                    <tr>
                        <td>
                            <div class="card-label">Endereco Completo</div>
                            <div class="card-value">
                                {{ $empresa->logradouro }}, {{ $empresa->numero }}
                                {{ $empresa->complemento ? ' - ' . $empresa->complemento : '' }} -
                                {{ $empresa->bairro }} -
                                CEP: {{ $empresa->cep }}
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="divider"></div>
                <div class="section-title">Nível de Satisfação dos serviços prestados</div>

                @foreach($empresaData['anos'] as $ano => $meses)
                    <div class="ano-titulo">{{ $ano }}</div>

                    <table class="matriz-table">
                        <thead>
                            <tr>
                                <th style="width: 10%; text-align: center;">Avaliação</th>
                                @foreach(array_reverse($meses, true) as $mes => $dados)
                                    <th style="width: {{ 90 / count($meses) }}%;">{{ $mes }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @for($nota = 5; $nota >= 1; $nota--)
                                <tr>
                                    <td class="nota-label nota-{{ $nota }}">Nota {{ $nota }}</td>
                                    @foreach(array_reverse($meses, true) as $mes => $dados)
                                        <td class="nota-{{ $nota }}">
                                            @if($dados[$nota] > 0)
                                                <strong>{{ $dados[$nota] }}</strong>
                                            @else
                                                <span class="matrix-empty">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endfor
                            <tr class="total-row">
                                <td style="font-weight: bold;">Total</td>
                                @foreach($meses as $mes => $dados)
                                    @php $totalMes = array_sum($dados); @endphp
                                    <td style="font-weight: bold;">{{ $totalMes > 0 ? $totalMes : '-' }}</td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                @endforeach
            </div>
        @endforeach
    @else
        <p style="text-align: center; color: #6b7280; padding: 20px 0;">
            Nenhuma avaliação registrada para os filtros selecionados.
        </p>
    @endif
@endsection
