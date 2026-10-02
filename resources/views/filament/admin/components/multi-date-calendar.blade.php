@php
    $selectedDates = collect($getState() ?? [])->filter()->values()->all();
    $holidayRules = $field->getHolidayRules();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
<div
    x-data="{
        month: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
        selected: @js($selectedDates),
        holidayRules: @js($holidayRules),
        limit: @js($field->getMaxSelectableDays()),
        statePath: @js($getStatePath()),
        weekdays: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
        get heading() {
            return this.month.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        },
        gridDays() {
            const firstWeekday = (this.month.getDay() + 6) % 7;
            const start = new Date(this.month.getFullYear(), this.month.getMonth(), 1 - firstWeekday);

            return Array.from({ length: 42 }, (_, index) => {
                const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + index);
                return {
                    date,
                    key: this.key(date),
                    inMonth: date.getMonth() === this.month.getMonth(),
                    weekend: date.getDay() === 0 || date.getDay() === 6,
                    holiday: this.holidayName(date),
                };
            });
        },
        holidayName(date) {
            const fixed = this.holidayRules.fixed[this.key(date).slice(5)] ?? null;
            if (fixed) return fixed;

            const year = date.getFullYear();
            const a = year % 19;
            const b = Math.floor(year / 100);
            const c = year % 100;
            const d = Math.floor(b / 4);
            const e = b % 4;
            const f = Math.floor((b + 8) / 25);
            const g = Math.floor((b - f + 1) / 3);
            const h = (19 * a + b - d - g + 15) % 30;
            const i = Math.floor(c / 4);
            const k = c % 4;
            const l = (32 + 2 * e + 2 * i - h - k) % 7;
            const m = Math.floor((a + 11 * h + 22 * l) / 451);
            const easterPart = h + l - 7 * m + 114;
            const easter = new Date(year, Math.floor(easterPart / 31) - 1, (easterPart % 31) + 1);
            for (const [offset, name] of Object.entries(this.holidayRules.easterOffsets)) {
                const holiday = new Date(easter);
                holiday.setDate(holiday.getDate() + Number(offset));
                if (this.key(holiday) === this.key(date)) return name;
            }

            return null;
        },
        key(date) {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        },
        isDisabled(day) {
            return !day.inMonth || day.weekend || Boolean(day.holiday)
                || (!this.selected.includes(day.key) && this.selected.length >= this.limit);
        },
        toggle(day) {
            if (this.isDisabled(day)) return;
            this.selected = this.selected.includes(day.key)
                ? this.selected.filter(date => date !== day.key)
                : [...this.selected, day.key].sort();
            this.$wire.set(this.statePath, this.selected);
        },
        shiftMonth(offset) {
            this.month = new Date(this.month.getFullYear(), this.month.getMonth() + offset, 1);
        },
        reason(day) {
            if (day.weekend) return 'Fim de semana';
            return day.holiday || '';
        },
    }"
    class="saldo-eleitoral-calendar"
