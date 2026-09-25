-- Restauração do desvio temporário
-- Execute após concluir a exportação do parecer.
-- Não depende de uma lista fixa de alunos: usa a relação entre o código da
-- turma temporária e o ID da turma original.

USE `gestao-edu`;

SET @escola_id := 70;
SET @turma_id := 1645;

START TRANSACTION;

UPDATE alunos AS aluno
INNER JOIN turmas AS temporaria
    ON temporaria.id = aluno.id_turma
INNER JOIN turmas AS origem
    ON temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', origem.id)
   AND temporaria.id_escola = origem.id_escola
SET aluno.id_turma = origem.id,
    aluno.updated_at = NOW()
WHERE temporaria.id_escola = @escola_id
  AND temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', @turma_id)
  AND origem.id = @turma_id
  AND origem.id_escola = @escola_id;

SET @alunos_restaurados := ROW_COUNT();

SELECT @alunos_restaurados AS alunos_restaurados;

SELECT
    temporaria.id AS turma_temporaria_id,
    temporaria.codigo AS turma_temporaria,
    COUNT(aluno.id) AS alunos_restantes
FROM turmas AS temporaria
LEFT JOIN alunos AS aluno
    ON aluno.id_turma = temporaria.id
WHERE temporaria.id_escola = @escola_id
  AND temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', @turma_id)
GROUP BY temporaria.id, temporaria.codigo;

COMMIT;
