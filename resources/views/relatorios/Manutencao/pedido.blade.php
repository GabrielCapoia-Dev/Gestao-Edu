@php
    $escola = $pedido->escola;

    $endereco = $escola
        ? "{$escola->logradouro}, {$escola->numero} - {$escola->bairro}, {$escola->cidade}/{$escola->estado} - CEP: {$escola->cep}"
        : 'Nao Informado';

    $tipo = match($pedido->nivel_prioridade?->value) {
        'indeterminado' => 'Indeterminado',
        default => $pedido->nivel_prioridade?->value ?? 'Nao Informado',
    };

    $prioridadeCor = match($pedido->nivel_prioridade?->value) {
        'Emergencial' => '#ef4444',
        'Corretivo' => '#f97316',
        'Preventivo' => '#3b82f6',
        default => '#6b7280',
    };

    $feedback = $pedido->ultimoFeedback;
    $statusCor = '#' . ltrim($pedido->tipoStatus?->cor ?? '#9ca3af', '#');
@endphp
@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatorio Tecnico de Manutencao')
@section('reportSubtitle', $reportSubtitle ?? ('Protocolo ' . ($pedido->numero_protocolo ?? 'N/A')))

@section('styles')
    .espaco-line {
        padding: 2px 4px;
    }

    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
        color: #fff;
        line-height: 1.4;
        vertical-align: middle;
    }

    .descricao {
        border-left: 3px solid #d1d5db;
        padding: 8px 0 8px 12px;
        margin-top: 4px;
        text-align: justify;
        text-justify: inter-word;
        line-height: 1.6;
        font-size: 12px;
    }

    .section-block {
        page-break-inside: avoid;
        margin-top: 10px;
    }

    .section-title-inline {
        page-break-after: avoid;
        page-break-inside: avoid;
        margin-bottom: 6px;
        font-weight: bold;
    }

    .photo-table {
        width: 100%;
        border-collapse: collapse;
        page-break-inside: auto;
    }

    .photo-table tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    .photo-table td {
        page-break-inside: avoid;
        padding: 4px;
        text-align: center;
        vertical-align: middle;
    }

    .photo-table img {
        max-width: 100%;
        max-height: 150px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        page-break-inside: avoid;
    }
@endsection

@section('content')
    <table style="width: 100%;">
        <tr>
            <td style="width: 33.33%;" class="espaco-line">
                <strong>Protocolo:</strong><br>
                {{ $pedido->numero_protocolo ?? 'Nao Informado' }}
            </td>
            <td style="width: 33.33%;" class="espaco-line">
                <strong>Data:</strong><br>
                {{ $pedido->data_solicitacao?->format('d/m/Y') ?? 'Nao Informado' }}
            </td>
            <td style="width: 33.33%;" class="espaco-line">
                <strong>Status:</strong><br>
                <span class="badge" style="background: {{ $statusCor }};">
                    {{ $pedido->tipoStatus?->nome ?? 'Nao Informado' }}
                </span>
            </td>
        </tr>

        <tr>
            <td class="espaco-line">
                <strong>Escola:</strong><br>
                {{ $escola?->nome ?? 'Nao Informado' }}
            </td>
            <td class="espaco-line">
                <strong>Solicitante:</strong><br>
                {{ $pedido->nome_solicitante ?? 'Nao Informado' }}
            </td>
            <td class="espaco-line">
                <strong>Telefone:</strong><br>
                {{ $escola?->telefone ?? 'Nao Informado' }}
            </td>
        </tr>

        <tr>
            <td class="espaco-line">
                <strong>E-mail:</strong><br>
                {{ $pedido->solicitante?->email ?? 'Nao Informado' }}
            </td>
            <td colspan="2" class="espaco-line">
                <strong>Endereco:</strong><br>
                {{ $endereco }}
            </td>
        </tr>

        <tr>
            <td class="espaco-line">
                <strong>Tipo:</strong><br>
                {{ $pedido->tipoManutencao?->nome ?? 'Nao Informado' }}
            </td>
            <td class="espaco-line"></td>
            <td class="espaco-line">
                <strong>Prioridade:</strong><br>
                <span class="badge" style="background: {{ $prioridadeCor }};">
                    {{ $tipo }}
                </span>
            </td>
        </tr>

        <tr>
            <td class="espaco-line" colspan="3">
                <strong>Descricao:</strong>
                <div class="descricao">
                    {{ $pedido->descricao_pedido ?? 'Nao Informado' }}
                </div>
            </td>
        </tr>

        @if($feedback)
            <tr>
                <td class="espaco-line" colspan="3">
                    <strong>Avaliacao do Servico:</strong>

                    <div class="descricao">
                        <div style="margin-bottom: 6px;">
                            <strong>Nota:</strong> {{ $feedback->valor }}/5
                        </div>

                        @if($feedback->descricao)
                            <div>
                                <strong>Comentario:</strong><br>
                                {{ $feedback->descricao }}
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        @endif
    </table>

    @php
        $fotosProblema = $pedido->arquivos()
            ->where('tipo_arquivo', 'fotos_problema')
            ->get();

        $fotosConclusao = $pedido->arquivos()
            ->where('tipo_arquivo', 'fotos_conclusao')
            ->get();

        $temFotos = $fotosProblema->isNotEmpty() || $fotosConclusao->isNotEmpty();
    @endphp

    @if($temFotos)
        <div class="divider"></div>

        @if($fotosProblema->isNotEmpty())
            <div class="section-block espaco-line">
                <div class="section-title-inline">Fotos do Problema</div>

                <table class="photo-table">
                    @foreach($fotosProblema->chunk(3) as $grupo)
                        <tr>
                            @foreach($grupo as $arquivo)
                                <td>
                                    <img src="{{ public_path('storage/' . $arquivo->caminho) }}" alt="Foto do problema">
                                </td>
                            @endforeach

                            @for($i = $grupo->count(); $i < 3; $i++)
                                <td></td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        @if($fotosConclusao->isNotEmpty())
            <div class="section-block espaco-line">
                <div class="section-title-inline">Fotos da Conclusao</div>

                <table class="photo-table">
                    @foreach($fotosConclusao->chunk(3) as $grupo)
                        <tr>
                            @foreach($grupo as $arquivo)
                                <td>
                                    <img src="{{ public_path('storage/' . $arquivo->caminho) }}" alt="Foto da conclusao">
                                </td>
                            @endforeach

                            @for($i = $grupo->count(); $i < 3; $i++)
                                <td></td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    @endif
@endsection
