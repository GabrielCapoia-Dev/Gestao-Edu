@php
    $statusLabels = \App\Models\Aluno::statusOptions();
    $vinculoLabels = \App\Models\Aluno::tiposVinculoOptions();

    $sexoLabel = match ($aluno->sexo) {
        'F' => 'Feminino',
        'M' => 'Masculino',
        default => 'Não informado',
    };

    $turnoLabel = static fn (?string $turno): string => match ($turno) {
        'manha' => 'Manhã',
        'tarde' => 'Tarde',
        'noite' => 'Noite',
        'integral' => 'Integral',
        default => filled($turno) ? ucfirst((string) $turno) : 'Não informado',
    };

    $statusClass = static fn (?string $status): string => match ($status) {
        \App\Models\Aluno::STATUS_MATRICULADO => 'ficha-badge ficha-badge--success',
        \App\Models\Aluno::STATUS_PENDENTE => 'ficha-badge ficha-badge--warning',
        \App\Models\Aluno::STATUS_REMANEJADO => 'ficha-badge ficha-badge--warning',
        \App\Models\Aluno::STATUS_TRANSFERIDO => 'ficha-badge ficha-badge--info',
        \App\Models\Aluno::STATUS_APROVADO => 'ficha-badge ficha-badge--success',
        \App\Models\Aluno::STATUS_RETIDO => 'ficha-badge ficha-badge--danger',
        default => 'ficha-badge',
    };

    $idade = $aluno->data_nascimento?->age;
@endphp

