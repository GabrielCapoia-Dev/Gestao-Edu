<section
    @class(['home-vehicle-booking', 'is-hidden' => ! $podeAcessar])
    @if ($podeAcessar)
        aria-labelledby="home-vehicle-booking-title"
    @endif
>
    @if ($podeAcessar)
        <header class="home-vehicle-booking__header">
            <div>
                <p class="home-vehicle-booking__eyebrow">FROTA</p>
                <h2 id="home-vehicle-booking-title">Reserva de veículos</h2>
                <p>Consulte a disponibilidade e organize os próximos deslocamentos.</p>
            </div>

            <div class="home-vehicle-booking__actions">
                <a class="gi-action" href="{{ $gerenciarUrl }}">Gerenciar reservas</a>
                {{ $this->novaReservaAction }}
            </div>
        </header>

        <div class="home-vehicle-booking__grid">
            @forelse ($reservas as $reserva)
                <article class="home-vehicle-booking__item" wire:key="reserva-veiculo-{{ $reserva->id }}">
                    <div class="home-vehicle-booking__date">
                        <span>{{ mb_strtoupper($reserva->data_inicio->locale('pt_BR')->translatedFormat('D')) }}</span>
                        <strong>{{ $reserva->data_inicio->format('d') }}</strong>
                        <small>{{ $reserva->data_inicio->locale('pt_BR')->translatedFormat('M') }}</small>
                    </div>

                    <div class="home-vehicle-booking__content">
                        <span>{{ $reserva->data_inicio->format('H:i') }}–{{ $reserva->data_fim->format('H:i') }}</span>
                        <strong>{{ $reserva->veiculo?->identificacao ?: $reserva->veiculo?->placa }}</strong>
                        <p>{{ $reserva->atividade }}</p>
                        <small>{{ $reserva->local_nome }} · {{ $reserva->usuario?->name }}</small>
                    </div>
                </article>
            @empty
                <div class="gi-empty home-vehicle-booking__empty">
                    <x-heroicon-o-truck />
                    <div>
                        <strong>Nenhuma reserva futura</strong>
                        <span>Os veículos disponíveis podem ser reservados pelo botão acima.</span>
                    </div>
                </div>
            @endforelse
        </div>

        <x-filament-actions::modals />
    @endif
</section>
