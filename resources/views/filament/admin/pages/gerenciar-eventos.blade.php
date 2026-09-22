<x-filament-panels::page>
    <div class="gi-event-management">
        @if ($this->podeVisualizarIndicadores())
            @php($indicadores = $this->getIndicadores())

            <section class="gi-event-metrics" aria-label="Resumo dos eventos de transporte">
                <article class="gi-event-metric gi-event-metric--pending">
                    <div class="gi-event-metric__icon" aria-hidden="true">
                        <x-heroicon-o-clock />
                    </div>
                    <div>
                        <span>Solicitações em aberto</span>
                        <strong>{{ number_format($indicadores['pendentes'], 0, ',', '.') }}</strong>
                        <small>Aguardando análise e publicação</small>
                    </div>
                </article>

                <article class="gi-event-metric gi-event-metric--published">
                    <div class="gi-event-metric__icon" aria-hidden="true">
                        <x-heroicon-o-check-circle />
                    </div>
                    <div>
                        <span>Eventos aprovados</span>
                        <strong>{{ number_format($indicadores['publicados'], 0, ',', '.') }}</strong>
                        <small>Publicados na agenda</small>
                    </div>
                </article>

                <article class="gi-event-metric gi-event-metric--rejected">
                    <div class="gi-event-metric__icon" aria-hidden="true">
                        <x-heroicon-o-x-circle />
                    </div>
                    <div>
                        <span>Eventos rejeitados</span>
                        <strong>{{ number_format($indicadores['rejeitados'], 0, ',', '.') }}</strong>
                        <small>Solicitações mantidas no histórico</small>
                    </div>
                </article>

                <article class="gi-event-metric gi-event-metric--total">
                    <div class="gi-event-metric__icon" aria-hidden="true">
                        <x-heroicon-o-truck />
                    </div>
                    <div>
                        <span>Total com transporte</span>
                        <strong>{{ number_format($indicadores['total_transporte'], 0, ',', '.') }}</strong>
                        <small>Eventos nos filtros atuais</small>
                    </div>
                </article>
            </section>
        @endif

        <section class="gi-event-table-panel" aria-labelledby="gi-event-table-title">
            <header class="gi-event-table-panel__header">
                <div>
                    <p class="gi-eyebrow">Registros</p>
                    <h2 id="gi-event-table-title">Eventos cadastrados</h2>
                    <p>Use os filtros para localizar eventos e acompanhar o fluxo de publicação.</p>
                </div>
            </header>

            {{ $this->table }}
        </section>
    </div>

    <livewire:home.evento-calendario-modal :mostrar-gatilho="false" />
</x-filament-panels::page>
