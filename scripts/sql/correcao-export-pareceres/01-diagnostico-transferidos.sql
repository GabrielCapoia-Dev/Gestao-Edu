-- Correção temporária da exportação de pareceres — Escola Tempo Integral
-- Avaliação: 3
--
-- SOMENTE LEITURA.
-- Este arquivo não altera alunos, turmas, avaliações ou snapshots.
-- Execute após selecionar o banco correto no phpMyAdmin.
-- O dump analisado contém o banco `gestao-edu`; ajuste o USE se o nome
-- do banco no servidor for diferente.

USE `gestao-edu`;

SET @avaliacao_id := 3;
SET @escola_id := 69;

-- Turmas confirmadas no dump de 23/09/2026:
-- 1616 = 1º Ano Integral A
-- 1617 = 2º Ano Integral A
-- 1618 = 2º Ano Integral B
-- A consulta usa o ciclo congelado e o evento do snapshot final vigente.
SELECT
    ciclo.avaliacao_id,
    turma.id AS turma_original_id,
    escola.nome AS escola,
    serie.nome AS serie,
    turma.nome AS turma,
    turma.turno,
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
INNER JOIN avaliacao_turma AS avaliacao_turma
    ON avaliacao_turma.avaliacao_id = ciclo.avaliacao_id
   AND avaliacao_turma.turma_id = ciclo.turma_avaliativa_id
INNER JOIN turmas AS turma
    ON turma.id = ciclo.turma_avaliativa_id
INNER JOIN escolas AS escola
    ON escola.id = turma.id_escola
INNER JOIN series AS serie
    ON serie.id = turma.id_serie
INNER JOIN alunos AS aluno
    ON aluno.id_turma = ciclo.turma_origem_id
   AND aluno.status = 'transferido'
LEFT JOIN avaliacao_aluno_snapshots AS snapshot
    ON snapshot.evento_id = ciclo.snapshot_evento_atual_id
   AND snapshot.aluno_id = aluno.id
WHERE ciclo.avaliacao_id = @avaliacao_id
  AND ciclo.turma_avaliativa_id IN (1616, 1617, 1618)
  AND turma.id_escola = @escola_id
ORDER BY turma.id, aluno.nome;

-- Resumo: somente alunos transferidos sem o snapshot final vigente são
-- candidatos ao desvio temporário.
SELECT
    turma.id AS turma_original_id,
    turma.nome AS turma,
    COUNT(aluno.id) AS transferidos,
    SUM(snapshot.id IS NOT NULL) AS com_snapshot_final,
    SUM(snapshot.id IS NULL) AS sem_snapshot_final,
    GROUP_CONCAT(
        CASE WHEN snapshot.id IS NULL THEN CONCAT(aluno.id, ' - ', aluno.nome) END
        ORDER BY aluno.nome SEPARATOR '\n'
    ) AS alunos_problematicos
FROM avaliacao_turma_ciclos AS ciclo
INNER JOIN turmas AS turma
    ON turma.id = ciclo.turma_avaliativa_id
INNER JOIN alunos AS aluno
    ON aluno.id_turma = ciclo.turma_origem_id
   AND aluno.status = 'transferido'
LEFT JOIN avaliacao_aluno_snapshots AS snapshot
    ON snapshot.evento_id = ciclo.snapshot_evento_atual_id
   AND snapshot.aluno_id = aluno.id
WHERE ciclo.avaliacao_id = @avaliacao_id
  AND ciclo.turma_avaliativa_id IN (1616, 1617, 1618)
  AND turma.id_escola = @escola_id
GROUP BY turma.id, turma.nome
ORDER BY turma.id;
