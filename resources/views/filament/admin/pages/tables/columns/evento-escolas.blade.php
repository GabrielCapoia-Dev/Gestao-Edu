@php
    $evento = $getRecord();
    $escolas = $evento->enviar_todas_escolas
        ? $evento->publicoAlvo?->escolas->map(fn ($escola) => [
            'nome' => $escola->nome,
            'alunos' => null,
        ])->values() ?? collect()
        : $evento->escolasResumo->map(fn ($agendamento) => [
            'nome' => $agendamento->escola?->nome ?? 'Escola não informada',
            'alunos' => $agendamento->precisa_transporte
                ? (int) $agendamento->quantidade_estimada_transporte
                : null,
        ])->values();
    $total = $escolas->count();
@endphp

<div
    class="gi-school-popover"
    x-data="{
        open: false,
        top: 0,
        left: 0,
        toggle() {
            this.open = ! this.open;
            if (! this.open) return;
            const rect = this.$refs.trigger.getBoundingClientRect();
            this.top = Math.max(12, Math.min(rect.bottom + 8, window.innerHeight - 320));
            this.left = Math.max(12, Math.min(rect.left, window.innerWidth - 340));
        },
        isInsidePanel(event) {
            return this.$refs.panel?.contains(event.target) ?? false;
        },
        closeOnOutsideClick(event) {
            if (! this.open || this.isInsidePanel(event) || this.$refs.trigger.contains(event.target)) return;

            this.open = false;
        },
        closeOnOutsideScroll(event) {
            if (! this.open || this.isInsidePanel(event)) return;

            this.open = false;
        },
    }"
    @keydown.escape.window="open = false"
    @resize.window="open = false"
    @click.window="closeOnOutsideClick($event)"
    @wheel.window.passive="closeOnOutsideScroll($event)"
>
    <button
        type="button"
        class="gi-school-popover__trigger"
        x-ref="trigger"
        @click="toggle()"
        :aria-expanded="open.toString()"
        aria-label="Exibir {{ $total }} escola(s) deste evento"
    >
        @forelse ($escolas->take(2) as $escola)
            <span class="gi-school-badge">{{ $escola['nome'] }}</span>
        @empty
            <span class="gi-school-badge gi-school-badge--muted">Todas as escolas do escopo</span>
        @endforelse

        @if ($total > 2)
            <span class="gi-school-badge gi-school-badge--count">+{{ $total - 2 }}</span>
        @endif
    </button>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-transition.opacity.duration.150ms
            x-ref="panel"
            class="gi-school-popover__panel"
            :style="`top: ${top}px; left: ${left}px`"
            role="tooltip"
        >
            <div class="gi-school-popover__heading">
                <strong>Escolas participantes</strong>
                <span>{{ $total ?: 'Todas' }}</span>
            </div>
            <div class="gi-school-popover__list">
                @forelse ($escolas as $escola)
                    <div class="gi-school-popover__item">
                        <span>{{ $escola['nome'] }}</span>
                        @if ($escola['alunos'] !== null)
                            <small>{{ number_format($escola['alunos'], 0, ',', '.') }} aluno(s)</small>
                        @endif
                    </div>
                @empty
                    <p>Todas as escolas autorizadas no escopo do evento.</p>
                @endforelse
            </div>
        </div>
    </template>
</div>
