@php
    $grupos = $aviso->leituras
        ->groupBy('versao_envio')
        ->sortKeysDesc();
@endphp

<div class="ge-aviso-leituras">
    <p class="ge-aviso-leituras__resumo">
        Envio atual: <strong>#{{ $aviso->versao_envio }}</strong>.
        Cada novo envio mantém as confirmações anteriores para auditoria.
    </p>

    @forelse ($grupos as $versao => $leituras)
        <section class="ge-aviso-leituras__grupo" aria-labelledby="leituras-envio-{{ $versao }}">
            <header>
                <h3 id="leituras-envio-{{ $versao }}">Envio #{{ $versao }}</h3>
                <span>{{ $leituras->count() }} {{ $leituras->count() === 1 ? 'leitura' : 'leituras' }}</span>
            </header>

            <ul>
                @foreach ($leituras as $leitura)
                    <li>
                        <div>
                            <strong>{{ $leitura->usuario?->name ?? 'Usuário removido' }}</strong>
                            @if ($leitura->usuario?->email)
                                <span>{{ $leitura->usuario->email }}</span>
                            @endif
                        </div>
                        <time datetime="{{ $leitura->lido_em?->toIso8601String() }}">
                            {{ $leitura->lido_em?->format('d/m/Y H:i') }}
                        </time>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="gi-empty ge-aviso-leituras__vazio">
            <x-heroicon-o-user-group />
            <strong>Nenhuma leitura registrada</strong>
        </div>
    @endforelse
</div>
