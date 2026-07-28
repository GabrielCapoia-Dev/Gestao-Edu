@extends('relatorios.layouts.base-pdf')

@section('styles')
    .pdf-logo-left {
        height: 52px;
        max-width: 220px;
    }

    .pdf-logo-right-image {
        height: 56px;
        max-width: 230px;
    }

    .pdf-header-table {
        margin-bottom: 3px;
    }

    .pdf-header-text {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .calendar-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .calendar-table > tbody > tr > td {
        vertical-align: top;
        padding: 3px;
    }

    .calendar-block {
        border: 1px solid #64748b;
        page-break-inside: avoid;
    }

    .calendar-block-title {
        padding: 3px 4px;
        background: #1747a6;
        color: #ffffff;
        font-size: {{ $visualizacao === 'ano' ? '8px' : '12px' }};
        font-weight: bold;
        text-align: center;
    }

    .calendar-month {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .calendar-month th {
        padding: 2px 1px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #475569;
        font-size: {{ $visualizacao === 'ano' ? '5px' : '8px' }};
        text-align: center;
    }

    .calendar-month td {
        position: relative;
        height: {{ $visualizacao === 'ano' ? '13px' : ($visualizacao === 'semana' ? '90px' : '52px') }};
        padding: 2px;
        border: 1px solid #d7dee8;
        color: #172033;
        font-size: {{ $visualizacao === 'ano' ? '5px' : '8px' }};
        text-align: center;
        vertical-align: top;
    }

    .calendar-day-number {
        font-weight: bold;
    }

    .calendar-event-count {
        display: block;
        margin-top: 1px;
        color: #475569;
        font-size: {{ $visualizacao === 'ano' ? '4px' : '7px' }};
    }

    .calendar-markers {
        margin-top: 2px;
        line-height: 4px;
    }

    .calendar-marker {
        display: inline-block;
        width: {{ $visualizacao === 'ano' ? '4px' : '7px' }};
        height: {{ $visualizacao === 'ano' ? '2px' : '4px' }};
        margin: 0 1px;
    }

    .calendar-summary {
        padding: {{ $visualizacao === 'ano' ? '3px' : '6px' }};
        background: #f8fafc;
    }

    .calendar-summary-item {
        margin: 0 0 1px;
        color: #334155;
        font-size: {{ $visualizacao === 'ano' ? '5.5px' : '9px' }};
        line-height: 1.3;
    }

    .calendar-summary-dot {
        display: inline-block;
        width: {{ $visualizacao === 'ano' ? '5px' : '7px' }};
        height: {{ $visualizacao === 'ano' ? '5px' : '7px' }};
        margin-right: 3px;
    }

    .calendar-empty {
        background: #f8fafc;
    }
@endsection

@section('content')
    @if ($visualizacao === 'ano')
        <table class="calendar-table">
            <tbody>
                @foreach (array_chunk($calendarios, 3) as $row)
                    <tr>
                        @foreach ($row as $calendario)
                            <td>
                                @include('relatorios.Calendario.partials.mes', ['calendario' => $calendario])
                            </td>
                        @endforeach

                        @for ($column = count($row); $column < 3; $column++)
                            <td></td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        @foreach ($calendarios as $calendario)
            @include('relatorios.Calendario.partials.mes', ['calendario' => $calendario])
        @endforeach
    @endif
@endsection
