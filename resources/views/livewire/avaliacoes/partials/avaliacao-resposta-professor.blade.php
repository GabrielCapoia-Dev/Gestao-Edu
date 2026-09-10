@php($pautaId = (int) $pauta->id)
@php($alunoId = (int) $aluno->id)
@php($alternativasPauta = $this->alternativasDaPauta($pautaId))
@php($respostaBloqueada = $this->respostaEstaBloqueada($pautaId, $alunoId))
@php($alunoBloqueadoTransferencia = $this->alunoEstaBloqueadoParaAvaliacao($aluno))
@php($referenciaOrigem = $respostas[$pautaId][$alunoId]['origem_referencia'] ?? null)
@php($alternativaSelecionadaId = (int) ($respostas[$pautaId][$alunoId]['alternativa_id'] ?? 0))
@php($requerObservacao = $this->alternativaRequerObservacao($pautaId, $alternativaSelecionadaId))

<article class="av-professor-answer-card" data-av-response-row wire:key="resposta-professor-{{ $turmaIdAtual }}-{{ $pautaId }}-{{ $alunoId }}">
    <header class="av-professor-answer-card__header">
        <div>
            <strong>{{ $tituloResposta }}</strong>
            @if (! empty($subtituloResposta))
                <span>{{ $subtituloResposta }}</span>
            @endif
        </div>

        @if ($respostaBloqueada || $alunoBloqueadoTransferencia)
            <span class="av-professor-status av-professor-status--blocked">Bloqueada</span>
        @endif
    </header>

    @if ($referenciaOrigem)
        <p class="av-professor-origin">
            Origem: {{ $referenciaOrigem['alternativa'] !== '' ? $referenciaOrigem['alternativa'] : 'Não avaliado' }}{{ $referenciaOrigem['observacao'] !== '' ? ' · '.$referenciaOrigem['observacao'] : '' }}
        </p>
    @endif

    <div class="av-professor-answer-fields">
        <label class="gi-field">
            <span>Alternativa</span>
            <select
                class="av-table-input"
                data-av-editavel
                data-av-autosave-turma-id="{{ $turmaIdAtual }}"
                data-av-autosave-version="{{ $respostaVersoes[$pautaId][$alunoId] ?? 0 }}"
                data-av-autosave-expected-alternativa="{{ $respostas[$pautaId][$alunoId]['alternativa_id'] ?? '' }}"
                data-av-autosave-expected-observacao="{{ $respostas[$pautaId][$alunoId]['observacao'] ?? '' }}"
                wire:model.live="respostas.{{ $pautaId }}.{{ $alunoId }}.alternativa_id"
                @disabled(! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)>
                <option value="">Selecione uma alternativa</option>
                @foreach ($alternativasPauta as $alternativa)
                    <option value="{{ $alternativa['id'] }}" data-requires-observation="{{ ($alternativa['tem_observacao'] ?? false) ? 1 : 0 }}" data-observation-placeholder="{{ $alternativa['observacao'] ?? 'Observação obrigatória' }}">
                        {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observação)' : '' }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="gi-field">
            <span>Observação da pauta</span>
            @php($observacaoAtual = (string) ($respostas[$pautaId][$alunoId]['observacao'] ?? ''))
            @php($observacaoBloqueada = ! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)
            <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($observacaoAtual)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
                <textarea
                    x-ref="field"
                    x-on:input="count = $event.target.value.length"
                    maxlength="1500"
                    placeholder="{{ $requerObservacao ? $this->placeholderObservacaoAlternativa($pautaId, $alternativaSelecionadaId) : 'Selecione uma alternativa que exija observação.' }}"
                    class="av-table-input av-textarea-input"
                    data-av-editavel
                    data-av-observation-field
                    data-av-observation-locked="{{ $observacaoBloqueada ? 1 : 0 }}"
                    data-av-autosave-turma-id="{{ $turmaIdAtual }}"
                    data-av-autosave-version="{{ $respostaVersoes[$pautaId][$alunoId] ?? 0 }}"
                    data-av-autosave-expected-alternativa="{{ $respostas[$pautaId][$alunoId]['alternativa_id'] ?? '' }}"
                    data-av-autosave-expected-observacao="{{ $respostas[$pautaId][$alunoId]['observacao'] ?? '' }}"
                    wire:model.live.debounce.700ms="respostas.{{ $pautaId }}.{{ $alunoId }}.observacao"
                    @disabled(! $requerObservacao || $observacaoBloqueada)></textarea>
                <div class="av-field-meta">
                    <small class="av-field-hint {{ $requerObservacao ? 'av-field-hint--danger' : '' }}">{{ $requerObservacao ? 'Obrigatória para esta alternativa.' : 'Será habilitada quando necessária.' }}</small>
                    <small class="av-char-count" x-text="`${count}/1500`"></small>
                </div>
            </div>
        </label>
    </div>
</article>
