-- CORREÇÃO TEMPORÁRIA DE EXPORTAÇÃO DE PARECERES
-- Restaura alunos transferidos de qualquer turma temporária desta frente.
-- A relação turma temporária -> turma original está no código:
-- TMP-TRANSFERIDOS-{ID_DA_TURMA_ORIGINAL}.
-- Pré-requisitos: a exportação já terminou e o resultado foi conferido.

START TRANSACTION;

-- Mostra exatamente os candidatos antes da alteração.
SELECT
    a.id AS aluno_id,
    a.nome AS aluno,
    a.cgm,
    a.status,
    tmp.id AS turma_temporaria_id,
    tmp.codigo AS turma_temporaria_codigo,
    origem.id AS turma_original_id,
    origem.codigo AS turma_original_codigo,
    origem.nome AS turma_original
FROM alunos AS a
INNER JOIN turmas AS tmp
    ON tmp.id = a.id_turma
INNER JOIN turmas AS origem
    ON origem.id = CAST(
        SUBSTRING(tmp.codigo, CHAR_LENGTH('TMP-TRANSFERIDOS-') + 1)
        AS UNSIGNED
    )
WHERE tmp.codigo LIKE 'TMP-TRANSFERIDOS-%'
  AND a.status = 'transferido'
  AND tmp.id <> origem.id
  AND tmp.id_escola = origem.id_escola
  AND tmp.id_serie = origem.id_serie
  AND tmp.turno = origem.turno
ORDER BY tmp.codigo, a.nome, a.id;

UPDATE alunos AS a
INNER JOIN turmas AS tmp
    ON tmp.id = a.id_turma
INNER JOIN turmas AS origem
    ON origem.id = CAST(
        SUBSTRING(tmp.codigo, CHAR_LENGTH('TMP-TRANSFERIDOS-') + 1)
        AS UNSIGNED
    )
SET a.id_turma = origem.id,
    a.updated_at = NOW()
WHERE tmp.codigo LIKE 'TMP-TRANSFERIDOS-%'
  AND a.status = 'transferido'
  AND tmp.id <> origem.id
  AND tmp.id_escola = origem.id_escola
  AND tmp.id_serie = origem.id_serie
  AND tmp.turno = origem.turno;

SET @restaurados := ROW_COUNT();

SELECT @restaurados AS alunos_restaurados;

COMMIT;
