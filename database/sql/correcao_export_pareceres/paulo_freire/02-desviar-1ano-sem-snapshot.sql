-- CORREÇÃO TEMPORÁRIA — ESCOLA PAULO FREIRE (ID 62) — 1º ANO
--
-- Pré-requisitos:
--   * Fazer backup antes da execução.
--   * Executar no banco MySQL da aplicação, nunca no arquivo de dump.
--   * Confirmar que a exportação desejada é a avaliação 3.
--
-- Critério: aluno principal em turma do 1º Ano da escola 62, com status
-- diferente de 'pendente', pertencente a ciclo concluído da avaliação 3 e
-- sem snapshot 'conclusao' correspondente ao evento atual do ciclo.
-- O critério segue o exportador e não depende de uma lista fixa de IDs.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS _correcao_pf_1ano_alunos;
CREATE TEMPORARY TABLE _correcao_pf_1ano_alunos (
    aluno_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    turma_original_id BIGINT UNSIGNED NOT NULL
) ENGINE = InnoDB;

INSERT INTO _correcao_pf_1ano_alunos (aluno_id, turma_original_id)
SELECT a.id, a.id_turma
FROM alunos AS a
INNER JOIN turmas AS t ON t.id = a.id_turma
WHERE t.id_escola = 62
  AND t.id_serie = 5 -- 1º Ano
  AND a.tipo_vinculo = 'principal'
  AND a.status <> 'pendente'
  AND EXISTS (
      SELECT 1
      FROM avaliacao_turma_ciclos AS c
      WHERE c.avaliacao_id = 3
        AND c.turma_origem_id = a.id_turma
        AND c.status = 'concluida'
        AND c.snapshot_evento_atual_id IS NOT NULL
  )
  AND NOT EXISTS (
      SELECT 1
      FROM avaliacao_turma_ciclos AS c
      INNER JOIN avaliacao_aluno_snapshots AS s
          ON s.evento_id = c.snapshot_evento_atual_id
         AND s.avaliacao_id = c.avaliacao_id
         AND s.ciclo_id = c.id
         AND s.turma_origem_id = c.turma_origem_id
         AND s.aluno_id = a.id
         AND s.tipo = 'conclusao'
      WHERE c.avaliacao_id = 3
        AND c.turma_origem_id = a.id_turma
        AND c.status = 'concluida'
        AND c.snapshot_evento_atual_id IS NOT NULL
  );

SELECT
    p.aluno_id,
    a.nome,
    a.status,
    p.turma_original_id,
    t.codigo AS codigo_turma_original,
    CONCAT('TMP-TRANSFERIDOS-', p.turma_original_id) AS codigo_turma_temporaria
FROM _correcao_pf_1ano_alunos AS p
INNER JOIN alunos AS a ON a.id = p.aluno_id
INNER JOIN turmas AS t ON t.id = p.turma_original_id
ORDER BY p.turma_original_id, a.nome;

INSERT INTO turmas (
    codigo, nome, turno, id_serie, id_escola, created_at, updated_at
)
SELECT
    CONCAT('TMP-TRANSFERIDOS-', t.id),
    CONCAT('TEMPORÁRIA - TRANSFERIDOS - ', t.codigo),
    t.turno,
    t.id_serie,
    t.id_escola,
    NOW(),
    NOW()
FROM turmas AS t
INNER JOIN (
    SELECT DISTINCT turma_original_id
    FROM _correcao_pf_1ano_alunos
) AS p ON p.turma_original_id = t.id
WHERE NOT EXISTS (
    SELECT 1
    FROM turmas AS existente
    WHERE existente.codigo = CONCAT('TMP-TRANSFERIDOS-', t.id)
      AND existente.id_escola = t.id_escola
);

UPDATE alunos AS a
INNER JOIN _correcao_pf_1ano_alunos AS p ON p.aluno_id = a.id
INNER JOIN turmas AS tmp
    ON tmp.codigo = CONCAT('TMP-TRANSFERIDOS-', p.turma_original_id)
   AND tmp.id_escola = 62
SET a.id_turma = tmp.id,
    a.updated_at = NOW();

SELECT ROW_COUNT() AS quantidade_alunos_desviados;

SELECT
    a.id AS aluno_id,
    a.nome,
    a.status,
    a.id_turma AS turma_temporaria_id,
    tmp.codigo AS turma_temporaria,
    p.turma_original_id
FROM _correcao_pf_1ano_alunos AS p
INNER JOIN alunos AS a ON a.id = p.aluno_id
INNER JOIN turmas AS tmp ON tmp.id = a.id_turma
WHERE tmp.codigo = CONCAT('TMP-TRANSFERIDOS-', p.turma_original_id)
ORDER BY tmp.codigo, a.nome;

COMMIT;

-- A turma temporária permanece para conferência e restauração.
