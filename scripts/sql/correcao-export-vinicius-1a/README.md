# Vinicius de Morais — 1º Ano A

## Diagnóstico

Backup analisado: `gestao-edu 24-09.sql`.

- Escola: `ESCOLA - Vinicius de Morais`, `escolas.id = 70`.
- Turma: `1º Ano A`, `turmas.id = 1645`.
- Avaliação: `avaliacoes.id = 3`.
- Ciclo: `avaliacao_turma_ciclos.id = 124`, concluído e congelado.
- Não há aluno com status `transferido` nessa turma.
- Há um aluno `remanejado` sem snapshot final: `alunos.id = 31513`, Mikaelly Barbosa Cardoso.
- O ciclo possui 20 alunos atuais elegíveis e 19 snapshots finais; o snapshot ausente é o da aluna `31513`.

O exportador inclui status diferentes de `pendente`. Como o ciclo está concluído, o leitor busca o snapshot final pelo evento vigente e pelo ID do aluno; a presença da matrícula remanejada sem esse snapshot causa a falha.

## Critério do desvio

O script move somente alunos da turma `1645` que:

1. estejam com status `transferido` ou `remanejado`;
2. pertençam ao ciclo concluído da avaliação `3`;
3. não tenham snapshot em `avaliacao_aluno_snapshots` para `snapshot_evento_atual_id` do ciclo.

No backup atual, esse critério retorna somente a aluna `31513`.

## Ordem de execução

1. Faça backup do banco.
2. Execute `01-diagnostico.sql` e confirme os resultados.
3. Execute `02-desviar.sql` e confira a lista antes do `COMMIT`.
4. Execute `03-conferencia.sql`.
5. Exporte o parecer da turma original pelo sistema.
6. Execute `04-restaurar.sql`.
7. Execute `05-limpar-temporaria.sql` somente quando a turma estiver vazia.

Os arquivos usam `USE \`gestao-edu\``. Ajuste somente se o nome do banco no servidor for diferente.

## Riscos e limitações

- Os IDs foram confirmados no backup informado; valide novamente se o banco do servidor não for exatamente esse backup.
- O script não altera status, histórico, snapshots, respostas ou ciclos.
- O desvio é temporário. Após restaurar a aluna, uma nova exportação desse mesmo ciclo poderá voltar a encontrar a mesma inconsistência.
- A limpeza não remove turma com alunos. Foreign keys de outras tabelas também podem impedir a remoção.
- Não desbloqueie nem reabra o ciclo concluído para resolver esse erro.
