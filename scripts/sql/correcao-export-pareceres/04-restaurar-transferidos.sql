-- Restauração do desvio temporário — Escola Tempo Integral
--
-- Pré-requisito: executar após terminar todas as exportações necessárias e
-- conferir 03-conferencia-desvio.sql.
-- A restauração não usa lista fixa de alunos: relaciona cada turma
-- temporária ao ID da turma original pelo código TMP-TRANSFERIDOS-{ID}.
-- Não altera status, snapshots, respostas ou ciclos.

USE `gestao-edu`;

SET @escola_id := 69;

START TRANSACTION;

UPDATE alunos AS aluno
INNER JOIN turmas AS temporaria
    ON temporaria.id = aluno.id_turma
INNER JOIN turmas AS origem
    ON temporaria.codigo = CONCAT('TMP-TRANSFERIDOS-', origem.id)
   AND temporaria.id_escola = origem.id_escola
SET aluno.id_turma = origem.id,
    aluno.updated_at = NOW()
WHERE temporaria.codigo LIKE 'TMP-TRANSFERIDOS-%'
  AND temporaria.id_escola = @escola_id
  AND origem.id IN (1616, 1617, 1618)
  AND origem.id_escola = @escola_id;

SET @alunos_restaurados := ROW_COUNT();

SELECT @alunos_restaurados AS alunos_restaurados;

-- Conferência dentro da transação: as turmas temporárias devem ficar vazias.
SELECT
    temporaria.id AS turma_temporaria_id,
    temporaria.codigo AS turma_temporaria,
    origem.id AS turma_original_id,
    origem.nome AS turma_original,
    COUNT(aluno.id) AS alunos_restantes
FROM turmas AS temporaria
INNER JOIN turmas AS origem
    ON temporaria.codigo = CONCAT('TMP-TRANSFERIDOS-', origem.id)
   AND temporaria.id_escola = origem.id_escola
LEFT JOIN alunos AS aluno
    ON aluno.id_turma = temporaria.id
WHERE temporaria.codigo LIKE 'TMP-TRANSFERIDOS-%'
  AND temporaria.id_escola = @escola_id
  AND origem.id IN (1616, 1617, 1618)
GROUP BY temporaria.id, temporaria.codigo, origem.id, origem.nome
ORDER BY temporaria.id;

COMMIT;
