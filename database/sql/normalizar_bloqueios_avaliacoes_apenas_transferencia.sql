-- Normaliza bloqueios de avaliacoes para manter bloqueada = 1 apenas em transferencia.
--
-- Regra aplicada:
-- - bloqueio_tipo = 'remanejamento' vira NULL.
-- - registros com bloqueio_tipo = 'transferencia' ficam com bloqueada = 1.
-- - qualquer outro bloqueio_tipo, inclusive NULL, fica com bloqueada = 0.
--
-- Tabelas afetadas:
-- - avaliacao_respostas
-- - avaliacao_informacoes_complementares
--
-- Importante:
-- - O valor correto usado pelo sistema e 'transferencia'.
-- - Nao use 'tranferencia' ou 'trancerencia' no filtro.

-- 1) Numeros antes da correcao.
SELECT
    'ANTES' AS etapa,
    'avaliacao_respostas' AS tabela,
    COUNT(*) AS total_registros,
    SUM(CASE WHEN bloqueio_tipo = 'remanejamento' THEN 1 ELSE 0 END) AS remanejamento,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' THEN 1 ELSE 0 END) AS transferencia,
    SUM(CASE WHEN bloqueada = 1 THEN 1 ELSE 0 END) AS total_bloqueados,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' AND bloqueada = 1 THEN 1 ELSE 0 END) AS transferencias_bloqueadas,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' AND COALESCE(bloqueada, 0) = 0 THEN 1 ELSE 0 END) AS transferencias_desbloqueadas,
    SUM(CASE WHEN bloqueada = 1 AND (bloqueio_tipo IS NULL OR bloqueio_tipo <> 'transferencia') THEN 1 ELSE 0 END) AS bloqueados_nao_transferencia,
    SUM(CASE WHEN bloqueio_tipo IS NULL THEN 1 ELSE 0 END) AS sem_bloqueio_tipo
FROM avaliacao_respostas
UNION ALL
SELECT
    'ANTES' AS etapa,
    'avaliacao_informacoes_complementares' AS tabela,
    COUNT(*) AS total_registros,
    SUM(CASE WHEN bloqueio_tipo = 'remanejamento' THEN 1 ELSE 0 END) AS remanejamento,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' THEN 1 ELSE 0 END) AS transferencia,
    SUM(CASE WHEN bloqueada = 1 THEN 1 ELSE 0 END) AS total_bloqueados,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' AND bloqueada = 1 THEN 1 ELSE 0 END) AS transferencias_bloqueadas,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' AND COALESCE(bloqueada, 0) = 0 THEN 1 ELSE 0 END) AS transferencias_desbloqueadas,
    SUM(CASE WHEN bloqueada = 1 AND (bloqueio_tipo IS NULL OR bloqueio_tipo <> 'transferencia') THEN 1 ELSE 0 END) AS bloqueados_nao_transferencia,
    SUM(CASE WHEN bloqueio_tipo IS NULL THEN 1 ELSE 0 END) AS sem_bloqueio_tipo
FROM avaliacao_informacoes_complementares;

-- Detalhe por tipo antes da correcao.
SELECT
    'ANTES_DETALHE' AS etapa,
    'avaliacao_respostas' AS tabela,
    COALESCE(bloqueio_tipo, 'NULL') AS bloqueio_tipo,
    bloqueada,
    COUNT(*) AS total
FROM avaliacao_respostas
GROUP BY COALESCE(bloqueio_tipo, 'NULL'), bloqueada
UNION ALL
SELECT
    'ANTES_DETALHE' AS etapa,
    'avaliacao_informacoes_complementares' AS tabela,
    COALESCE(bloqueio_tipo, 'NULL') AS bloqueio_tipo,
    bloqueada,
    COUNT(*) AS total
FROM avaliacao_informacoes_complementares
GROUP BY COALESCE(bloqueio_tipo, 'NULL'), bloqueada
ORDER BY tabela, bloqueio_tipo, bloqueada;

START TRANSACTION;

SET @agora = CURRENT_TIMESTAMP;

-- 2) Limpa o tipo remanejamento.
UPDATE avaliacao_respostas
SET
    bloqueio_tipo = NULL,
    updated_at = @agora
WHERE bloqueio_tipo = 'remanejamento';

SELECT ROW_COUNT() AS respostas_remanejamento_convertidas_para_null;

UPDATE avaliacao_informacoes_complementares
SET
    bloqueio_tipo = NULL,
    updated_at = @agora
WHERE bloqueio_tipo = 'remanejamento';

SELECT ROW_COUNT() AS informacoes_remanejamento_convertidas_para_null;

