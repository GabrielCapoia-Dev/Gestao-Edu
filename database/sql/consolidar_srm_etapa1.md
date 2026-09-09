# Consolidação SRM — etapa 1

Execute somente `consolidar_srm_etapa1.sql`, inteiro, no phpMyAdmin do banco correto.
Requer MySQL 8, permissão de criar tabelas/rotinas e tabelas transacionais InnoDB.
Faça backup completo atualizado antes e execute sem professores, importações ou filas gravando.
O arquivo não inicia serviços e não executa alterações por conta própria.

## Comportamento

- Cria Manhã e Tarde para cada escola com vínculo SRM matriculado ou pendente.
- Preserva o ID, CGM, tipo de vínculo e status de cada aluno; atualiza sua turma na mesma escola, série e turno.
- Mantém os registros remanejados/históricos e as turmas/vínculos de origem.
- Replica os vínculos de professor por componente. Grupos sem professor permanecem assim.
- Quando dois cadastros usam o mesmo usuário, seleciona o menor ID ativo para o vínculo de destino, sem alterar autoria nem cadastro. No backup, Ouro Branco/Manhã usa 1937; o cadastro 3284 continua existindo.
- Cria ciclos abertos e dinâmicos no destino para as avaliações das turmas originais do mesmo turno. Os dois destinos sem turma de origem/avaliação continuam vazios.
- Move as respostas operacionais mantendo os IDs e conteúdos; copia as respostas do JSON canônico ainda não migrado e do snapshot atual de conclusão.
- Preserva integralmente documentos JSON e snapshots originais. Documentos obsoletos, inclusive de outras avaliações, não são promovidos ao fluxo operacional.
- Marca as origens migradas como inicializadas. Reabre a origem antes concluída, agora sem alunos ativos, para não somar novamente seu fechamento no progresso. O snapshot e seu evento permanecem intactos; a continuidade ocorre no destino.
- Não altera datas de preenchimento. Se a avaliação estava encerrada, passa a ativa para comportar os novos ciclos abertos.

## Conferência

O resultado precisa mostrar `COMMIT realizado - consolidacao SRM etapa 1` e os totais.
Em seguida, o arquivo mostra escola, turno, turma nova, alunos e professor.
Os totais são calculados no banco durante a execução; não dependem do backup antigo.

Na simulação offline com o backup de 09/09: 408 vínculos movidos, 36 turmas novas,
34 novos ciclos, 4.767 respostas operacionais preservadas, 1.360 respostas recuperadas
de JSON/snapshot, 6 informações complementares preservadas e 202 vínculos históricos
mantidos na origem. Os 2 snapshots SRM permaneceram intactos.

As seleções SQL foram exercitadas em SQLite; os UPDATEs e a expansão de JSON foram
simulados em memória. Foram verificados conteúdos, IDs, escopo, ausência de duplicação
do progresso, repetição sem alterações e rollback de casos conflitantes.
A procedure completa ainda não foi executada em MySQL neste ambiente.

As tabelas `srm_20260909_*` guardam cópias e os mapas de origem/destino. Inclua todas
no próximo backup e não as exclua. Um segundo processamento do mesmo arquivo, depois
do sucesso, informa que a etapa já foi concluída e não repete a migração.

Em caso de erro, a transação de dados é desfeita; as tabelas de auditoria criadas
antes dela podem permanecer vazias. Envie o erro completo para conferência.
Após o sucesso, envie o backup atualizado para preparar a etapa 2. Não exclua
manualmente turmas, alunos remanejados, snapshots ou respostas antigas.
