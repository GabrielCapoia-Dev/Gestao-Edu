# Correção temporária da exportação de pareceres

Scripts destinados exclusivamente à avaliação `3` da escola `ESCOLA - Tempo Integral`.

## Diagnóstico confirmado no dump de 23/09/2026

- Escola: `escolas.id = 69`.
- `1º Ano Integral A`: `turmas.id = 1616`.
- `2º Ano Integral A`: `turmas.id = 1617`.
- `2º Ano Integral B`: `turmas.id = 1618`.
- Avaliação: `avaliacoes.id = 3`.
- Os três ciclos estão `concluida` e `roster_mode = congelado`; por isso a interface mostra “Resposta bloqueada por histórico”. Não se deve desbloquear ou reabrir esses ciclos para resolver a exportação.
- A turma `1618` possui o aluno transferido `31376` — Bernardo Cândido da Silva Dantas — sem registro no `snapshot_evento_atual_id` do ciclo `120`.
- O desvio remove esse aluno do conjunto atual da turma original durante a exportação. O snapshot histórico não é apagado nem alterado.

## Critério exato do aluno problemático

O aluno é selecionado somente quando todas as condições são verdadeiras:

1. pertence atualmente a uma das turmas `1616`, `1617` ou `1618`;
2. possui `alunos.status = 'transferido'`;
3. existe ciclo concluído da avaliação `3` para a turma;
4. o ciclo possui `snapshot_evento_atual_id`;
5. não existe `avaliacao_aluno_snapshots` com esse evento e o `aluno.id`.

Esse critério reproduz a leitura do exportador relacional em `AvaliacaoDocumentoBatchReader`, que procura o snapshot pelo evento final vigente e pelo aluno.

## Execução manual

Faça backup antes de qualquer alteração. Não execute os arquivos em produção sem validar o banco selecionado. Os SQLs não foram executados por este trabalho.

Execute nesta ordem:

1. `01-diagnostico-transferidos.sql` — somente leitura; confirme que a lista problemática é a esperada.
2. `02-desviar-transferidos.sql` — cria as turmas temporárias de forma idempotente e move somente os candidatos.
3. `03-conferencia-desvio.sql` — confirme a lista e o status `transferido`; a ausência de snapshot final para os alunos desviados é esperada.
4. Exporte no sistema as turmas originais da avaliação 3.
5. `04-restaurar-transferidos.sql` — restaura todos os alunos encontrados nas turmas temporárias usando a relação codificada com a turma original.
6. `05-limpar-turmas-temporarias.sql` — remova somente as turmas temporárias vazias.

Em `02-desviar-transferidos.sql`, revise os `SELECT`s antes do `COMMIT`. Se houver divergência, substitua o `COMMIT` por `ROLLBACK` antes de executar o bloco.

## Tabelas e colunas utilizadas

- `escolas`: `id`, `nome`.
- `series`: `id`, `nome`.
- `turmas`: `id`, `codigo`, `nome`, `turno`, `id_serie`, `id_escola`, `created_at`, `updated_at`.
- `alunos`: `id`, `nome`, `cgm`, `id_turma`, `status`, `updated_at`.
- `avaliacao_turma`: `avaliacao_id`, `turma_id`.
- `avaliacao_turma_ciclos`: `avaliacao_id`, `turma_avaliativa_id`, `turma_origem_id`, `status`, `snapshot_evento_atual_id`.
- `avaliacao_aluno_snapshots`: `id`, `evento_id`, `aluno_id`, `tipo`.

## Riscos e limitações

- Os IDs de escola, turmas e avaliação são os confirmados no dump informado. Se o banco do servidor tiver outra carga ou novos IDs, pare e atualize o diagnóstico antes de executar qualquer alteração.
- A turma temporária deve permanecer fora da exportação. Não associe a turma temporária à avaliação.
- A restauração altera somente `alunos.id_turma` e `alunos.updated_at`; status e dados avaliativos permanecem inalterados.
- A limpeza nunca remove turma que ainda tenha aluno. Outras foreign keys podem impedir a exclusão mesmo quando a turma está vazia; nesse caso, não force a remoção.
- O desvio é paliativo. A correção definitiva deve tratar no código o conjunto de alunos usado na exportação de ciclos congelados, sem manipular histórico.