-- 3) Garante que apenas transferencia fique bloqueada.
UPDATE avaliacao_respostas
SET
    bloqueada = CASE WHEN bloqueio_tipo = 'transferencia' THEN 1 ELSE 0 END,
    updated_at = @agora
WHERE
    (bloqueio_tipo = 'transferencia' AND COALESCE(bloqueada, 0) <> 1)
    OR ((bloqueio_tipo IS NULL OR bloqueio_tipo <> 'transferencia') AND COALESCE(bloqueada, 0) <> 0);

SELECT ROW_COUNT() AS respostas_bloqueada_normalizadas;

UPDATE avaliacao_informacoes_complementares
SET
    bloqueada = CASE WHEN bloqueio_tipo = 'transferencia' THEN 1 ELSE 0 END,
    updated_at = @agora
WHERE
    (bloqueio_tipo = 'transferencia' AND COALESCE(bloqueada, 0) <> 1)
    OR ((bloqueio_tipo IS NULL OR bloqueio_tipo <> 'transferencia') AND COALESCE(bloqueada, 0) <> 0);

SELECT ROW_COUNT() AS informacoes_bloqueada_normalizadas;

-- 4) Numeros depois da correcao. Esperado:
-- - remanejamento = 0
-- - transferencias_desbloqueadas = 0
-- - bloqueados_nao_transferencia = 0
SELECT
    'DEPOIS' AS etapa,
    'avaliacao_respostas' AS tabela,
    COUNT(*) AS total_registros,
    SUM(CASE WHEN bloqueio_tipo = 'remanejamento' THEN 1 ELSE 0 END) AS remanejamento,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' THEN 1 ELSE 0 END) AS transferencia,
    SUM(CASE WHEN bloqueada = 1 THEN 1 ELSE 0 END) AS total_bloqueados,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' AND bloqueada = 1 THEN 1 ELSE 0 END) AS transferencias_bloqueadas,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' AND COALESCE(bloqueada, 0) = 0 THEN 1 ELSE 0 END) AS transferencias_desbloqueadas,
    SUM(CASE WHEN bloqueada = 1 AND (bloqueio_tipo IS NULL OR bloqueio_tipo <> 'transferencia') THEN 1 ELSE 0 END) AS bloqueados_nao_transferencia,
    SUM(CASE WHEN bloqueio_tipo IS NULL THEN 1 ELSE 0 END) AS sem_bloqueio_tipo
FROM avaliacao_respostas
UNION ALL
SELECT
    'DEPOIS' AS etapa,
    'avaliacao_informacoes_complementares' AS tabela,
    COUNT(*) AS total_registros,
    SUM(CASE WHEN bloqueio_tipo = 'remanejamento' THEN 1 ELSE 0 END) AS remanejamento,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' THEN 1 ELSE 0 END) AS transferencia,
    SUM(CASE WHEN bloqueada = 1 THEN 1 ELSE 0 END) AS total_bloqueados,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' AND bloqueada = 1 THEN 1 ELSE 0 END) AS transferencias_bloqueadas,
    SUM(CASE WHEN bloqueio_tipo = 'transferencia' AND COALESCE(bloqueada, 0) = 0 THEN 1 ELSE 0 END) AS transferencias_desbloqueadas,
    SUM(CASE WHEN bloqueada = 1 AND (bloqueio_tipo IS NULL OR bloqueio_tipo <> 'transferencia') THEN 1 ELSE 0 END) AS bloqueados_nao_transferencia,
    SUM(CASE WHEN bloqueio_tipo IS NULL THEN 1 ELSE 0 END) AS sem_bloqueio_tipo
FROM avaliacao_informacoes_complementares;

-- Detalhe por tipo depois da correcao.
SELECT
    'DEPOIS_DETALHE' AS etapa,
    'avaliacao_respostas' AS tabela,
    COALESCE(bloqueio_tipo, 'NULL') AS bloqueio_tipo,
    bloqueada,
    COUNT(*) AS total
FROM avaliacao_respostas
GROUP BY COALESCE(bloqueio_tipo, 'NULL'), bloqueada
UNION ALL
SELECT
    'DEPOIS_DETALHE' AS etapa,
    'avaliacao_informacoes_complementares' AS tabela,
    COALESCE(bloqueio_tipo, 'NULL') AS bloqueio_tipo,
    bloqueada,
    COUNT(*) AS total
FROM avaliacao_informacoes_complementares
GROUP BY COALESCE(bloqueio_tipo, 'NULL'), bloqueada
ORDER BY tabela, bloqueio_tipo, bloqueada;

COMMIT;
