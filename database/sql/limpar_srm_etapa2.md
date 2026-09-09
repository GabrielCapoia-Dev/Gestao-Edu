# Limpeza SRM — etapa 2

Arquivo de execução: `limpar_srm_etapa2.sql`. Os arquivos Python são ferramentas de auditoria/teste, não são executados no phpMyAdmin.

## Escopo

Revisado sobre `Downloads/gestao-edu (1).sql`, após o COMMIT da consolidação e a tentativa cancelada da limpeza. A seleção usa os IDs arquivados pela etapa 1; não é uma exclusão global por status, nome ou CGM.

Neste backup, a resposta 224179 (aluno 33677, pauta 208) permaneceu no ciclo 244, embora o aluno já estivesse na nova turma. Não existe resposta equivalente no ciclo de destino 616. A etapa 2 agora arquiva essa linha e transfere seu ciclo, token e referências de turma antes da limpeza. Preserva ID, aluno, pauta, alternativa, professor, observação e todas as datas; incrementa apenas `version` além das referências transferidas. A conferência compara a linha completa antes das exclusões e antes do COMMIT.

Essa realocação exige aluno matriculado, destino mapeado aberto/dinâmico/inicializado com token e ausência de resposta para o mesmo aluno/pauta no destino. Conflito (mesmo com conteúdo igual), destino concluído ou aluno pendente interrompem a transação. Não há sobrescrita nem deduplicação automática. Respostas pertencentes aos remanejados continuam protegidas contra exclusão automática.

- Remove 202 linhas com status `remanejado` das 153 turmas antigas mapeadas, depois de confirmar matrícula atual SRM na mesma escola. Uma matrícula histórica mudou de turno anteriormente: a conferência não exige que o turno histórico seja o atual.
- Remove as 153 turmas antigas e seus ciclos vazios, tokens, vínculos antigos e resumo derivado da conclusão antiga. Não remove as avaliações nem seus cadastros de pautas/alternativas.
- Preserva as 36 turmas novas, inclusive as duas turmas de manhã sem alunos, e todos os seus vínculos atuais de professor/componente.
- Preserva integralmente os 408 alunos atuais, incluindo os dois pendentes, seus IDs, status, respostas, autores e informações complementares. Não altera o vínculo principal regular nem os remanejados fora da consolidação SRM.
- Corrige `turma_id` de 35 documentos atuais para o destino mapeado, sem modificar payload, autores, versão ou conteúdo. Excluir as turmas sem essa correção apagaria os documentos por cascata.
- Consolida 43 vínculos administrativos antigos em 30 vínculos no destino. Mantém status/datas/principal; não ativa funções inativas nem substitui professores já vinculados.
- Preserva os snapshots e seus payloads/hashes, além dos eventos e logs de exportação. Os ponteiros relacionais para turmas/ciclos excluídos ficam nulos. Não vincula a antiga conclusão do Malba ao novo ciclo. O resumo derivado antigo fica arquivado na auditoria; as respostas do snapshot continuam no histórico e já estão operacionais nos alunos atuais.

No backup auditado, os 202 remanejados não possuem respostas, documentos ou dependências avaliativas próprias. Se essas dependências aparecerem antes da execução, o script cancela a transação em vez de eliminar conteúdo novo sem revisão. Também cancela se houver pendência ligada a um remanejado, aluno atual na origem, alteração de schema/FKs ou falta de correspondência da matrícula atual.

## Execução e recuperação

1. Mantenha um backup completo imediatamente anterior à execução. Não exclua as tabelas `srm_20260909_*`.
2. Execute com usuários e fila sem gravar no banco; o script não coloca o aplicativo em manutenção por conta própria. O bloqueio consultivo coordena apenas os scripts SRM.
3. No banco correto, execute **todo** `limpar_srm_etapa2.sql` no phpMyAdmin. É necessária permissão para criar tabelas e rotinas.
4. Sucesso retorna `COMMIT realizado - limpeza SRM etapa 2`. Em erro, a transação é desfeita e o motivo é exibido. Não ignore o erro nem desligue `FOREIGN_KEY_CHECKS`.

As linhas removidas/alteradas são arquivadas em `srm2_20260909_*`. Não há descarte dessas tabelas. Depois do COMMIT não existe ROLLBACK simples: a recuperação requer restauração assistida a partir da auditoria ou do backup, considerando eventuais respostas posteriores. A restauração não está automatizada neste arquivo. Tabelas de auditoria vazias podem permanecer após uma falha, pois sua criação ocorre antes da transação. Reexecutar uma etapa já concluída não repete as exclusões.

## Validação

`validar_limpeza_srm_etapa2.py` lê o backup em memória e executa os mesmos SELECTs de proteção e comandos DML do SQL, adaptando o dialeto para SQLite. Compara todos os IDs/conteúdos carregados, verifica as FKs disponíveis, preservação de documentos/snapshots, reexecução e rollback nos cenários de conflito. No novo backup, confirmou 7.088 respostas e 12 informações arquivadas na etapa 1, além da realocação da resposta residual, sem descarte. Testa também resposta já existente no destino, destino concluído e aluno pendente.

Limitação: esse teste não executa a rotina em MySQL, nem valida locks, permissões, INFORMATION_SCHEMA ou o comportamento do phpMyAdmin. Nenhum banco real foi alterado na preparação.
