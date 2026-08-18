<style>
    .fi-resource-alunos.fi-resource-list-records-page {
        --aluno-card-border: color-mix(in oklab, var(--gray-200) 78%, var(--primary-100));
        --aluno-card-surface: #ffffff;
        --aluno-card-muted: var(--gray-500);
        --aluno-card-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-page-content,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content-ctn,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content-ctn,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-grid,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-split,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-stack {
        max-width: 100%;
        min-width: 0;
    }

    /*
     * O menu do ActionGroup pode ultrapassar a última linha da tabela.
     * overflow-x:hidden faz o outro eixo se comportar como área de recorte/scroll
     * em navegadores modernos, fazendo o dropdown disputar camada com o rodapé.
     */
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content-ctn {
        overflow: visible !important;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content {
        display: grid;
        gap: 0.5rem;
        padding: 0.5rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content-header {
        flex-wrap: wrap;
        gap: 0.5rem;
        padding: 0.5rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-sorting-settings {
        display: flex;
        flex: 1 1 24rem;
        flex-wrap: wrap;
        gap: 0.5rem;
        min-width: 0;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-sorting-settings label {
        flex: 1 1 14rem;
        min-width: min(14rem, 100%);
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record {
        position: relative;
        z-index: 0;
        border: 1px solid var(--aluno-card-border);
        border-radius: 0.5rem;
        background: var(--aluno-card-surface);
        box-shadow: var(--aluno-card-shadow);
    }

    /*
     * Enquanto o usuário interage com o botão/menu de Ações, a linha precisa ficar
     * acima das linhas seguintes e do rodapé/paginação da tabela.
     */
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record:hover,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record:focus-within {
        z-index: 60;
        border-color: color-mix(in oklab, var(--primary-300) 58%, var(--gray-200));
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content-ctn > .fi-ta-actions {
        position: relative;
        z-index: 70;
        padding-top: 0.35rem;
        border-top: 1px solid var(--aluno-card-border);
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content-ctn > .fi-ta-actions .fi-dropdown,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content-ctn > .fi-ta-actions .fi-dropdown-panel {
        z-index: 80 !important;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content {
        display: grid;
        gap: 0.5rem;
        width: 100%;
        padding: 0.65rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-main-grid {
        gap: 0.5rem 0.65rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-name .fi-ta-text-item,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field .fi-ta-text-item,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field .fi-ta-text-description {
        overflow-wrap: anywhere;
        word-break: normal;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-name .fi-ta-text-description,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field .fi-ta-text-description {
        color: var(--aluno-card-muted);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
        letter-spacing: 0;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-name .fi-ta-text-item {
        color: var(--primary-800);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-bold);
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field .fi-badge,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field .fi-ta-text-item {
        max-width: 100%;
        white-space: normal;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field--school .fi-ta-text-item {
        color: var(--gray-950);
        font-weight: var(--font-weight-medium);
    }

    @media (max-width: 48rem) {
        .fi-resource-alunos.fi-resource-list-records-page .fi-page-content {
            padding-inline: 0.75rem;
        }

        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-header,
        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content,
        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content-header {
            padding: 0.5rem;
        }

        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content {
            gap: 0.45rem;
            padding: 0.6rem;
        }
    }

    @media (max-width: 24rem) {
        .fi-resource-alunos.fi-resource-list-records-page .fi-page-content {
            padding-inline: 0.5rem;
        }

        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content,
        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content-header {
            padding: 0.4rem;
        }

        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content {
            padding: 0.5rem;
        }
    }
</style>
