@php($componenteId = (int) $componenteId)
@php($alunoId = (int) $aluno->id)
@php($informacoesAtuais = (string) ($informacoesComplementares[$componenteId][$alunoId] ?? ''))
@php($informacaoBloqueada = $this->informacaoComplementarEstaBloqueada($componenteId, $alunoId))
@php($alunoBloqueadoTransferencia = $this->alunoEstaBloqueadoParaAvaliacao($aluno))

<label class="gi-field av-professor-complementary">
    <span>Informações complementares do componente</span>
    <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($informacoesAtuais)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
        <textarea
            x-ref="field"
            x-on:input="count = $event.target.value.length"
            maxlength="1500"
            placeholder="Informações complementares (opcional)"
            class="av-table-input av-textarea-input"
            data-av-editavel
            data-av-autosave-turma-id="{{ $turmaIdAtual }}"
            data-av-autosave-version="{{ $informacaoVersoes[$componenteId][$alunoId] ?? 0 }}"
            wire:model.live.debounce.900ms="informacoesComplementares.{{ $componenteId }}.{{ $alunoId }}"
            @disabled(! $this->podeResponder() || $informacaoBloqueada || $alunoBloqueadoTransferencia)></textarea>
        <div class="av-field-meta">
            <small class="av-field-hint">
                {{ $informacaoBloqueada || $alunoBloqueadoTransferencia ? 'Campo bloqueado para este aluno.' : 'Campo opcional.' }}
            </small>
            <small class="av-char-count" x-text="`${count}/1500`"></small>
        </div>
    </div>
</label>
