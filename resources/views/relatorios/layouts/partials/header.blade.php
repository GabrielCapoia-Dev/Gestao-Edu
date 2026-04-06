<div class="pdf-header">
    <table class="pdf-header-table">
        <tr>
            <td style="width: 50%;">
                <img src="{{ public_path('images/logo-educacao.png') }}" class="pdf-logo-left" alt="Logo Educacao">
            </td>
            <td style="width: 50%;" class="pdf-logo-right">
                <img src="{{ public_path('images/logo-abrinq-preto.png') }}" style="width: 190px;" alt="Logo Abrinq">
            </td>
        </tr>
    </table>

    <div class="pdf-header-text">
        <h1>Gestao Educacional</h1>
        <h2>{{ $reportTitle }}</h2>

        @if(! empty($reportSubtitle))
            <div style="margin-top: 4px; font-size: 10px; color: #6b7280;">
                {{ $reportSubtitle }}
            </div>
        @endif
    </div>

    <div class="divider"></div>
</div>
