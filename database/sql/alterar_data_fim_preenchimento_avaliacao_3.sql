-- Altera somente o prazo de preenchimento da avaliação 3.
-- Observação: setembro não possui dia 31; a data válida equivalente é 30/09/2026.

START TRANSACTION;

SET @avaliacao_id := 3;
SET @data_fim_atual_esperada := '2026-09-04';
SET @nova_data_fim_preenchimento := '2026-09-30';

-- Confere o registro esperado antes da alteração. Se esta consulta não
-- retornar exatamente uma linha, não prossiga com a execução.
SELECT id, nome, data_fim_preenchimento
FROM avaliacoes
WHERE id = @avaliacao_id
  AND data_fim_preenchimento = @data_fim_atual_esperada
FOR UPDATE;

UPDATE avaliacoes
SET data_fim_preenchimento = @nova_data_fim_preenchimento,
    updated_at = CURRENT_TIMESTAMP
WHERE id = @avaliacao_id
  AND data_fim_preenchimento = @data_fim_atual_esperada;

-- Conferência do resultado antes do commit.
SELECT
    id,
    nome,
    data_inicio_preenchimento,
    data_fim_preenchimento,
    updated_at
FROM avaliacoes
WHERE id = @avaliacao_id;

COMMIT;
