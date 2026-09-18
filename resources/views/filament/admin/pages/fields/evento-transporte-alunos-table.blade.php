@php
    $excecoes = collect($excecoes ?? [])->map(fn ($id): int => (int) $id)->all();
    $grupos = collect($alunos ?? [])->groupBy(fn ($aluno) => $aluno->turma?->nome ?? 'Turma não identificada');
    $total = collect($alunos ?? [])->reject(fn ($aluno) => in_array((int) $aluno->id, $excecoes, true))->count();
@endphp

<div class="evento-transporte-alunos" x-data="{ removidos: @js($excecoes) }">
    <div class="evento-transporte-alunos__heading">
        <div>
            <p>ALUNOS SELECIONADOS</p>
            <h3>{{ $total }} aluno(s) estimado(s) para transporte</h3>
            <span>Remova individualmente quem não utilizará o transporte.</span>
        </div>
        <strong>{{ $grupos->count() }} turma(s)</strong>
    </div>

    @if ($grupos->isEmpty())
        <div class="evento-transporte-alunos__empty">Selecione ao menos uma escola para listar os alunos.</div>
    @else
        <div class="evento-transporte-alunos__groups">
            @foreach ($grupos as $turma => $alunosDaTurma)
                <details open>
                    <summary><strong>{{ $turma }}</strong><span>{{ $alunosDaTurma->count() }} aluno(s)⌄</span></summary>
                    <table>
                        <thead><tr><th>Aluno</th><th>Escola</th><th>Série</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($alunosDaTurma as $aluno)
                                <tr x-show="!removidos.includes({{ (int) $aluno->id }})">
                                    <td>{{ $aluno->nome }}</td>
                                    <td>{{ $aluno->turma?->escola?->nome ?? 'Não identificada' }}</td>
                                    <td>{{ $aluno->turma?->serie?->nome ?? 'Não informada' }}</td>
                                    <td><button type="button" title="Remover aluno" @click="removidos.push({{ (int) $aluno->id }}); $wire.set('mountedActionsData.0.transporte_excecoes_aluno_ids', [...new Set(removidos)])">&times;</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            @endforeach
        </div>
    @endif
</div>

<style>
    .evento-transporte-alunos { border: 1px solid #cbdcf2; border-radius: .75rem; overflow: hidden; background: #fff; }
    .evento-transporte-alunos__heading { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:1rem 1.1rem; border-bottom:1px solid #e2eaf4; }
    .evento-transporte-alunos__heading p { margin:0 0 .2rem; color:#1d5fb8; font-size:.68rem; font-weight:700; letter-spacing:.05em; }
    .evento-transporte-alunos h3 { margin:0; color:#172b4d; font-size:1rem; }
    .evento-transporte-alunos__heading span, .evento-transporte-alunos__heading strong { color:#64748b; font-size:.75rem; }
    .evento-transporte-alunos__groups { display:grid; gap:.65rem; padding:.8rem; }
    .evento-transporte-alunos details { border:1px solid #dce7f3; border-radius:.6rem; overflow:hidden; }
    .evento-transporte-alunos summary { display:flex; justify-content:space-between; padding:.7rem .85rem; cursor:pointer; background:#f5f8fc; color:#173b73; font-size:.8rem; }
    .evento-transporte-alunos summary span { color:#64748b; font-weight:400; }
    .evento-transporte-alunos table { width:100%; border-collapse:collapse; font-size:.78rem; }
    .evento-transporte-alunos th, .evento-transporte-alunos td { padding:.6rem .85rem; border-top:1px solid #edf1f6; text-align:left; }
    .evento-transporte-alunos th { color:#64748b; font-size:.68rem; text-transform:uppercase; }
    .evento-transporte-alunos td:last-child { width:2.5rem; text-align:center; }
    .evento-transporte-alunos td button { border:0; background:transparent; color:#dc2626; cursor:pointer; font-size:1.25rem; }
    .evento-transporte-alunos__empty { padding:1rem; color:#64748b; font-size:.8rem; }
</style>
