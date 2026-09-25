-- Conferência do desvio temporário
-- SOMENTE LEITURA. Execute após 02-desviar.sql e antes da exportação.

USE `gestao-edu`;

SET @avaliacao_id := 3;
SET @escola_id := 70;
SET @turma_id := 1645;

SELECT
    aluno.id AS aluno_id,
    aluno.nome AS aluno,
    aluno.cgm,
    aluno.status,
    temporaria.id AS turma_temporaria_id,
    temporaria.codigo AS turma_temporaria,
    origem.id AS turma_original_id,
    origem.nome AS turma_original,
    ciclo.id AS ciclo_id,
    ciclo.snapshot_evento_atual_id,
    CASE WHEN aluno.status IN ('transferido', 'remanejado') THEN 'OK' ELSE 'REVISAR' END AS status_conferencia,
    CASE WHEN snapshot.id IS NULL THEN 'SEM_SNAPSHOT_FINAL' ELSE 'COM_SNAPSHOT_FINAL' END AS snapshot_conferencia
FROM alunos AS aluno
INNER JOIN turmas AS temporaria
    ON temporaria.id = aluno.id_turma
INNER JOIN turmas AS origem
    ON temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', origem.id)
   AND temporaria.id_escola = origem.id_escola
INNER JOIN avaliacao_turma_ciclos AS ciclo
    ON ciclo.avaliacao_id = @avaliacao_id
   AND ciclo.turma_avaliativa_id = origem.id
   AND ciclo.turma_origem_id = origem.id
LEFT JOIN avaliacao_aluno_snapshots AS snapshot
    ON snapshot.evento_id = ciclo.snapshot_evento_atual_id
   AND snapshot.aluno_id = aluno.id
WHERE temporaria.id_escola = @escola_id
  AND origem.id = @turma_id
ORDER BY aluno.nome;

SELECT
    temporaria.id AS turma_temporaria_id,
    temporaria.codigo,
    temporaria.nome,
    COUNT(aluno.id) AS quantidade_alunos,
    SUM(aluno.status IN ('transferido', 'remanejado')) AS quantidade_movidos
FROM turmas AS temporaria
LEFT JOIN alunos AS aluno
    ON aluno.id_turma = temporaria.id
WHERE temporaria.id_escola = @escola_id
  AND temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', @turma_id)
GROUP BY temporaria.id, temporaria.codigo, temporaria.nome;
