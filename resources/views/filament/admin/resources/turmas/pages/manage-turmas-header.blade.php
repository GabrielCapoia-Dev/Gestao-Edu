<div class="gi-page">
    <section class="gi-hero">
        <div>
            <p class="gi-eyebrow">Pedagogico</p>
            <h1>Turmas</h1>
            <p>Organize series, turnos e vinculos de alunos e professores por unidade escolar.</p>
        </div>

        @if (filled($actions))
            <div class="gi-actions">
                <x-filament::actions :actions="$actions" />
            </div>
        @endif
    </section>
</div>
