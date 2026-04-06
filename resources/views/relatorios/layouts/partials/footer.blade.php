@php
    $dataExportada = \Illuminate\Support\Carbon::parse($dataExportacao ?? now());
@endphp
<div class="pdf-footer">
    <div class="pdf-footer-divider"></div>

    <table class="pdf-footer-table">
        <tr>
            <td class="pdf-footer-left">
                Documento exportado do Sistema de Gestao Educacional<br>
                Secretaria Municipal de Educacao - Umuarama
            </td>
            <td class="pdf-footer-center">&nbsp;</td>
            <td class="pdf-footer-right">
                Exportado por: <strong>{{ $usuarioExportacao->name ?? 'Sistema' }}</strong><br>
                Em: {{ $dataExportada->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>
</div>
