-- Limpeza das turmas temporárias — Escola Tempo Integral
--
-- Execute somente depois de 04-restaurar-transferidos.sql.
-- A exclusão é restrita aos códigos conhecidos e ocorre somente quando não
-- existe nenhum aluno vinculado. Foreign keys de outras tabelas podem impedir
-- a exclusão; nesse caso, preserve a turma e faça a análise manual.

USE `gestao-edu`;

SET @escola_id := 69;

START TRANSACTION;

-- Resultado antes da limpeza.
SELECT
    temporaria.id,
    temporaria.codigo,
    temporaria.nome,
    COUNT(aluno.id) AS quantidade_alunos,
    CASE WHEN COUNT(aluno.id) = 0 THEN 'ELEGIVEL_PARA_LIMPEZA' ELSE 'NAO_REMOVER' END AS situacao
FROM turmas AS temporaria
LEFT JOIN alunos AS aluno
    ON aluno.id_turma = temporaria.id
WHERE temporaria.codigo IN (
    'TMP-TRANSFERIDOS-1616',
    'TMP-TRANSFERIDOS-1617',
    'TMP-TRANSFERIDOS-1618'
)
  AND temporaria.id_escola = @escola_id
GROUP BY temporaria.id, temporaria.codigo, temporaria.nome
ORDER BY temporaria.id;

DELETE temporaria
FROM turmas AS temporaria
WHERE temporaria.codigo IN (
    'TMP-TRANSFERIDOS-1616',
    'TMP-TRANSFERIDOS-1617',
    'TMP-TRANSFERIDOS-1618'
)
  AND temporaria.id_escola = @escola_id
  AND NOT EXISTS (
      SELECT 1
      FROM alunos AS aluno
      WHERE aluno.id_turma = temporaria.id
  );

SET @turmas_removidas := ROW_COUNT();

SELECT @turmas_removidas AS turmas_temporarias_removidas;

-- Resultado depois da limpeza.
SELECT
    temporaria.id,
    temporaria.codigo,
    temporaria.nome,
    COUNT(aluno.id) AS quantidade_alunos
FROM turmas AS temporaria
LEFT JOIN alunos AS aluno
    ON aluno.id_turma = temporaria.id
WHERE temporaria.codigo IN (
    'TMP-TRANSFERIDOS-1616',
    'TMP-TRANSFERIDOS-1617',
    'TMP-TRANSFERIDOS-1618'
)
  AND temporaria.id_escola = @escola_id
GROUP BY temporaria.id, temporaria.codigo, temporaria.nome
ORDER BY temporaria.id;

COMMIT;
