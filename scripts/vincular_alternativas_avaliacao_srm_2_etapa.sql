-- Vincula, como override por pauta, as alternativas permitidas para a avaliacao:
-- "2ª Etapa | Parecer do 2ª Trimestre - Sala de Recursos Multifuncionais"
--
-- Regras aplicadas a todas as pautas vinculadas a essa avaliacao:
--   1. todas as alternativas ativas do tipo "Parecer";
--   2. somente a alternativa ativa "Não se Aplica" do tipo "SRM - 2ª Etapa".
--
-- O script e idempotente: vinculos existentes nao sao duplicados.
-- Nao remove overrides nem respostas existentes.

START TRANSACTION;

INSERT INTO `avaliacao_pauta_alternativa` (
    `avaliacao_id`,
    `pauta_id`,
    `alternativa_id`,
    `created_at`,
    `updated_at`
)
SELECT
    avaliacao.`id`,
    avaliacao_pauta.`pauta_id`,
    alternativa.`id`,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
FROM `avaliacoes` AS avaliacao
INNER JOIN `avaliacao_pauta`
    ON `avaliacao_pauta`.`avaliacao_id` = avaliacao.`id`
INNER JOIN `alternativas` AS alternativa
    ON alternativa.`status` = 1
INNER JOIN `tipos_avaliacao` AS tipo_alternativa
    ON tipo_alternativa.`id` = alternativa.`tipo_avaliacao_id`
   AND tipo_alternativa.`status` = 1
WHERE avaliacao.`id` = 5
  AND avaliacao.`nome` = '2ª Etapa | Parecer do 2ª Trimestre - Sala de Recursos Multifuncionais'
  AND (
      tipo_alternativa.`nome` = 'Parecer'
      OR (
          tipo_alternativa.`nome` = 'SRM - 2ª Etapa'
          AND alternativa.`nome` = 'Não se Aplica'
      )
  )
ON DUPLICATE KEY UPDATE
    `updated_at` = `avaliacao_pauta_alternativa`.`updated_at`;

COMMIT;

-- Conferencia: no dump analisado, o resultado esperado e:
--   32 pautas, 9 alternativas por pauta e 288 vinculos no total.
SELECT
    COUNT(DISTINCT apa.`pauta_id`) AS `total_pautas`,
    COUNT(DISTINCT apa.`alternativa_id`) AS `total_alternativas`,
    COUNT(*) AS `total_vinculos`
FROM `avaliacao_pauta_alternativa` AS apa
WHERE apa.`avaliacao_id` = 5;

SELECT
    apa.`pauta_id`,
    tipo_alternativa.`nome` AS `tipo_alternativa`,
    alternativa.`nome` AS `alternativa`
FROM `avaliacao_pauta_alternativa` AS apa
INNER JOIN `alternativas` AS alternativa
    ON alternativa.`id` = apa.`alternativa_id`
INNER JOIN `tipos_avaliacao` AS tipo_alternativa
    ON tipo_alternativa.`id` = alternativa.`tipo_avaliacao_id`
WHERE apa.`avaliacao_id` = 5
ORDER BY apa.`pauta_id`, tipo_alternativa.`nome`, alternativa.`nome`;
