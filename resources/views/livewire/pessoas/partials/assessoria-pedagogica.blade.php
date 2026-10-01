@if ($cargo === 'assessoria_pedagogica' && $podeGerenciarEquipeGestora)
    @php
        $idsAssessoriaSelecionadas = collect($escolaIdsAssessoria)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $escolasAssessoriaSelecionadas = collect($escolasOptions)
            ->filter(fn (string $label, int|string $id): bool => in_array((int) $id, $idsAssessoriaSelecionadas, true));
        $escolasAssessoriaDisponiveis = collect($escolasOptions)
            ->reject(fn (string $label, int|string $id): bool => in_array((int) $id, $idsAssessoriaSelecionadas, true));
    @endphp

    <section class="pe-person-form__section" aria-labelledby="pessoa-form-assessoria-pedagogica-title">
        <header class="pe-person-form__section-header">
            <span class="pe-person-form__section-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-academic-cap" />
            </span>
            <div>
                <h3 id="pessoa-form-assessoria-pedagogica-title">Assessoria Pedagógica</h3>
                <p>Defina as escolas sob responsabilidade da Assessoria Pedagógica.</p>
            </div>
        </header>

        <div class="pe-person-form__grid">
            <fieldset class="pe-person-form__field pe-person-form__span-2">
                <legend>Escolas assessoradas <b aria-hidden="true">*</b></legend>
                <div class="pe-person-form__school-groups">
                    <section class="pe-person-form__school-group pe-person-form__school-group--selected" aria-labelledby="pessoa-form-escolas-selecionadas">
                        <header>
                            <h4 id="pessoa-form-escolas-selecionadas">Escolas selecionadas</h4>
                            <span>{{ $escolasAssessoriaSelecionadas->count() }}</span>
                        </header>
                        <div class="pe-person-form__checks pe-person-form__checks--2 pe-person-form__checks--scroll">
                            @forelse ($escolasAssessoriaSelecionadas as $value => $label)
                                <label wire:key="assessoria-school-{{ $value }}">
                                    <input type="checkbox" value="{{ $value }}" @checked(in_array((int) $value, $idsAssessoriaSelecionadas, true)) wire:model.live.debounce.200ms="escolaIdsAssessoria" wire:loading.attr="disabled">
                                    {{ $label }}
                                </label>
                            @empty
                                <p>Nenhuma escola selecionada.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="pe-person-form__school-group" aria-labelledby="pessoa-form-escolas-disponiveis">
                        <header>
                            <h4 id="pessoa-form-escolas-disponiveis">Outras escolas disponíveis</h4>
                            <span>{{ $escolasAssessoriaDisponiveis->count() }}</span>
                        </header>
                        <div class="pe-person-form__checks pe-person-form__checks--2 pe-person-form__checks--scroll">
                            @forelse ($escolasAssessoriaDisponiveis as $value => $label)
                                <label wire:key="assessoria-school-{{ $value }}">
                                    <input type="checkbox" value="{{ $value }}" @checked(in_array((int) $value, $idsAssessoriaSelecionadas, true)) wire:model.live.debounce.200ms="escolaIdsAssessoria" wire:loading.attr="disabled">
                                    {{ $label }}
                                </label>
                            @empty
                                <p>Não há outras escolas disponíveis.</p>
                            @endforelse
                        </div>
                    </section>
                </div>
                @error('escolaIdsAssessoria')
                    <small class="is-error">{{ $message }}</small>
                @enderror
                @error('escolaIdsAssessoria.*')
                    <small class="is-error">{{ $message }}</small>
                @enderror
            </fieldset>
        </div>
    </section>
@endif
