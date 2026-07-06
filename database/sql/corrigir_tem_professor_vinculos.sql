-- Corrige inconsistencias em turma_componente_professor.tem_professor.
--
-- Regra atual do sistema:
-- - professor_id preenchido  => tem_professor = 1
-- - professor_id nulo        => tem_professor = 0
--
-- Impacto:
-- - A tela "Minhas Avaliacoes" usa turma_componente_professor.tem_professor = 1
--   como fonte de verdade do vinculo pedagogico do professor.
-- - Este script nao altera avaliacoes, respostas, pautas, turmas, componentes
--   nem professores. Ele apenas alinha o indicador booleano ao professor_id.

-- 1) Diagnostico antes da correcao.
SELECT
    SUM(CASE WHEN professor_id IS NOT NULL AND tem_professor = 0 THEN 1 ELSE 0 END)
        AS com_professor_marcado_sem_professor,
    SUM(CASE WHEN professor_id IS NULL AND tem_professor = 1 THEN 1 ELSE 0 END)
        AS sem_professor_marcado_com_professor
FROM turma_componente_professor;

SELECT
    tcp.id,
    tcp.turma_id,
    t.nome AS turma,
    s.nome AS serie,
    e.nome AS escola,
    tcp.componente_curricular_id,
    c.nome AS componente,
    tcp.professor_id,
    p.nome AS professor,
    p.ativo AS professor_ativo,
    p.user_id,
    tcp.tem_professor
FROM turma_componente_professor tcp
LEFT JOIN turmas t ON t.id = tcp.turma_id
LEFT JOIN series s ON s.id = t.id_serie
LEFT JOIN escolas e ON e.id = t.id_escola
LEFT JOIN componentes_curriculares c ON c.id = tcp.componente_curricular_id
LEFT JOIN professores p ON p.id = tcp.professor_id
WHERE
    (tcp.professor_id IS NOT NULL AND tcp.tem_professor = 0)
    OR (tcp.professor_id IS NULL AND tcp.tem_professor = 1)
ORDER BY e.nome, s.nome, t.nome, c.nome, p.nome, tcp.id;

START TRANSACTION;

-- 2) Correcao do indicador.
UPDATE turma_componente_professor
SET
    tem_professor = CASE WHEN professor_id IS NULL THEN 0 ELSE 1 END,
    updated_at = NOW()
WHERE
    (professor_id IS NOT NULL AND tem_professor = 0)
    OR (professor_id IS NULL AND tem_professor = 1);

SELECT ROW_COUNT() AS vinculos_corrigidos;

-- 3) Ressincronizacao nao destrutiva das escolas pedagogicas dos usuarios.
-- Nao remove vinculos existentes em escola_user, apenas garante que usuarios
-- de professores ativos tenham as escolas das turmas em que lecionam.
INSERT INTO escola_user (user_id, escola_id, created_at, updated_at)
SELECT DISTINCT
    p.user_id,
    t.id_escola,
    NOW(),
    NOW()
FROM turma_componente_professor tcp
INNER JOIN professores p ON p.id = tcp.professor_id
INNER JOIN turmas t ON t.id = tcp.turma_id
WHERE
    tcp.tem_professor = 1
    AND p.ativo = 1
    AND p.user_id IS NOT NULL
    AND t.id_escola IS NOT NULL
ON DUPLICATE KEY UPDATE
    updated_at = VALUES(updated_at);

SELECT ROW_COUNT() AS vinculos_escola_user_inseridos_ou_atualizados;

-- 4) Validacao apos a correcao. Deve retornar zero nos dois campos.
SELECT
    SUM(CASE WHEN professor_id IS NOT NULL AND tem_professor = 0 THEN 1 ELSE 0 END)
        AS com_professor_marcado_sem_professor,
    SUM(CASE WHEN professor_id IS NULL AND tem_professor = 1 THEN 1 ELSE 0 END)
        AS sem_professor_marcado_com_professor
FROM turma_componente_professor;

COMMIT;
