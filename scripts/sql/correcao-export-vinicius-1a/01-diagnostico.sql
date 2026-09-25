-- Diagnóstico temporário da exportação de pareceres
-- Escola Vinicius de Morais — 1º Ano A — avaliação 3
--
-- SOMENTE LEITURA. Não altera dados.
-- O dump analisado confirmou escola 70, turma 1645 e avaliação 3.
-- O critério inclui transferido/remanejado porque o exportador inclui
-- qualquer aluno com status diferente de `pendente`.

USE `gestao-edu`;

SET @avaliacao_id := 3;
SET @escola_id := 70;
SET @turma_id := 1645;

SELECT
    escola.nome AS escola,
    turma.id AS turma_id,
    turma.nome AS turma,
    serie.nome AS serie,
    aluno.id AS aluno_id,
    aluno.nome AS aluno,
    aluno.cgm,
    aluno.status,
    ciclo.id AS ciclo_id,
    ciclo.status AS ciclo_status,
    ciclo.roster_mode,
    ciclo.snapshot_evento_atual_id,
    CASE
        WHEN snapshot.id IS NULL THEN 'SEM_SNAPSHOT_FINAL'
        ELSE 'COM_SNAPSHOT_FINAL'
    END AS situacao_snapshot,
    snapshot.tipo AS snapshot_tipo
FROM avaliacao_turma_ciclos AS ciclo
INNER JOIN turmas AS turma
    ON turma.id = ciclo.turma_avaliativa_id
INNER JOIN escolas AS escola
    ON escola.id = turma.id_escola
INNER JOIN series AS serie
    ON serie.id = turma.id_serie
INNER JOIN alunos AS aluno
    ON aluno.id_turma = ciclo.turma_origem_id
LEFT JOIN avaliacao_aluno_snapshots AS snapshot
    ON snapshot.evento_id = ciclo.snapshot_evento_atual_id
   AND snapshot.aluno_id = aluno.id
WHERE ciclo.avaliacao_id = @avaliacao_id
  AND ciclo.turma_avaliativa_id = @turma_id
  AND turma.id_escola = @escola_id
ORDER BY aluno.nome;

-- Resumo por status. No dump, o caso problemático é remanejado,
-- não transferido.
SELECT
    aluno.status,
    COUNT(*) AS quantidade_alunos,
    SUM(snapshot.id IS NULL) AS sem_snapshot_final,
    GROUP_CONCAT(
        CASE WHEN snapshot.id IS NULL THEN CONCAT(aluno.id, ' - ', aluno.nome) END
        ORDER BY aluno.nome SEPARATOR '\n'
    ) AS alunos_sem_snapshot
FROM avaliacao_turma_ciclos AS ciclo
INNER JOIN turmas AS turma
    ON turma.id = ciclo.turma_avaliativa_id
INNER JOIN alunos AS aluno
    ON aluno.id_turma = ciclo.turma_origem_id
LEFT JOIN avaliacao_aluno_snapshots AS snapshot
    ON snapshot.evento_id = ciclo.snapshot_evento_atual_id
   AND snapshot.aluno_id = aluno.id
WHERE ciclo.avaliacao_id = @avaliacao_id
  AND ciclo.turma_avaliativa_id = @turma_id
  AND turma.id_escola = @escola_id
GROUP BY aluno.status
ORDER BY aluno.status;
