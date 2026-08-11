-- Auditoria somente leitura da sincronizacao de pautas e fatos da avaliacao.
SET @avaliacao_alvo_id := 3;
SET SESSION group_concat_max_len = 10000;

SELECT
    a.id AS avaliacao_id,
    a.nome AS avaliacao,
    a.status AS avaliacao_status,
    c.status AS consolidacao_status,
    c.consolidada_em,
    c.erro AS consolidacao_erro
FROM avaliacoes a
LEFT JOIN avaliacao_dashboard_consolidacoes c
  ON c.avaliacao_id = a.id
WHERE a.id = @avaliacao_alvo_id;

SELECT
    (SELECT COUNT(*)
       FROM avaliacao_pauta ap
       JOIN pautas p ON p.id = ap.pauta_id AND p.status = 1
      WHERE ap.avaliacao_id = @avaliacao_alvo_id) AS pautas_ativas_vinculadas,
    (SELECT COUNT(*)
       FROM avaliacao_dashboard_fatos f
      WHERE f.avaliacao_id = @avaliacao_alvo_id) AS fatos_total,
    (SELECT COUNT(*)
       FROM avaliacao_dashboard_fatos f
      WHERE f.avaliacao_id = @avaliacao_alvo_id
        AND f.respondida = 1) AS fatos_respondidos,
    (SELECT COALESCE(SUM(JSON_LENGTH(JSON_EXTRACT(d.payload, '$.pautas'))), 0)
       FROM avaliacao_aluno_documentos d
      WHERE d.avaliacao_id = @avaliacao_alvo_id) AS respostas_nos_documentos,
    (SELECT COUNT(*)
       FROM (
           SELECT DISTINCT f.serie_id, f.componente_curricular_id
           FROM avaliacao_dashboard_fatos f
           WHERE f.avaliacao_id = @avaliacao_alvo_id
       ) pares) AS pares_serie_componente_com_fatos;

SELECT
    s.nome AS serie,
    COUNT(DISTINCT f.componente_curricular_id) AS total_componentes,
    GROUP_CONCAT(
        DISTINCT c.nome
        ORDER BY c.nome
        SEPARATOR ', '
    ) AS componentes,
    COUNT(*) AS fatos,
    SUM(f.respondida = 1) AS fatos_respondidos
FROM avaliacao_dashboard_fatos f
JOIN series s ON s.id = f.serie_id
JOIN componentes_curriculares c ON c.id = f.componente_curricular_id
WHERE f.avaliacao_id = @avaliacao_alvo_id
GROUP BY s.id, s.nome
ORDER BY s.nome;

SELECT COUNT(*) AS pautas_curriculares_ainda_faltantes
FROM pautas p
JOIN avaliacao_serie avs
  ON avs.avaliacao_id = @avaliacao_alvo_id
 AND avs.serie_id = p.serie_id
JOIN avaliacao_componente avc
  ON avc.avaliacao_id = @avaliacao_alvo_id
 AND avc.componente_curricular_id = p.componente_curricular_id
JOIN serie_componente_curricular scc
  ON scc.serie_id = p.serie_id
 AND scc.componente_curricular_id = p.componente_curricular_id
WHERE p.tipo_avaliacao_id = (
        SELECT a.tipo_avaliacao_id
        FROM avaliacoes a
        WHERE a.id = @avaliacao_alvo_id
    )
  AND p.status = 1
  AND NOT EXISTS (
      SELECT 1
      FROM avaliacao_pauta ap
      WHERE ap.avaliacao_id = @avaliacao_alvo_id
        AND ap.pauta_id = p.id
  );
