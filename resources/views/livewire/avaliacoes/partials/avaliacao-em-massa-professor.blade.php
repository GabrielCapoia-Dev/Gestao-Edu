@if ($this->podePreencherEmMassa())
    <section class="av-professor-bulk" aria-label="Avaliação em massa">
        <div>
            <strong>Avaliar em massa</strong>
            <span>{{ $visualizacao === 'pautas' ? 'Aplicar aos alunos sem resposta desta pauta.' : 'Aplicar às pautas compatíveis ainda não respondidas deste aluno.' }}</span>
        </div>
        <label>
            <span class="sr-only">Alternativa para avaliação em massa</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model="avaliacaoEmMassaGlobal">
                    <option value="">Selecione uma alternativa</option>
                    @foreach ($this->alternativasEmMassaModal as $alternativa)
                        <option value="{{ $alternativa['id'] }}">{{ $alternativa['nome'] }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <button
            type="button"
            class="gi-action gi-action--primary"
            wire:click="aplicarEmMassaNoModal"
            wire:loading.attr="disabled"
            wire:target="aplicarEmMassaNoModal">
            <span wire:loading.remove wire:target="aplicarEmMassaNoModal">Aplicar</span>
            <span wire:loading wire:target="aplicarEmMassaNoModal">Aplicando...</span>
        </button>
    </section>
@endif
