@php($pautaId = (int) $pauta->id)
@php($alunoId = (int) $aluno->id)
@php($alternativasPauta = $this->alternativasDaPauta($pautaId))
@php($respostaBloqueada = $this->respostaEstaBloqueada($pautaId, $alunoId))
@php($alunoBloqueadoTransferencia = $this->alunoEstaBloqueadoParaAvaliacao($aluno))
@php($alternativaSelecionadaId = (int) ($respostas[$pautaId][$alunoId]['alternativa_id'] ?? 0))
@php($requerObservacao = $this->alternativaRequerObservacao($pautaId, $alternativaSelecionadaId))

<tr data-av-response-row wire:key="resposta-professor-linha-{{ $turmaIdAtual }}-{{ $pautaId }}-{{ $alunoId }}">
    <td data-label="Aluno">
        <strong>{{ $aluno->nome }}</strong>
        <small>{{ $aluno->cgm ? 'CGM '.$aluno->cgm : 'Sem CGM' }}</small>
        @if ($respostaBloqueada || $alunoBloqueadoTransferencia)
            <small class="av-professor-row-blocked">Resposta bloqueada.</small>
        @endif
    </td>
    <td data-label="Alternativa">
        <select
            class="av-table-input"
            data-av-editavel
            data-av-autosave-turma-id="{{ $turmaIdAtual }}"
            data-av-autosave-version="{{ $respostaVersoes[$pautaId][$alunoId] ?? 0 }}"
            data-av-autosave-expected-alternativa="{{ $respostas[$pautaId][$alunoId]['alternativa_id'] ?? '' }}"
            data-av-autosave-expected-observacao="{{ $respostas[$pautaId][$alunoId]['observacao'] ?? '' }}"
            wire:model.live="respostas.{{ $pautaId }}.{{ $alunoId }}.alternativa_id"
            @disabled(! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)>
            <option value="">Selecione</option>
            @foreach ($alternativasPauta as $alternativa)
                <option value="{{ $alternativa['id'] }}" data-requires-observation="{{ ($alternativa['tem_observacao'] ?? false) ? 1 : 0 }}" data-observation-placeholder="{{ $alternativa['observacao'] ?? 'Observação obrigatória' }}">
                    {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observação)' : '' }}
                </option>
            @endforeach
        </select>
    </td>
    <td data-label="Observação">
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
            <small class="av-char-count" x-text="`${count}/1500`"></small>
        </div>
    </td>
</tr>