<style>
    .ficha-aluno { display: grid; gap: 1rem; color: #172033; }
    .ficha-hero { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.15rem 1.25rem; border: 1px solid #d9e2ef; border-radius: 16px; background: linear-gradient(135deg, #f8fbff 0%, #eef5ff 100%); }
    .ficha-hero__name { margin: 0; font-size: 1.08rem; font-weight: 800; color: #13213a; }
    .ficha-hero__meta { display: flex; flex-wrap: wrap; gap: .45rem .85rem; margin-top: .45rem; color: #58677d; font-size: .86rem; }
    .ficha-hero__badges { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .45rem; }
    .ficha-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 1rem; }
    .ficha-card { grid-column: span 6; border: 1px solid #dfe6ef; border-radius: 16px; background: #fff; overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
    .ficha-card--full { grid-column: 1 / -1; }
    .ficha-card__title { padding: .85rem 1rem; border-bottom: 1px solid #e7edf4; background: #fbfcfe; font-size: .9rem; font-weight: 800; color: #1d2a40; }
    .ficha-card__body { padding: 1rem; }
    .ficha-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .9rem 1.25rem; }
    .ficha-field--full { grid-column: 1 / -1; }
    .ficha-label { margin-bottom: .22rem; color: #738198; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .035em; }
    .ficha-value { color: #172033; font-size: .9rem; font-weight: 600; word-break: break-word; }
    .ficha-value--muted { color: #8995a8; font-weight: 500; }
    .ficha-badge { display: inline-flex; align-items: center; width: fit-content; border-radius: 999px; padding: .25rem .58rem; background: #eef2f7; color: #4b5b70; font-size: .74rem; font-weight: 800; white-space: nowrap; }
    .ficha-badge--success { background: #e8f8ef; color: #087a46; }
    .ficha-badge--warning { background: #fff5df; color: #a35b00; }
    .ficha-badge--info { background: #e9f2ff; color: #1556a8; }
    .ficha-badge--danger { background: #feecee; color: #bd2532; }
    .ficha-table-wrap { overflow-x: auto; }
    .ficha-table { width: 100%; border-collapse: collapse; min-width: 880px; }
    .ficha-table th { padding: .65rem .75rem; border-bottom: 1px solid #dfe6ef; background: #f8fafc; color: #68778e; font-size: .7rem; text-align: left; text-transform: uppercase; letter-spacing: .035em; }
    .ficha-table td { padding: .72rem .75rem; border-bottom: 1px solid #edf1f6; color: #26344a; font-size: .82rem; vertical-align: middle; }
    .ficha-table tr:last-child td { border-bottom: 0; }
    .ficha-table tr.ficha-table__current td { background: #f3f8ff; }
    .ficha-current { color: #1d5fbf; font-size: .7rem; font-weight: 800; }
    .ficha-alert { padding: .8rem .9rem; border: 1px solid #f0d49b; border-radius: 12px; background: #fff9ec; color: #81520a; font-size: .82rem; line-height: 1.45; }
    @media (max-width: 900px) {
        .ficha-card { grid-column: 1 / -1; }
        .ficha-hero { flex-direction: column; }
        .ficha-hero__badges { justify-content: flex-start; }
    }
    @media (max-width: 620px) {
        .ficha-fields { grid-template-columns: 1fr; }
        .ficha-field--full { grid-column: auto; }
    }
</style>

<div class="ficha-aluno">
    <div class="ficha-hero">
        <div>
            <h3 class="ficha-hero__name">{{ $aluno->nome }}</h3>
            <div class="ficha-hero__meta">
                <span><strong>CGM:</strong> {{ $aluno->cgm ?: 'Não informado' }}</span>
                <span><strong>Escola deste registro:</strong> {{ $aluno->turma?->escola?->nome ?: 'Não informada' }}</span>
                <span><strong>Registro:</strong> #{{ $aluno->id }}</span>
            </div>
        </div>
        <div class="ficha-hero__badges">
            <span class="{{ $statusClass($aluno->status) }}">{{ $statusLabels[$aluno->status] ?? ucfirst((string) $aluno->status) }}</span>
            <span class="ficha-badge ficha-badge--info">{{ $vinculoLabels[$aluno->tipo_vinculo] ?? ucfirst((string) $aluno->tipo_vinculo) }}</span>
        </div>
    </div>

    @if ($aluno->estaMatriculado() && $vinculos->contains(fn ($vinculo) => $vinculo->isPrincipal() && $vinculo->estaPendente()))
        <div class="ficha-alert">
            Este é o vínculo matriculado atual. Há também uma matrícula Principal pendente para este CGM em outra escola. Consulte a seção “Todos os vínculos deste CGM” abaixo para visualizar a matrícula de destino e a data correspondente a ela.
        </div>
    @endif

    <div class="ficha-grid">
        <section class="ficha-card">
            <div class="ficha-card__title">Identificação do aluno</div>
            <div class="ficha-card__body ficha-fields">
                <div class="ficha-field--full">
                    <div class="ficha-label">Nome completo</div>
                    <div class="ficha-value">{{ $aluno->nome ?: 'Não informado' }}</div>
                </div>
                <div>
                    <div class="ficha-label">CGM</div>
                    <div class="ficha-value">{{ $aluno->cgm ?: 'Não informado' }}</div>
                </div>
                <div>
                    <div class="ficha-label">Sexo</div>
                    <div class="ficha-value">{{ $sexoLabel }}</div>
                </div>
                <div>
                    <div class="ficha-label">Data de nascimento</div>
                    <div class="ficha-value">{{ $aluno->data_nascimento?->format('d/m/Y') ?? 'Não informada' }}</div>
                </div>
                <div>
                    <div class="ficha-label">Idade</div>
                    <div class="ficha-value">{{ $idade !== null ? $idade.' anos' : 'Não informada' }}</div>
                </div>
            </div>
        </section>

        <section class="ficha-card">
            <div class="ficha-card__title">Matrícula deste registro</div>
            <div class="ficha-card__body ficha-fields">
                <div>
                    <div class="ficha-label">Status</div>
                    <div class="ficha-value"><span class="{{ $statusClass($aluno->status) }}">{{ $statusLabels[$aluno->status] ?? ucfirst((string) $aluno->status) }}</span></div>
                </div>
                <div>
                    <div class="ficha-label">Tipo de vínculo</div>
                    <div class="ficha-value">{{ $vinculoLabels[$aluno->tipo_vinculo] ?? ucfirst((string) $aluno->tipo_vinculo) }}</div>
                </div>
                <div>
                    <div class="ficha-label">Data de matrícula</div>
                    <div class="ficha-value {{ $aluno->data_matricula ? '' : 'ficha-value--muted' }}">{{ $aluno->data_matricula?->format('d/m/Y') ?? 'Não informada neste vínculo' }}</div>
                </div>
                <div>
                    <div class="ficha-label">Contra turno</div>
                    <div class="ficha-value">{{ $aluno->isContraTurno() ? 'Este registro é Contra Turno' : ($aluno->permite_contra_turno ? 'Permitido' : 'Não permitido') }}</div>
                </div>
            </div>
        </section>

        <section class="ficha-card">
            <div class="ficha-card__title">Turma e unidade deste registro</div>
            <div class="ficha-card__body ficha-fields">
                @if ($podeListarEscolas)
                    <div class="ficha-field--full">
                        <div class="ficha-label">Escola</div>
                        <div class="ficha-value">{{ $aluno->turma?->escola?->nome ?: 'Não informada' }}</div>
                    </div>
                @endif
                <div>
                    <div class="ficha-label">Série</div>
                    <div class="ficha-value">{{ $aluno->turma?->serie?->nome ?: 'Não informada' }}</div>
                </div>
                <div>
                    <div class="ficha-label">Turma</div>
                    <div class="ficha-value">{{ $aluno->turma?->nome ?: 'Não informada' }}</div>
                </div>
                <div>
                    <div class="ficha-label">Turno</div>
                    <div class="ficha-value">{{ $turnoLabel($aluno->turma?->turno) }}</div>
                </div>
                <div>
                    <div class="ficha-label">Código da turma</div>
                    <div class="ficha-value">{{ $aluno->turma?->codigo ?: 'Não informado' }}</div>
                </div>
            </div>
        </section>

        <section class="ficha-card">
            <div class="ficha-card__title">Movimentação e situação</div>
            <div class="ficha-card__body ficha-fields">
                <div>
                    <div class="ficha-label">Última alteração de status</div>
                    <div class="ficha-value">{{ $aluno->status_alterado_em?->format('d/m/Y H:i') ?? 'Não informada' }}</div>
                </div>
                <div>
                    <div class="ficha-label">Responsável pela alteração</div>
                    <div class="ficha-value">{{ $aluno->statusAlteradoPor?->name ?? $aluno->statusAlteradoPor?->nome ?? 'Sistema / não informado' }}</div>
                </div>
                <div class="ficha-field--full">
                    <div class="ficha-label">Motivo / observação de status</div>
                    <div class="ficha-value {{ filled($aluno->status_motivo) ? '' : 'ficha-value--muted' }}">{{ $aluno->status_motivo ?: 'Nenhum motivo registrado' }}</div>
                </div>
                @if ($aluno->turmaOrigem || $aluno->alunoOrigem)
                    <div class="ficha-field--full">
                        <div class="ficha-label">Origem da movimentação</div>
                        <div class="ficha-value">
                            {{ $aluno->turmaOrigem?->escola?->nome ?? $aluno->alunoOrigem?->turma?->escola?->nome ?? 'Escola não identificada' }}
                            — {{ $aluno->turmaOrigem?->serie?->nome ?? $aluno->alunoOrigem?->turma?->serie?->nome ?? 'Série não identificada' }}
                            / {{ $aluno->turmaOrigem?->nome ?? $aluno->alunoOrigem?->turma?->nome ?? 'Turma não identificada' }}
                        </div>
                    </div>
                @endif
                @if ($aluno->pendenciaOrigem)
                    <div class="ficha-field--full">
                        <div class="ficha-label">Transferência aguardando origem</div>
                        <div class="ficha-value">
                            {{ $aluno->pendenciaOrigem?->turma?->escola?->nome ?? 'Escola não identificada' }}
                            — vínculo #{{ $aluno->pendenciaOrigem?->id }}
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <section class="ficha-card ficha-card--full">
            <div class="ficha-card__title">Todos os vínculos deste CGM</div>
            <div class="ficha-card__body" style="padding: 0;">
                <div class="ficha-table-wrap">
                    <table class="ficha-table">
                        <thead>
                            <tr>
                                <th>Vínculo</th>
                                <th>Status</th>
                                <th>Escola</th>
                                <th>Série / Turma</th>
                                <th>Turno</th>
                                <th>Data matrícula</th>
                                <th>Alterado em</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vinculos as $vinculo)
                                <tr class="{{ (int) $vinculo->id === (int) $aluno->id ? 'ficha-table__current' : '' }}">
                                    <td>
                                        <div>{{ $vinculoLabels[$vinculo->tipo_vinculo] ?? ucfirst((string) $vinculo->tipo_vinculo) }}</div>
                                        @if ((int) $vinculo->id === (int) $aluno->id)
                                            <div class="ficha-current">REGISTRO ABERTO</div>
                                        @endif
                                    </td>
                                    <td><span class="{{ $statusClass($vinculo->status) }}">{{ $statusLabels[$vinculo->status] ?? ucfirst((string) $vinculo->status) }}</span></td>
                                    <td>{{ $podeListarEscolas ? ($vinculo->turma?->escola?->nome ?: '—') : '—' }}</td>
                                    <td>{{ $vinculo->turma?->serie?->nome ?: '—' }} / {{ $vinculo->turma?->nome ?: '—' }}</td>
                                    <td>{{ $turnoLabel($vinculo->turma?->turno) }}</td>
                                    <td>{{ $vinculo->data_matricula?->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $vinculo->status_alterado_em?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7">Nenhum vínculo relacionado encontrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>