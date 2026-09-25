-- Conferência do desvio temporário — Escola Tempo Integral
-- Avaliação: 3
-- SOMENTE LEITURA.
-- Execute depois de 02-desviar-transferidos.sql e antes da exportação.

USE `gestao-edu`;

SET @avaliacao_id := 3;
SET @escola_id := 69;

-- Todos os alunos atualmente presentes nas turmas temporárias.
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
    CASE WHEN aluno.status = 'transferido' THEN 'OK' ELSE 'REVISAR' END AS status_conferencia,
    CASE WHEN snapshot.id IS NULL THEN 'SEM_SNAPSHOT_FINAL' ELSE 'COM_SNAPSHOT_FINAL' END AS snapshot_conferencia
FROM alunos AS aluno
INNER JOIN turmas AS temporaria
    ON temporaria.id = aluno.id_turma
INNER JOIN turmas AS origem
    ON temporaria.codigo = CONCAT('TMP-TRANSFERIDOS-', origem.id)
   AND temporaria.id_escola = origem.id_escola
INNER JOIN avaliacao_turma_ciclos AS ciclo
    ON ciclo.avaliacao_id = @avaliacao_id
   AND ciclo.turma_avaliativa_id = origem.id
   AND ciclo.turma_origem_id = origem.id
LEFT JOIN avaliacao_aluno_snapshots AS snapshot
    ON snapshot.evento_id = ciclo.snapshot_evento_atual_id
   AND snapshot.aluno_id = aluno.id
WHERE temporaria.codigo LIKE 'TMP-TRANSFERIDOS-%'
  AND temporaria.id_escola = @escola_id
ORDER BY temporaria.id, aluno.nome;

-- Resumo das turmas temporárias e garantia de que não estão vazias.
SELECT
    temporaria.id AS turma_temporaria_id,
    temporaria.codigo,
    temporaria.nome,
    temporaria.turno,
    temporaria.id_serie,
    temporaria.id_escola,
    COUNT(aluno.id) AS quantidade_alunos,
    SUM(aluno.status = 'transferido') AS quantidade_transferidos,
    SUM(snapshot.id IS NULL) AS quantidade_sem_snapshot_final
FROM turmas AS temporaria
LEFT JOIN alunos AS aluno
    ON aluno.id_turma = temporaria.id
LEFT JOIN turmas AS origem
    ON temporaria.codigo = CONCAT('TMP-TRANSFERIDOS-', origem.id)
   AND temporaria.id_escola = origem.id_escola
LEFT JOIN avaliacao_turma_ciclos AS ciclo
    ON ciclo.avaliacao_id = @avaliacao_id
   AND ciclo.turma_avaliativa_id = origem.id
   AND ciclo.turma_origem_id = origem.id
LEFT JOIN avaliacao_aluno_snapshots AS snapshot
    ON snapshot.evento_id = ciclo.snapshot_evento_atual_id
   AND snapshot.aluno_id = aluno.id
WHERE temporaria.codigo LIKE 'TMP-TRANSFERIDOS-%'
  AND temporaria.id_escola = @escola_id
GROUP BY temporaria.id, temporaria.codigo, temporaria.nome,
         temporaria.turno, temporaria.id_serie, temporaria.id_escola
ORDER BY temporaria.id;
