-- Mantem na tabela servidores apenas registros com vinculo ativo de:
-- - Coordenacao Pedagogica
-- - Direcao
--
-- Importante:
-- - Este script executa DELETE somente em servidores.
-- - Pelas FKs atuais do banco, ao deletar um servidor:
--   - professores nao sao deletados; professores.servidor_id fica NULL.
--   - users nao sao deletados.
--   - servidor_funcao_administrativa e servidor_funcao_turma sao removidas por ON DELETE CASCADE.
--
-- Regra aplicada:
-- - Mantem o servidor se existir ao menos um vinculo ativo em
--   servidor_funcao_administrativa com funcao marcada como
--   direcao_escolar = 1 ou coordenacao_pedagogica = 1.
-- - O filtro por nome fica como fallback para bancos em que os flags estejam
--   inconsistentes, mas as funcoes existam com os nomes esperados.

SET NAMES utf8mb4;

-- 1) Conferir quais funcoes serao consideradas validas.
SELECT
    fa.id,
    fa.nome,
    fa.ativo,
    fa.direcao_escolar,
    fa.coordenacao_pedagogica
FROM funcao_administrativa fa
WHERE
    fa.ativo = 1
    AND (
        fa.direcao_escolar = 1
        OR fa.coordenacao_pedagogica = 1
        OR fa.nome IN ('Coordenação Pedagógica', 'Direção')
    )
ORDER BY fa.nome;

-- 2) Diagnostico antes da exclusao.
SELECT
    COUNT(*) AS total_servidores,
    SUM(
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM servidor_funcao_administrativa sfa
                INNER JOIN funcao_administrativa fa
                    ON fa.id = sfa.funcao_administrativa_id
                WHERE
                    sfa.servidor_id = s.id
                    AND sfa.status = 'ativo'
                    AND fa.ativo = 1
                    AND (
                        fa.direcao_escolar = 1
                        OR fa.coordenacao_pedagogica = 1
                        OR fa.nome IN ('Coordenação Pedagógica', 'Direção')
                    )
            )
            THEN 1
            ELSE 0
        END
    ) AS servidores_mantidos,
    SUM(
        CASE
            WHEN NOT EXISTS (
                SELECT 1
                FROM servidor_funcao_administrativa sfa
                INNER JOIN funcao_administrativa fa
                    ON fa.id = sfa.funcao_administrativa_id
                WHERE
                    sfa.servidor_id = s.id
                    AND sfa.status = 'ativo'
                    AND fa.ativo = 1
                    AND (
                        fa.direcao_escolar = 1
                        OR fa.coordenacao_pedagogica = 1
                        OR fa.nome IN ('Coordenação Pedagógica', 'Direção')
                    )
            )
            THEN 1
            ELSE 0
        END
    ) AS servidores_a_excluir
FROM servidores s;

-- 3) Lista nominal dos servidores que serao excluidos.
SELECT
    s.id,
    s.nome,
    s.matricula,
    s.email,
    s.id_escola,
    s.setor_id,
    GROUP_CONCAT(
        DISTINCT CONCAT(fa.nome, ' [', COALESCE(sfa.status, 'sem status'), ']')
        ORDER BY fa.nome
        SEPARATOR ', '
    ) AS funcoes_encontradas
FROM servidores s
LEFT JOIN servidor_funcao_administrativa sfa
    ON sfa.servidor_id = s.id
LEFT JOIN funcao_administrativa fa
    ON fa.id = sfa.funcao_administrativa_id
WHERE NOT EXISTS (
    SELECT 1
    FROM servidor_funcao_administrativa sfa_keep
    INNER JOIN funcao_administrativa fa_keep
        ON fa_keep.id = sfa_keep.funcao_administrativa_id
    WHERE
        sfa_keep.servidor_id = s.id
        AND sfa_keep.status = 'ativo'
        AND fa_keep.ativo = 1
        AND (
            fa_keep.direcao_escolar = 1
            OR fa_keep.coordenacao_pedagogica = 1
            OR fa_keep.nome IN ('Coordenação Pedagógica', 'Direção')
        )
)
GROUP BY
    s.id,
    s.nome,
    s.matricula,
    s.email,
    s.id_escola,
    s.setor_id
ORDER BY s.nome, s.id;

START TRANSACTION;

SET @professores_antes = (SELECT COUNT(*) FROM professores);
SET @usuarios_antes = (SELECT COUNT(*) FROM users);

-- 4) Exclusao restrita a servidores que nao possuem os vinculos permitidos.
DELETE s
FROM servidores s
WHERE NOT EXISTS (
    SELECT 1
    FROM servidor_funcao_administrativa sfa
    INNER JOIN funcao_administrativa fa
        ON fa.id = sfa.funcao_administrativa_id
    WHERE
        sfa.servidor_id = s.id
        AND sfa.status = 'ativo'
        AND fa.ativo = 1
        AND (
            fa.direcao_escolar = 1
            OR fa.coordenacao_pedagogica = 1
            OR fa.nome IN ('Coordenação Pedagógica', 'Direção')
        )
);

SELECT ROW_COUNT() AS servidores_excluidos;

-- 5) Validacao depois da exclusao.
-- O campo servidores_fora_das_funcoes_permitidas deve retornar 0.
SELECT
    COUNT(*) AS servidores_fora_das_funcoes_permitidas
FROM servidores s
WHERE NOT EXISTS (
    SELECT 1
    FROM servidor_funcao_administrativa sfa
    INNER JOIN funcao_administrativa fa
        ON fa.id = sfa.funcao_administrativa_id
    WHERE
        sfa.servidor_id = s.id
        AND sfa.status = 'ativo'
        AND fa.ativo = 1
        AND (
            fa.direcao_escolar = 1
            OR fa.coordenacao_pedagogica = 1
            OR fa.nome IN ('Coordenação Pedagógica', 'Direção')
        )
);

SELECT
    @professores_antes AS professores_antes,
    (SELECT COUNT(*) FROM professores) AS professores_depois,
    @usuarios_antes AS usuarios_antes,
    (SELECT COUNT(*) FROM users) AS usuarios_depois;

SELECT
    COUNT(*) AS professores_com_servidor_id_nulo
FROM professores
WHERE servidor_id IS NULL;

COMMIT;
