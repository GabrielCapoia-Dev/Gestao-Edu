-- Recuperacao aditiva da avaliacao 4 de Sala de Recursos Multifuncionais.
-- Compatibilidade: MySQL 8.0+.
--
-- O dump de 14/08/2026 possui 246 matriculas SRM de contra turno ativas,
-- mas nenhum fato para a avaliacao 4. Este script vincula o escopo faltante
-- e materializa os alunos no dashboard sem duplicar registros em alunos.
-- Documentos, respostas e historicos existentes sao preservados.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @avaliacao_id := 4;

ROLLBACK;

SET @avaliacao_matches := (
    SELECT COUNT(*)
    FROM avaliacoes
    WHERE id = @avaliacao_id
      AND nome LIKE '%Sala de Recursos Multifuncionais%'
);
SET @srm_serie_matches := (
    SELECT COUNT(*)
    FROM series
    WHERE codigo = 'srm_serie'
);
SET @srm_serie_id := (
    SELECT MIN(id)
    FROM series
    WHERE codigo = 'srm_serie'
);
SET @escolas_selecionadas := (
    SELECT COUNT(*)
    FROM avaliacao_escola
    WHERE avaliacao_id = @avaliacao_id
);
SET @componentes_selecionados := (
    SELECT COUNT(*)
    FROM avaliacao_componente
    WHERE avaliacao_id = @avaliacao_id
);
SET @total_erros_guard :=
      IF(@avaliacao_matches = 1, 0, 1)
    + IF(@srm_serie_matches = 1, 0, 1)
    + IF(@escolas_selecionadas > 0, 0, 1)
    + IF(@componentes_selecionados > 0, 0, 1);

DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_4_srm_guard;
CREATE TEMPORARY TABLE tmp_avaliacao_4_srm_guard (
    total_erros int NOT NULL,
    CONSTRAINT chk_tmp_avaliacao_4_srm_sem_erros CHECK (total_erros = 0)
) ENGINE=InnoDB;

-- Interrompe antes de qualquer escrita persistente se o alvo for ambiguo.
INSERT INTO tmp_avaliacao_4_srm_guard (total_erros)
VALUES (@total_erros_guard);
DROP TEMPORARY TABLE tmp_avaliacao_4_srm_guard;

DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_4_turmas_srm;
CREATE TEMPORARY TABLE tmp_avaliacao_4_turmas_srm (
    turma_id bigint unsigned NOT NULL PRIMARY KEY,
    escola_id bigint unsigned NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_avaliacao_4_turmas_srm (turma_id, escola_id)
SELECT t.id, t.id_escola
FROM turmas t
JOIN avaliacao_escola ae
  ON ae.avaliacao_id = @avaliacao_id
 AND ae.escola_id = t.id_escola
WHERE t.id_serie = @srm_serie_id;

DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_4_preservacao;
CREATE TEMPORARY TABLE tmp_avaliacao_4_preservacao AS
SELECT
    (SELECT COUNT(*) FROM alunos) AS alunos,
    (SELECT COUNT(*) FROM avaliacao_aluno_documentos WHERE avaliacao_id = @avaliacao_id) AS documentos,
    (SELECT COALESCE(SUM(CRC32(CAST(payload AS CHAR))), 0)
       FROM avaliacao_aluno_documentos
      WHERE avaliacao_id = @avaliacao_id) AS payload_fingerprint,
    (SELECT COUNT(*) FROM avaliacao_aluno_documentos_historico WHERE avaliacao_id = @avaliacao_id) AS historicos;

START TRANSACTION;

INSERT IGNORE INTO avaliacao_serie
    (avaliacao_id, serie_id, created_at, updated_at)
VALUES
    (@avaliacao_id, @srm_serie_id, NOW(), NOW());

INSERT IGNORE INTO avaliacao_turma
    (avaliacao_id, turma_id, created_at, updated_at)
SELECT @avaliacao_id, turma_id, NOW(), NOW()
FROM tmp_avaliacao_4_turmas_srm;

-- Garante as pautas SRM ativas dos componentes selecionados na avaliacao.
INSERT IGNORE INTO avaliacao_pauta
    (avaliacao_id, pauta_id, created_at, updated_at)
SELECT @avaliacao_id, p.id, NOW(), NOW()
FROM avaliacoes av
JOIN pautas p
  ON p.tipo_avaliacao_id = av.tipo_avaliacao_id
 AND p.serie_id = @srm_serie_id
 AND p.status = 1
JOIN avaliacao_componente ac
  ON ac.avaliacao_id = av.id
 AND ac.componente_curricular_id = p.componente_curricular_id
WHERE av.id = @avaliacao_id;

-- Materializa somente matriculas SRM ativas de contra turno.
INSERT IGNORE INTO avaliacao_dashboard_fatos (
    avaliacao_id,
    aluno_id,
    turma_id,
    escola_id,
    serie_id,
    pauta_id,
    componente_curricular_id,
    professor_id,
    alternativa_id,
    respondida,
    observacao_pendente,
    status_resposta,
    respondida_em,
    origem_version,
    created_at,
    updated_at
)
SELECT
    @avaliacao_id,
    aluno.id,
    turma.turma_id,
    turma.escola_id,
    @srm_serie_id,
    pauta.id,
    pauta.componente_curricular_id,
    NULL,
    NULL,
    0,
    0,
    'pendente',
    NULL,
    1,
    NOW(),
    NOW()
FROM tmp_avaliacao_4_turmas_srm turma
JOIN alunos aluno
  ON aluno.id_turma = turma.turma_id
 AND BINARY aluno.tipo_vinculo = BINARY 'contra_turno'
 AND aluno.status = 'matriculado'
JOIN avaliacao_pauta ap
  ON ap.avaliacao_id = @avaliacao_id
JOIN pautas pauta
  ON pauta.id = ap.pauta_id
 AND pauta.serie_id = @srm_serie_id
 AND pauta.status = 1
JOIN avaliacao_componente ac
  ON ac.avaliacao_id = @avaliacao_id
 AND ac.componente_curricular_id = pauta.componente_curricular_id;

-- Se o script for reexecutado depois de algum preenchimento, reflete as
-- respostas canonicas nos fatos sem alterar os documentos JSON.
UPDATE avaliacao_dashboard_fatos fato
JOIN (
    SELECT
        documento.avaliacao_id,
        documento.aluno_id,
        item.pauta_id,
        item.alternativa_id,
        item.professor_id,
        item.componente_curricular_id,
        item.observacao,
        item.respondido_em,
        documento.version
    FROM avaliacao_aluno_documentos documento
    CROSS JOIN JSON_TABLE(
        COALESCE(documento.payload, JSON_OBJECT()),
        '$.pautas.*'
        COLUMNS (
            pauta_id int PATH '$.pauta_id' NULL ON ERROR,
            alternativa_id int PATH '$.alternativa_id' NULL ON ERROR,
            professor_id int PATH '$.professor_id' NULL ON ERROR,
            componente_curricular_id int PATH '$.componente_curricular_id' NULL ON ERROR,
            observacao text PATH '$.observacao' NULL ON ERROR,
            respondido_em varchar(64) PATH '$.respondido_em' NULL ON ERROR
        )
    ) item
    WHERE documento.avaliacao_id = @avaliacao_id
      AND item.pauta_id IS NOT NULL
      AND item.alternativa_id IS NOT NULL
) resposta
  ON resposta.avaliacao_id = fato.avaliacao_id
 AND resposta.aluno_id = fato.aluno_id
 AND resposta.pauta_id = fato.pauta_id
LEFT JOIN alternativas alternativa
  ON alternativa.id = resposta.alternativa_id
SET
    fato.alternativa_id = resposta.alternativa_id,
    fato.professor_id = NULLIF(resposta.professor_id, 0),
    fato.componente_curricular_id = COALESCE(
        NULLIF(resposta.componente_curricular_id, 0),
        fato.componente_curricular_id
    ),
    fato.respondida = 1,
    fato.observacao_pendente = CASE
        WHEN COALESCE(alternativa.tem_observacao, 0) = 1
         AND NULLIF(TRIM(resposta.observacao), '') IS NULL THEN 1
        ELSE 0
    END,
    fato.status_resposta = CASE
        WHEN COALESCE(alternativa.tem_observacao, 0) = 1
         AND NULLIF(TRIM(resposta.observacao), '') IS NULL THEN 'pendente_observacao'
        ELSE 'respondida'
    END,
    fato.respondida_em = resposta.respondido_em,
    fato.origem_version = resposta.version,
    fato.updated_at = NOW()
WHERE fato.avaliacao_id = @avaliacao_id;

INSERT INTO avaliacao_dashboard_consolidacoes (
    avaliacao_id,
    status,
    solicitada_em,
    iniciada_em,
    consolidada_em,
    erro,
    created_at,
    updated_at
)
VALUES (
    @avaliacao_id,
    'consolidado',
    NOW(),
    NOW(),
    NOW(),
    NULL,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    status = 'consolidado',
    iniciada_em = COALESCE(iniciada_em, NOW()),
    consolidada_em = NOW(),
    erro = NULL,
    updated_at = NOW();

COMMIT;

-- Resultado 1: totais recuperados. No dump analisado sao esperados
-- 44 turmas, 246 alunos elegiveis, 68 pautas e 16.728 fatos.
SELECT
    COUNT(DISTINCT turma.turma_id) AS `Turmas SRM`,
    COUNT(DISTINCT aluno.id) AS `Alunos elegiveis`,
    COUNT(DISTINCT pauta.id) AS `Pautas SRM`,
    COUNT(DISTINCT fato.id) AS `Fatos materializados`
FROM tmp_avaliacao_4_turmas_srm turma
LEFT JOIN alunos aluno
  ON aluno.id_turma = turma.turma_id
 AND BINARY aluno.tipo_vinculo = BINARY 'contra_turno'
 AND aluno.status = 'matriculado'
LEFT JOIN avaliacao_pauta ap
  ON ap.avaliacao_id = @avaliacao_id
LEFT JOIN pautas pauta
  ON pauta.id = ap.pauta_id
 AND pauta.serie_id = @srm_serie_id
 AND pauta.status = 1
LEFT JOIN avaliacao_dashboard_fatos fato
  ON fato.avaliacao_id = @avaliacao_id
 AND fato.aluno_id = aluno.id
 AND fato.turma_id = turma.turma_id
 AND fato.pauta_id = pauta.id;

-- Resultado 2: turmas com alunos, mas sem professor do componente SRM.
-- Esses alunos ficam consolidados, porem a turma nao aparece na tela do
-- professor ate que o professor correto seja atribuido no cadastro da turma.
SELECT
    escola.nome AS `Escola`,
    turma.turma_id AS `Turma ID`,
    t.nome AS `Turma`,
    t.turno AS `Turno`,
    COUNT(DISTINCT aluno.id) AS `Alunos sem professor`
FROM tmp_avaliacao_4_turmas_srm turma
JOIN turmas t ON t.id = turma.turma_id
JOIN escolas escola ON escola.id = turma.escola_id
JOIN alunos aluno
  ON aluno.id_turma = turma.turma_id
 AND BINARY aluno.tipo_vinculo = BINARY 'contra_turno'
 AND aluno.status = 'matriculado'
WHERE NOT EXISTS (
    SELECT 1
    FROM turma_componente_professor tcp
    JOIN avaliacao_componente ac
      ON ac.avaliacao_id = @avaliacao_id
     AND ac.componente_curricular_id = tcp.componente_curricular_id
    WHERE tcp.turma_id = turma.turma_id
      AND tcp.tem_professor = 1
      AND tcp.professor_id IS NOT NULL
)
GROUP BY escola.nome, turma.turma_id, t.nome, t.turno
ORDER BY escola.nome, t.turno, t.nome;

-- Resultado 3: prova de que alunos e documentos canonicos foram preservados.
SELECT
    antes.alunos AS `Alunos antes`,
    (SELECT COUNT(*) FROM alunos) AS `Alunos depois`,
    antes.documentos AS `Documentos antes`,
    (SELECT COUNT(*) FROM avaliacao_aluno_documentos WHERE avaliacao_id = @avaliacao_id) AS `Documentos depois`,
    antes.payload_fingerprint AS `Fingerprint antes`,
    (SELECT COALESCE(SUM(CRC32(CAST(payload AS CHAR))), 0)
       FROM avaliacao_aluno_documentos
      WHERE avaliacao_id = @avaliacao_id) AS `Fingerprint depois`,
    antes.historicos AS `Historicos antes`,
    (SELECT COUNT(*) FROM avaliacao_aluno_documentos_historico WHERE avaliacao_id = @avaliacao_id) AS `Historicos depois`
FROM tmp_avaliacao_4_preservacao antes;

DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_4_preservacao;
DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_4_turmas_srm;