>
    <div class="saldo-eleitoral-calendar__summary">
        <div>
            <strong x-text="`${selected.length} de ${limit} dias selecionados`"></strong>
            <p x-show="limit > 0">Selecione dias úteis. Feriados nacionais, do Paraná e de Umuarama ficam bloqueados.</p>
            <p x-show="limit === 0">Este servidor não possui dias de saldo disponíveis para solicitar.</p>
        </div>
        <span class="saldo-eleitoral-calendar__legend"><i></i> Dia selecionado</span>
    </div>

    <div class="saldo-eleitoral-calendar__panel">
        <div class="saldo-eleitoral-calendar__navigation">
            <button type="button" aria-label="Mês anterior" x-on:click="shiftMonth(-1)">
                <x-heroicon-m-chevron-left />
            </button>
            <h3 x-text="heading"></h3>
            <button type="button" aria-label="Próximo mês" x-on:click="shiftMonth(1)">
                <x-heroicon-m-chevron-right />
            </button>
        </div>

        <div class="saldo-eleitoral-calendar__grid" role="grid" aria-label="Selecione os dias de uso do saldo">
            <template x-for="weekday in weekdays" :key="weekday">
                <span class="saldo-eleitoral-calendar__weekday" role="columnheader" x-text="weekday"></span>
            </template>

            <template x-for="day in gridDays()" :key="day.key">
                <button
                    type="button"
                    role="gridcell"
                    class="saldo-eleitoral-calendar__day"
                    :class="{
                        'is-outside': !day.inMonth,
                        'is-disabled': isDisabled(day),
                        'is-selected': selected.includes(day.key),
                    }"
                    :disabled="isDisabled(day)"
                    :title="reason(day)"
                    :aria-label="`${day.key}${reason(day) ? ` — ${reason(day)}` : ''}`"
                    :aria-pressed="selected.includes(day.key).toString()"
                    x-on:click="toggle(day)"
                    x-text="day.date.getDate()"
                ></button>
            </template>
        </div>

        <div class="saldo-eleitoral-calendar__footer">
            <span><i class="is-disabled"></i> Fins de semana e feriados indisponíveis</span>
            <button type="button" x-show="selected.length" x-on:click="selected = []; $wire.set(statePath, [])">Limpar seleção</button>
        </div>
    </div>

    <style>
        .saldo-eleitoral-calendar { display: grid; gap: 1rem; color: #172b4d; }
        .saldo-eleitoral-calendar__summary, .saldo-eleitoral-calendar__navigation, .saldo-eleitoral-calendar__footer { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
        .saldo-eleitoral-calendar__summary strong { font-size: .95rem; }
        .saldo-eleitoral-calendar__summary p { margin: .25rem 0 0; color: #7183a2; font-size: .8rem; }
        .saldo-eleitoral-calendar__legend, .saldo-eleitoral-calendar__footer span { display: inline-flex; align-items: center; gap: .4rem; color: #7183a2; font-size: .75rem; }
        .saldo-eleitoral-calendar__legend i, .saldo-eleitoral-calendar__footer i { width: .65rem; height: .65rem; border-radius: 50%; background: #dbeafe; }
        .saldo-eleitoral-calendar__panel { padding: 1rem; border: 1px solid #d5e0f0; border-radius: .9rem; background: #fff; }
        .saldo-eleitoral-calendar__navigation { margin-bottom: .9rem; }
        .saldo-eleitoral-calendar__navigation h3 { margin: 0; color: #173b78; font-size: .95rem; font-weight: 650; text-transform: capitalize; }
        .saldo-eleitoral-calendar__navigation button { display: grid; width: 2rem; height: 2rem; place-items: center; border: 1px solid #d5e0f0; border-radius: .55rem; color: #315b98; background: #fff; cursor: pointer; }
        .saldo-eleitoral-calendar__navigation button:hover { background: #f1f6fd; }
        .saldo-eleitoral-calendar__navigation svg { width: 1rem; height: 1rem; }
        .saldo-eleitoral-calendar__grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .3rem; }
        .saldo-eleitoral-calendar__weekday { padding: .35rem 0; color: #8090aa; font-size: .7rem; font-weight: 600; text-align: center; }
        .saldo-eleitoral-calendar__day { min-height: 2.35rem; border: 1px solid transparent; border-radius: .55rem; color: #293d5c; background: transparent; font-size: .82rem; cursor: pointer; transition: background .12s ease, border-color .12s ease; }
        .saldo-eleitoral-calendar__day:not(:disabled):hover { border-color: #bfd5f5; background: #f2f7ff; }
        .saldo-eleitoral-calendar__day.is-selected { border-color: #c8dcfa; background: #eaf3ff; color: #19478d; font-weight: 650; }
        .saldo-eleitoral-calendar__day.is-disabled { color: #aeb8c8; background: #f1f3f6; cursor: not-allowed; }
        .saldo-eleitoral-calendar__day.is-outside { visibility: hidden; }
        .saldo-eleitoral-calendar__footer { margin-top: .9rem; padding-top: .8rem; border-top: 1px solid #edf1f7; }
        .saldo-eleitoral-calendar__footer i.is-disabled { background: #e9edf2; }
        .saldo-eleitoral-calendar__footer button { border: 0; color: #2454a6; background: transparent; font-size: .75rem; font-weight: 600; cursor: pointer; }
        @media (max-width: 480px) { .saldo-eleitoral-calendar__summary { align-items: flex-start; flex-direction: column; } .saldo-eleitoral-calendar__day { min-height: 2.1rem; } }
    </style>
</div>
</x-dynamic-component>
