@php
    $resolvedReportTitle = $reportTitle ?? trim((string) $__env->yieldContent('reportTitle'));
    $resolvedReportSubtitle = $reportSubtitle ?? trim((string) $__env->yieldContent('reportSubtitle'));
    $resolvedReportTitle = $resolvedReportTitle !== '' ? $resolvedReportTitle : 'Relatório';
    $resolvedReportSubtitle = $resolvedReportSubtitle !== '' ? $resolvedReportSubtitle : null;
    $resolvedFilters = $reportFilters ?? $filtros ?? [];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $resolvedReportTitle }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }

        @page {
            size: {{ $paperSize ?? 'a4' }} {{ $orientation ?? 'portrait' }};
            margin: 145px 25px 80px 25px;
        }

        .pdf-header {
            position: fixed;
            top: -138px;
            left: 0;
            right: 0;
            height: 118px;
            background: white;
            z-index: 1000;
        }

        .pdf-header-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }

        .pdf-header-table td {
            vertical-align: middle;
        }

        .pdf-logo-left {
            width: auto;
            height: 38px;
            max-width: 170px;
        }

        .pdf-logo-right {
            text-align: right;
        }

        .pdf-logo-right-image {
            width: auto;
            height: 42px;
            max-width: 190px;
        }

        .pdf-header-text {
            text-align: center;
            margin-top: 4px;
            margin-bottom: 14px;
        }

        .pdf-header-text h1 {
            margin: 0;
            font-size: 16px;
        }

        .pdf-header-text h2 {
            margin: 2px 0 0 0;
            font-size: 13px;
            font-weight: normal;
        }

        .pdf-footer {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            height: 60px;
            font-size: 9px;
            color: #6b7280;
            background: white;
            z-index: 1000;
        }

        .pdf-footer-divider {
            border-top: 1px solid #d1d5db;
            margin-bottom: 6px;
        }

        .pdf-footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pdf-footer-table td {
            vertical-align: top;
        }

        .pdf-footer-left {
            width: 40%;
            text-align: left;
        }

        .pdf-footer-center {
            width: 20%;
            text-align: center;
        }

        .pdf-footer-right {
            width: 40%;
            text-align: right;
        }

        .pdf-content {
            page-break-inside: auto;
        }

        .divider {
            border-top: 1px solid #d1d5db;
            margin: 12px 0;
        }

        .pdf-header-divider {
            border-top: 1px solid #d1d5db;
            margin-top: 8px;
        }

        .report-filters {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .report-filters-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .report-filters-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-filters-table td {
            width: 50%;
            padding: 2px 10px 2px 0;
            font-size: 10px;
            vertical-align: top;
        }

        .report-filters-label {
            font-weight: bold;
            color: #111827;
        }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            color: #111827;
            margin: 14px 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid #e5e7eb;
            page-break-after: avoid;
        }

        .page-break {
            page-break-before: always;
        }

        @yield('styles')
    </style>
</head>
<body>
    @include('relatorios.layouts.partials.header', [
        'reportTitle' => $resolvedReportTitle,
        'reportSubtitle' => $resolvedReportSubtitle,
    ])

    @include('relatorios.layouts.partials.footer', [
        'usuarioExportacao' => $usuarioExportacao ?? null,
        'dataExportacao' => $dataExportacao ?? now(),
    ])

    <div class="pdf-content">
        @if(! empty($resolvedFilters))
            <div class="report-filters">
                <div class="report-filters-title">Filtros aplicados</div>

                <table class="report-filters-table">
                    @foreach(array_chunk($resolvedFilters, 2, true) as $row)
                        <tr>
                            @foreach($row as $label => $value)
                                <td>
                                    <span class="report-filters-label">{{ ucfirst((string) $label) }}:</span>
                                    {{ $value }}
                                </td>
                            @endforeach

                            @if(count($row) === 1)
                                <td></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
