# Correção temporária de exportação de pareceres

Scripts manuais para retirar temporariamente do roster de uma turma os alunos com status `transferido` que não possuem o snapshot final exigido pelo exportador relacional.

## Escopo confirmado no código

O exportador resolve a turma avaliativa e seleciona os alunos pela `turma_origem_id` do ciclo. Ele exclui apenas alunos com status `pendente`, portanto `transferido` é processado. Em ciclo `concluida`, o leitor busca `avaliacao_aluno_snapshots` pelo `snapshot_evento_atual_id` e pelo `aluno_id`; a ausência dessa linha produz `Snapshot final do aluno não encontrado.`

O critério usado pelos scripts é exatamente:

- ciclo em `avaliacao_turma_ciclos` para a avaliação e turma avaliativa informadas;
- `ciclo.status = 'concluida'` e `snapshot_evento_atual_id` preenchido;
- aluno na `ciclo.turma_origem_id` com `alunos.status = 'transferido'`;
- nenhuma linha em `avaliacao_aluno_snapshots` com o evento atual e o aluno.

Não há filtro artificial por `tipo_vinculo`, porque o código atual do exportador não usa esse campo para montar o roster.

## Schema e colunas utilizadas

- `avaliacoes.id`, `avaliacoes.nome`;
- `avaliacao_turma_ciclos.id`, `avaliacao_id`, `status`, `turma_avaliativa_id`, `turma_origem_id`, `snapshot_evento_atual_id`;
- `avaliacao_snapshot_eventos.id`, `tipo`, `publicado_em`;
- `avaliacao_aluno_snapshots.id`, `evento_id`, `aluno_id`;
- `alunos.id`, `nome`, `cgm`, `status`, `tipo_vinculo`, `id_turma`, `updated_at`;
- `turmas.id`, `codigo`, `nome`, `turno`, `id_serie`, `id_escola`, `created_at`, `updated_at`.

As constraints confirmadas incluem FK de `alunos.id_turma` para `turmas.id` e FKs restritivas da estrutura avaliativa para turmas. Por isso a turma temporária não recebe avaliação, componentes, professores ou snapshots.

## Execução manual

1. Faça backup do banco.
2. Abra `01-diagnostico-transferidos.sql`, configure `@avaliacao_id` e `@turma_avaliativa_id` e execute somente as consultas.
3. Revise o ciclo, a turma de origem e cada aluno marcado como `PROBLEMÁTICO`.
4. Configure as mesmas variáveis em `02-desviar-transferidos.sql` e execute o script inteiro em uma sessão MySQL/phpMyAdmin.
5. Execute `03-conferencia-desvio.sql` com as mesmas variáveis. Só prossiga se todas as linhas relevantes estiverem `OK`.
6. Faça a exportação da turma original pela aplicação enquanto os alunos estiverem na turma temporária.
7. Depois de concluir e conferir a exportação, execute `04-restaurar-transferidos.sql`. Ele deriva a turma original do código `TMP-TRANSFERIDOS-{ID}` e não usa uma lista fixa de alunos.
8. Confirme a restauração e execute `05-limpar-turmas-temporarias.sql`. A limpeza remove apenas turmas vazias sem referências conhecidas; qualquer bloqueio permanece para revisão manual.

Os arquivos 01 e 03 são somente leitura. Os arquivos 02, 04 e 05 usam transação. Não feche a sessão antes do `COMMIT` e não execute o arquivo 05 antes do 04.

## Riscos e limitações

- A movimentação altera `alunos.id_turma`, mesmo sem alterar status ou histórico; não exporte a turma original em paralelo durante o desvio.
- O código da turma temporária é a relação usada para restaurar: `TMP-TRANSFERIDOS-{ID_DA_TURMA_ORIGINAL}`. Não reutilize esse padrão para outro fim.
- Se a mesma turma de origem for usada por mais de uma avaliação concluída, os alunos sem snapshot ficam agrupados na mesma turma temporária. Conclua as exportações necessárias antes de restaurar.
- O script não cria nem corrige snapshots e não resolve outros motivos de falha do exportador.
- A análise foi feita no código e nas migrations versionadas. Nenhum SQL foi executado no servidor, conforme solicitado; antes da execução manual, valide que o banco está no schema correspondente às migrations atuais.
- Não foram alterados models, services, Livewire, Filament, migrations ou código da aplicação.
