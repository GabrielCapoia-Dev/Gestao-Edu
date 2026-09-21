-- CORREÇÃO TEMPORÁRIA DE EXPORTAÇÃO DE PARECERES
-- Remove somente turmas vazias criadas por esta frente.
-- Pré-requisito: executar o arquivo 04 e confirmar que não há alunos restantes.
-- Backup é obrigatório antes desta operação destrutiva.

START TRANSACTION;

-- Prévia: mostra candidatas vazias e referências que impedem uma remoção segura.
SELECT
    tmp.id,
    tmp.codigo,
    tmp.nome,
    COUNT(DISTINCT a.id) AS alunos,
    CASE
        WHEN EXISTS (
            SELECT 1 FROM turma_componente_professor tcp WHERE tcp.turma_id = tmp.id
        ) THEN 'BLOQUEADA: componentes/professores'
        WHEN EXISTS (
            SELECT 1 FROM professor_turma pt WHERE pt.turma_id = tmp.id
        ) THEN 'BLOQUEADA: professor_turma'
        WHEN EXISTS (
            SELECT 1 FROM professor_funcao_turma pft WHERE pft.turma_id = tmp.id
        ) THEN 'BLOQUEADA: professor_funcao_turma'
        WHEN EXISTS (
            SELECT 1 FROM avaliacao_turma at WHERE at.turma_id = tmp.id
        ) THEN 'BLOQUEADA: avaliacao_turma'
        WHEN EXISTS (
            SELECT 1
            FROM avaliacao_turma_ciclos c
            WHERE c.turma_avaliativa_id = tmp.id
               OR c.turma_origem_id = tmp.id
        ) THEN 'BLOQUEADA: ciclo avaliativo'
        WHEN EXISTS (
            SELECT 1
            FROM avaliacao_respostas_operacionais aro
            WHERE aro.turma_avaliativa_id = tmp.id
               OR aro.turma_origem_id = tmp.id
        ) THEN 'BLOQUEADA: respostas operacionais'
        WHEN EXISTS (
            SELECT 1
            FROM avaliacao_informacoes_operacionais aio
            WHERE aio.turma_avaliativa_id = tmp.id
               OR aio.turma_origem_id = tmp.id
        ) THEN 'BLOQUEADA: informações operacionais'
        WHEN EXISTS (
            SELECT 1
            FROM evento_calendario_escola_turma ecet
            WHERE ecet.turma_id = tmp.id
        ) THEN 'BLOQUEADA: evento escolar'
        WHEN EXISTS (
            SELECT 1
            FROM evento_transporte_alocacao_turma etat
            WHERE etat.turma_id = tmp.id
        ) THEN 'BLOQUEADA: transporte'
        WHEN COUNT(a.id) > 0 THEN 'BLOQUEADA: possui alunos'
        ELSE 'ELEGÍVEL PARA REMOÇÃO'
    END AS resultado
FROM turmas AS tmp
LEFT JOIN alunos AS a ON a.id_turma = tmp.id
WHERE tmp.codigo LIKE 'TMP-TRANSFERIDOS-%'
GROUP BY tmp.id, tmp.codigo, tmp.nome
ORDER BY tmp.codigo;

DELETE FROM turmas
WHERE codigo LIKE 'TMP-TRANSFERIDOS-%'
  AND NOT EXISTS (
      SELECT 1 FROM alunos AS a WHERE a.id_turma = turmas.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM turma_componente_professor tcp WHERE tcp.turma_id = turmas.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM professor_turma pt WHERE pt.turma_id = turmas.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM professor_funcao_turma pft WHERE pft.turma_id = turmas.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM avaliacao_turma at WHERE at.turma_id = turmas.id
  )
  AND NOT EXISTS (
      SELECT 1
      FROM avaliacao_turma_ciclos c
      WHERE c.turma_avaliativa_id = turmas.id
         OR c.turma_origem_id = turmas.id
  )
  AND NOT EXISTS (
      SELECT 1
      FROM avaliacao_respostas_operacionais aro
      WHERE aro.turma_avaliativa_id = turmas.id
         OR aro.turma_origem_id = turmas.id
  )
  AND NOT EXISTS (
      SELECT 1
      FROM avaliacao_informacoes_operacionais aio
      WHERE aio.turma_avaliativa_id = turmas.id
         OR aio.turma_origem_id = turmas.id
  )
  AND NOT EXISTS (
      SELECT 1
      FROM evento_calendario_escola_turma ecet
      WHERE ecet.turma_id = turmas.id
  )
  AND NOT EXISTS (
      SELECT 1
      FROM evento_transporte_alocacao_turma etat
      WHERE etat.turma_id = turmas.id
  );

SET @turmas_removidas := ROW_COUNT();

SELECT @turmas_removidas AS turmas_temporarias_removidas;

-- Deve retornar zero linhas quando toda a limpeza foi concluída.
SELECT
    tmp.id,
    tmp.codigo,
    tmp.nome,
    COUNT(a.id) AS alunos_restantes
FROM turmas AS tmp
LEFT JOIN alunos AS a ON a.id_turma = tmp.id
WHERE tmp.codigo LIKE 'TMP-TRANSFERIDOS-%'
GROUP BY tmp.id, tmp.codigo, tmp.nome
ORDER BY tmp.codigo;

COMMIT;
