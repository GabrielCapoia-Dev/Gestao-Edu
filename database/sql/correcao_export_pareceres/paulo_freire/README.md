# Paulo Freire — desvio temporário de alunos sem snapshot

Análise baseada no dump `gestao-edu (3).sql`, sem execução do dump.

- Escola: `escolas.id = 62` (`ESCOLA - Paulo Freire`)
- No dump anterior `gestao-edu (3).sql`, o 1º Ano estava com ciclos `aberta`; no dump atual `gestao-edu.sql`, os quatro ciclos estão `concluida`.
- 2º Ano: turmas `1366`, `1367`, `1368`, `1369`; ciclos da avaliação `3` estão `concluida`.
- Alunos sem snapshot final no 2º Ano: `5` no dump — `3 transferidos` e `2 remanejados`.

No dump posterior `gestao-edu.sql`, os ciclos do 1º Ano estão concluídos. Foi encontrado 1 aluno sem snapshot final na turma `1362`: `ANTHONY MIGUEL DE LIMA DA SILVA` (`status = remanejado`). Para esse caso, usar `02-desviar-1ano-sem-snapshot.sql` e `04-restaurar-1ano.sql`.

O exportador inclui aluno principal com qualquer status diferente de `pendente`, por isso o script move todos os cinco alunos sem snapshot, e não somente os transferidos. A identificação é feita novamente no banco de produção por relacionamento entre `avaliacao_turma_ciclos` e `avaliacao_aluno_snapshots`; não há lista fixa de alunos.

## Ordem manual

1. Fazer backup.
2. Executar `02-desviar-2ano-sem-snapshot.sql` no banco real.
3. Conferir a lista retornada e exportar os pareceres do 2º Ano.
4. Executar `04-restaurar-2ano.sql`.
5. Conferir que as turmas temporárias estão vazias.
6. Executar `05-limpar-turmas-temporarias.sql` somente para limpar turmas vazias, se desejado.

Os scripts do 1º Ano usam o mesmo critério do exportador: aluno principal com status diferente de `pendente`, ciclo concluído da avaliação `3` e ausência do snapshot `conclusao` do evento atual.
