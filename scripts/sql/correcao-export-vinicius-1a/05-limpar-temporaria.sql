-- Limpeza da turma temporária
-- Execute somente depois de 04-restaurar.sql.
-- Nunca remove a turma se houver aluno vinculado.

USE `gestao-edu`;

SET @escola_id := 70;
SET @turma_id := 1645;

START TRANSACTION;

SELECT
    temporaria.id,
    temporaria.codigo,
    temporaria.nome,
    COUNT(aluno.id) AS quantidade_alunos,
    CASE WHEN COUNT(aluno.id) = 0 THEN 'ELEGIVEL_PARA_LIMPEZA' ELSE 'NAO_REMOVER' END AS situacao
FROM turmas AS temporaria
LEFT JOIN alunos AS aluno
    ON aluno.id_turma = temporaria.id
WHERE temporaria.id_escola = @escola_id
  AND temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', @turma_id)
GROUP BY temporaria.id, temporaria.codigo, temporaria.nome;

DELETE temporaria
FROM turmas AS temporaria
WHERE temporaria.id_escola = @escola_id
  AND temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', @turma_id)
  AND NOT EXISTS (
      SELECT 1 FROM alunos AS aluno WHERE aluno.id_turma = temporaria.id
  );

SELECT ROW_COUNT() AS turmas_temporarias_removidas;

COMMIT;
