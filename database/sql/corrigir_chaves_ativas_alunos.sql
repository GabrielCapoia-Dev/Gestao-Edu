-- Corrige colunas derivadas de unicidade da tabela alunos.
--
-- Compatível com a execução direta no phpMyAdmin.
-- Não usa PROCEDURE, DELIMITER, DECLARE, IF ou SIGNAL.
-- Não altera alunos, CGM, turmas, status, vínculos ou avaliações.
-- Faça backup antes de executar no banco de produção.
--
-- Se houver duplicidades semânticas, a tabela temporária receberá conflitos
-- e os dois UPDATEs serão ignorados automaticamente.

DROP TEMPORARY TABLE IF EXISTS tmp_conflitos_chaves_ativas_alunos;

CREATE TEMPORARY TABLE tmp_conflitos_chaves_ativas_alunos (
    tipo VARCHAR(64) NOT NULL,
    chave VARCHAR(255) NOT NULL
);

INSERT INTO tmp_conflitos_chaves_ativas_alunos (tipo, chave)
SELECT 'principal_matriculado_global', TRIM(a.cgm)
FROM alunos a
WHERE a.tipo_vinculo = 'principal'
  AND a.status = 'matriculado'
  AND NULLIF(TRIM(a.cgm), '') IS NOT NULL
GROUP BY TRIM(a.cgm)
HAVING COUNT(*) > 1;

INSERT INTO tmp_conflitos_chaves_ativas_alunos (tipo, chave)
SELECT 'principal_por_unidade', CONCAT(t.id_escola, '|', TRIM(a.cgm))
FROM alunos a
INNER JOIN turmas t ON t.id = a.id_turma
WHERE a.tipo_vinculo = 'principal'
  AND a.status IN ('matriculado', 'pendente')
  AND NULLIF(TRIM(a.cgm), '') IS NOT NULL
GROUP BY CONCAT(t.id_escola, '|', TRIM(a.cgm))
HAVING COUNT(*) > 1;

INSERT INTO tmp_conflitos_chaves_ativas_alunos (tipo, chave)
SELECT 'contra_turno_matriculado', TRIM(a.cgm)
FROM alunos a
WHERE a.tipo_vinculo = 'contra_turno'
  AND a.status = 'matriculado'
  AND NULLIF(TRIM(a.cgm), '') IS NOT NULL
GROUP BY TRIM(a.cgm)
HAVING COUNT(*) > 1;

-- Verifique este resultado antes de continuar.
SELECT *
FROM tmp_conflitos_chaves_ativas_alunos
ORDER BY tipo, chave;

START TRANSACTION;

-- Libera primeiro as chaves antigas para evitar colisão durante a
-- repopulação, mantendo todas as demais colunas intactas.
UPDATE alunos
SET
    cgm_matricula_ativa = NULL,
    cgm_contra_turno_ativo = NULL,
    cgm_unidade_matricula_ativa = NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM tmp_conflitos_chaves_ativas_alunos
);

-- Reconstrói as chaves a partir do estado canônico.
UPDATE alunos a
LEFT JOIN turmas t ON t.id = a.id_turma
SET
    a.cgm_matricula_ativa = CASE
        WHEN a.tipo_vinculo = 'principal'
         AND a.status = 'matriculado'
        THEN NULLIF(TRIM(a.cgm), '')
        ELSE NULL
    END,
    a.cgm_contra_turno_ativo = CASE
        WHEN a.tipo_vinculo = 'contra_turno'
         AND a.status = 'matriculado'
        THEN NULLIF(TRIM(a.cgm), '')
        ELSE NULL
    END,
    a.cgm_unidade_matricula_ativa = CASE
        WHEN a.tipo_vinculo = 'principal'
         AND a.status IN ('matriculado', 'pendente')
         AND t.id_escola IS NOT NULL
        THEN CONCAT(t.id_escola, '|', NULLIF(TRIM(a.cgm), ''))
        ELSE NULL
    END
WHERE NOT EXISTS (
    SELECT 1
    FROM tmp_conflitos_chaves_ativas_alunos
);

COMMIT;

-- Deve retornar 0 quando a correção foi aplicada sem inconsistências.
SELECT COUNT(*) AS chaves_inconsistentes_restantes
FROM alunos a
LEFT JOIN turmas t ON t.id = a.id_turma
WHERE (a.cgm_matricula_ativa IS NOT NULL AND NOT (
           a.tipo_vinculo = 'principal'
       AND a.status = 'matriculado'
       AND NULLIF(TRIM(a.cgm), '') IS NOT NULL
       AND a.cgm_matricula_ativa = NULLIF(TRIM(a.cgm), '')
    ))
   OR (a.cgm_contra_turno_ativo IS NOT NULL AND NOT (
           a.tipo_vinculo = 'contra_turno'
       AND a.status = 'matriculado'
       AND NULLIF(TRIM(a.cgm), '') IS NOT NULL
       AND a.cgm_contra_turno_ativo = NULLIF(TRIM(a.cgm), '')
    ))
   OR (a.cgm_unidade_matricula_ativa IS NOT NULL AND NOT (
           a.tipo_vinculo = 'principal'
       AND a.status IN ('matriculado', 'pendente')
       AND NULLIF(TRIM(a.cgm), '') IS NOT NULL
       AND t.id_escola IS NOT NULL
       AND a.cgm_unidade_matricula_ativa = CONCAT(t.id_escola, '|', NULLIF(TRIM(a.cgm), ''))
    ));

SELECT *
FROM tmp_conflitos_chaves_ativas_alunos
ORDER BY tipo, chave;

DROP TEMPORARY TABLE IF EXISTS tmp_conflitos_chaves_ativas_alunos;
