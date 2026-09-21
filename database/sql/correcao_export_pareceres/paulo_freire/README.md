# Paulo Freire — desvio temporário de alunos sem snapshot

Análise baseada no dump `gestao-edu (3).sql`, sem execução do dump.

- Escola: `escolas.id = 62` (`ESCOLA - Paulo Freire`)
- 1º Ano: turmas `1362`, `1363`, `1364`, `1365`; ciclos da avaliação `3` estão `aberta`.
- 2º Ano: turmas `1366`, `1367`, `1368`, `1369`; ciclos da avaliação `3` estão `concluida`.
- Alunos sem snapshot final no 2º Ano: `5` no dump — `3 transferidos` e `2 remanejados`.

O exportador inclui aluno principal com qualquer status diferente de `pendente`, por isso o script move todos os cinco alunos sem snapshot, e não somente os transferidos. A identificação é feita novamente no banco de produção por relacionamento entre `avaliacao_turma_ciclos` e `avaliacao_aluno_snapshots`; não há lista fixa de alunos.

## Ordem manual

1. Fazer backup.
2. Executar `02-desviar-2ano-sem-snapshot.sql` no banco real.
3. Conferir a lista retornada e exportar os pareceres do 2º Ano.
4. Executar `04-restaurar-2ano.sql`.
5. Conferir que as turmas temporárias estão vazias.
6. Executar `05-limpar-turmas-temporarias.sql` somente para limpar turmas vazias, se desejado.

O 1º Ano não foi incluído no desvio: o dump mostra ciclos ainda abertos e, portanto, não existe snapshot final de conclusão contra o qual seja seguro aplicar esse critério. Se a exportação do 1º Ano também falhar, é necessária uma análise própria do ciclo antes de mover alunos.
