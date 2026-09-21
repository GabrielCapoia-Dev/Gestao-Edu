@php
    $excecoes = collect($excecoes ?? [])->map(fn ($id): int => (int) $id)->all();
    $statePath = $getStatePath();
    $formStatePath = str($statePath)->beforeLast('.');
    $excecoesStatePath = $formStatePath.'.transporte_excecoes_aluno_ids';
    $grupos = collect($alunos ?? [])
        ->groupBy(fn ($aluno) => $aluno->turma?->escola?->nome ?? 'Escola não identificada')
        ->map(fn ($porEscola) => $porEscola->groupBy(fn ($aluno) => $aluno->turma?->serie?->nome ?? 'Série não identificada'));
    $total = collect($alunos ?? [])->reject(fn ($aluno) => in_array((int) $aluno->id, $excecoes, true))->count();
    $turmas = collect($alunos ?? [])->pluck('id_turma')->filter()->unique()->count();
@endphp

<div class="evento-transporte-alunos" x-data="{ removidos: @js($excecoes) }">
    <div class="evento-transporte-alunos__heading">
        <div>
            <p>ALUNOS SELECIONADOS</p>
            <h3>{{ $total }} aluno(s) estimado(s) para transporte</h3>
            <span>Remova individualmente quem não utilizará o transporte.</span>
        </div>
        <strong>{{ $turmas }} turma(s)</strong>
    </div>

    @if ($grupos->isEmpty())
        <div class="evento-transporte-alunos__empty">{{ $mensagem ?? 'Nenhum aluno encontrado para os filtros informados.' }}</div>
    @else
        <div class="evento-transporte-alunos__actions">
            <button type="button" @click="$el.closest('.evento-transporte-alunos').querySelectorAll('details').forEach((grupo) => grupo.open = true)">Abrir todos</button>
            <button type="button" @click="$el.closest('.evento-transporte-alunos').querySelectorAll('details').forEach((grupo) => grupo.open = false)">Fechar todos</button>
        </div>
        <div class="evento-transporte-alunos__groups">
            @foreach ($grupos as $escola => $porSerie)
                <details>
                    <summary><strong>{{ $escola }}</strong><span>{{ $porSerie->flatten()->count() }} aluno(s)⌄</span></summary>
                    @foreach ($porSerie as $serie => $alunosDaSerie)
                        <details class="evento-transporte-alunos__serie">
                            <summary><strong>{{ $serie }}</strong><span>{{ $alunosDaSerie->count() }} aluno(s)⌄</span></summary>
                            @foreach ($alunosDaSerie->groupBy(fn ($aluno) => $aluno->turma?->nome ?? 'Turma não identificada') as $turma => $alunosDaTurma)
                                <details class="evento-transporte-alunos__turma">
                                    <summary><strong>{{ $turma }} - {{ ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite', 'integral' => 'Integral'][$alunosDaTurma->first()->turma?->turno] ?? 'Turno não identificado' }}</strong><span>{{ $alunosDaTurma->count() }} aluno(s)⌄</span></summary>
                                    <table>
                                        <thead><tr><th>Aluno</th><th></th></tr></thead>
                                        <tbody>
                                            @foreach ($alunosDaTurma as $aluno)
                                                <tr x-show="!removidos.includes({{ (int) $aluno->id }})">
                                                    <td>{{ $aluno->nome }}</td>
                                                    <td><button type="button" title="Remover aluno" @click="removidos.push({{ (int) $aluno->id }}); $wire.set(@js($excecoesStatePath), [...new Set(removidos)])">&times;</button></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </details>
                            @endforeach
                        </details>
                    @endforeach
                </details>
            @endforeach
        </div>
    @endif
</div>
