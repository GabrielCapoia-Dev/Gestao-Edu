-- Recuperacao aditiva da avaliacao 3: regular, integral e SRM.
-- Compatibilidade: MySQL 8.0+.
--
-- Series restauradas simultaneamente:
--   1o Ano, 2o Ano, 1o Ano - Integral, 2o Ano - Integral e
--   Sala de Recursos Multifuncionais.
--
-- O script e idempotente e preserva documentos, historicos e respostas.
-- Fatos ja existentes nao sao apagados, substituidos nem movidos.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @avaliacao_id := 3;

-- A tentativa anterior pode ter parado depois de START TRANSACTION.
-- ROLLBACK e inofensivo quando nao existe transacao aberta e impede que
-- escritas parciais da tentativa com erro sejam confirmadas por engano.
ROLLBACK;

DROP TEMPORARY TABLE IF EXISTS tmp_series_alvo;
CREATE TEMPORARY TABLE tmp_series_alvo (
    ordem tinyint unsigned NOT NULL PRIMARY KEY,
    nome varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    tipo_escopo enum('regular', 'integral', 'srm')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    serie_id bigint unsigned NULL,
    correspondencias int unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- Resolve nomes e IDs durante a insercao. Isso evita o erro MySQL #1137
-- causado por atualizar e reler a mesma tabela temporaria na mesma instrucao.
INSERT INTO tmp_series_alvo
    (ordem, nome, tipo_escopo, serie_id, correspondencias)
SELECT
    alvo.ordem,
    alvo.nome,
    alvo.tipo_escopo,
    MIN(s.id) AS serie_id,
    COUNT(s.id) AS correspondencias
FROM (
    SELECT 1 AS ordem, '1º Ano' AS nome, 'regular' AS tipo_escopo
    UNION ALL SELECT 2, '2º Ano', 'regular'
    UNION ALL SELECT 3, '1º Ano - Integral', 'integral'
    UNION ALL SELECT 4, '2º Ano - Integral', 'integral'
    UNION ALL SELECT 5, 'Sala de Recursos Multifuncionais', 'srm'
) alvo
LEFT JOIN series s ON BINARY s.nome = BINARY alvo.nome
GROUP BY alvo.ordem, alvo.nome, alvo.tipo_escopo;

SET @avaliacao_correspondencias := (
    SELECT COUNT(*) FROM avaliacoes WHERE id = @avaliacao_id
);
SET @series_invalidas := (
    SELECT COUNT(*) FROM tmp_series_alvo WHERE correspondencias <> 1
);
SET @escolas_selecionadas := (
    SELECT COUNT(*) FROM avaliacao_escola WHERE avaliacao_id = @avaliacao_id
);
SET @componentes_selecionados := (
    SELECT COUNT(*) FROM avaliacao_componente WHERE avaliacao_id = @avaliacao_id
);
SET @total_erros_guard :=
      IF(@avaliacao_correspondencias = 1, 0, 1)
    + @series_invalidas
    + IF(@escolas_selecionadas > 0, 0, 1)
    + IF(@componentes_selecionados > 0, 0, 1);

DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_guard;
CREATE TEMPORARY TABLE tmp_avaliacao_guard (
    total_erros int NOT NULL,
    CONSTRAINT chk_tmp_avaliacao_sem_erros CHECK (total_erros = 0)
) ENGINE=InnoDB;

-- Interrompe antes da primeira escrita persistente quando o alvo e ambiguo.
INSERT INTO tmp_avaliacao_guard (total_erros) VALUES (@total_erros_guard);
DROP TEMPORARY TABLE tmp_avaliacao_guard;

-- Turmas das cinco series pertencentes as escolas ja selecionadas na avaliacao.
DROP TEMPORARY TABLE IF EXISTS tmp_turmas_alvo;
CREATE TEMPORARY TABLE tmp_turmas_alvo (
    turma_id bigint unsigned NOT NULL PRIMARY KEY,
    escola_id bigint unsigned NOT NULL,
    serie_id bigint unsigned NOT NULL,
    serie_nome varchar(255) NOT NULL,
    tipo_escopo enum('regular', 'integral', 'srm')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    turma_nome varchar(255) NOT NULL,
    turno varchar(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_turmas_alvo
    (turma_id, escola_id, serie_id, serie_nome, tipo_escopo, turma_nome, turno)
SELECT
    t.id,
    t.id_escola,
    t.id_serie,
    alvo.nome,
    alvo.tipo_escopo,
    t.nome,
    t.turno
FROM tmp_series_alvo alvo
JOIN turmas t ON t.id_serie = alvo.serie_id
JOIN avaliacao_escola ae
  ON ae.escola_id = t.id_escola
 AND ae.avaliacao_id = @avaliacao_id;

-- Define a origem dos alunos sem depender dos vinculos atuais em avaliacao_turma.
DROP TEMPORARY TABLE IF EXISTS tmp_turma_origem;
CREATE TEMPORARY TABLE tmp_turma_origem (
    turma_avaliativa_id bigint unsigned NOT NULL PRIMARY KEY,
    turma_origem_id bigint unsigned NULL,
    tipo_vinculo enum('principal', 'contra_turno')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    tipo_origem enum('direta', 'integral_base', 'srm', 'sem_origem')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_turma_origem
    (turma_avaliativa_id, turma_origem_id, tipo_vinculo, tipo_origem)
SELECT
    alvo.turma_id,
    CASE
        WHEN alvo.tipo_escopo = 'srm' THEN alvo.turma_id
        WHEN alvo.tipo_escopo = 'regular' THEN alvo.turma_id
        WHEN EXISTS (
            SELECT 1
            FROM alunos direto
            WHERE direto.id_turma = alvo.turma_id
              AND direto.tipo_vinculo = 'principal'
              AND direto.status <> 'pendente'
        ) THEN alvo.turma_id
        WHEN COUNT(DISTINCT base_turma.id) = 1 THEN MIN(base_turma.id)
        ELSE NULL
    END AS turma_origem_id,
    CASE WHEN alvo.tipo_escopo = 'srm' THEN 'contra_turno' ELSE 'principal' END,
    CASE
        WHEN alvo.tipo_escopo = 'srm' THEN 'srm'
        WHEN alvo.tipo_escopo = 'regular' THEN 'direta'
        WHEN EXISTS (
            SELECT 1
            FROM alunos direto
            WHERE direto.id_turma = alvo.turma_id
              AND direto.tipo_vinculo = 'principal'
              AND direto.status <> 'pendente'
        ) THEN 'direta'
        WHEN COUNT(DISTINCT base_turma.id) = 1 THEN 'integral_base'
        ELSE 'sem_origem'
    END AS tipo_origem
FROM tmp_turmas_alvo alvo
LEFT JOIN tmp_series_alvo serie_regular
  ON alvo.tipo_escopo = 'integral'
 AND BINARY alvo.serie_nome = BINARY CONCAT(serie_regular.nome, ' - Integral')
 AND serie_regular.tipo_escopo = 'regular'
LEFT JOIN turmas base_turma
  ON base_turma.id_escola = alvo.escola_id
 AND base_turma.id_serie = serie_regular.serie_id
 AND BINARY base_turma.nome = BINARY alvo.turma_nome
 AND BINARY base_turma.turno = BINARY alvo.turno
 AND EXISTS (
     SELECT 1
     FROM alunos aluno_base
     WHERE aluno_base.id_turma = base_turma.id
       AND aluno_base.tipo_vinculo = 'principal'
       AND aluno_base.status <> 'pendente'
 )
GROUP BY
    alvo.turma_id,
    alvo.tipo_escopo,
    alvo.serie_nome;

-- Fingerprints anteriores das fontes canonicas; usados na validacao final.
DROP TEMPORARY TABLE IF EXISTS tmp_preservacao_antes;
CREATE TEMPORARY TABLE tmp_preservacao_antes AS
SELECT
    (SELECT COUNT(*) FROM avaliacao_aluno_documentos WHERE avaliacao_id = @avaliacao_id) AS documentos,
    (SELECT COALESCE(SUM(CRC32(CAST(payload AS CHAR))), 0) FROM avaliacao_aluno_documentos WHERE avaliacao_id = @avaliacao_id) AS payload_fingerprint,
    (SELECT COALESCE(SUM(version), 0) FROM avaliacao_aluno_documentos WHERE avaliacao_id = @avaliacao_id) AS versoes,
    (SELECT COUNT(*) FROM avaliacao_aluno_documentos_historico WHERE avaliacao_id = @avaliacao_id) AS historicos,
    (SELECT COALESCE(SUM(CRC32(CAST(payload AS CHAR))), 0) FROM avaliacao_aluno_documentos_historico WHERE avaliacao_id = @avaliacao_id) AS historicos_fingerprint;

START TRANSACTION;

INSERT IGNORE INTO avaliacao_serie (avaliacao_id, serie_id, created_at, updated_at)
SELECT @avaliacao_id, serie_id, NOW(), NOW()
FROM tmp_series_alvo;

INSERT IGNORE INTO avaliacao_turma (avaliacao_id, turma_id, created_at, updated_at)
SELECT @avaliacao_id, turma_id, NOW(), NOW()
FROM tmp_turmas_alvo;

-- Pautas ativas, do mesmo tipo, serie e componente curricular da avaliacao.
INSERT IGNORE INTO avaliacao_pauta (avaliacao_id, pauta_id, created_at, updated_at)
SELECT
    @avaliacao_id,
    p.id,
    NOW(),
    NOW()
FROM avaliacoes av
JOIN tmp_series_alvo alvo
JOIN pautas p
  ON p.tipo_avaliacao_id = av.tipo_avaliacao_id
 AND p.serie_id = alvo.serie_id
 AND p.status = 1
JOIN avaliacao_componente ac
  ON ac.avaliacao_id = av.id
 AND ac.componente_curricular_id = p.componente_curricular_id
JOIN serie_componente_curricular scc
  ON scc.serie_id = alvo.serie_id
 AND scc.componente_curricular_id = p.componente_curricular_id
WHERE av.id = @avaliacao_id;

-- Acrescenta somente fatos ausentes. Fatos regulares, integrais ou SRM que ja
-- existem nao sao substituidos nem movidos para outra turma/serie.
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
    turma.serie_id,
    p.id,
    p.componente_curricular_id,
    NULL,
    NULL,
    0,
    0,
    'pendente',
    NULL,
    1,
    NOW(),
    NOW()
FROM tmp_turmas_alvo turma
JOIN tmp_turma_origem origem
  ON origem.turma_avaliativa_id = turma.turma_id
 AND origem.turma_origem_id IS NOT NULL
JOIN alunos aluno
  ON aluno.id_turma = origem.turma_origem_id
 AND BINARY aluno.tipo_vinculo = BINARY origem.tipo_vinculo
 AND (
     (origem.tipo_vinculo = 'contra_turno' AND aluno.status = 'matriculado')
     OR
     (origem.tipo_vinculo = 'principal' AND aluno.status <> 'pendente')
 )
JOIN avaliacao_pauta ap ON ap.avaliacao_id = @avaliacao_id
JOIN pautas p
  ON p.id = ap.pauta_id
 AND p.serie_id = turma.serie_id
 AND p.status = 1
JOIN avaliacao_componente ac
  ON ac.avaliacao_id = @avaliacao_id
 AND ac.componente_curricular_id = p.componente_curricular_id
JOIN serie_componente_curricular scc
  ON scc.serie_id = turma.serie_id
 AND scc.componente_curricular_id = p.componente_curricular_id;

-- Reaplica as respostas dos documentos JSON canonicos nos fatos criados agora.
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
LEFT JOIN alternativas alternativa ON alternativa.id = resposta.alternativa_id
SET
    fato.alternativa_id = resposta.alternativa_id,
    fato.professor_id = NULLIF(resposta.professor_id, 0),
    fato.componente_curricular_id = COALESCE(NULLIF(resposta.componente_curricular_id, 0), fato.componente_curricular_id),
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
    avaliacao_id, status, solicitada_em, iniciada_em, consolidada_em, erro, created_at, updated_at
)
VALUES (
    @avaliacao_id, 'consolidado', NOW(), NOW(), NOW(), NULL, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    status = 'consolidado',
    consolidada_em = NOW(),
    erro = NULL,
    updated_at = NOW();

COMMIT;

-- Resultado 1: as cinco series e seus totais efetivos.
SELECT
    alvo.ordem AS `Ordem`,
    alvo.nome AS `Serie`,
    COUNT(DISTINCT turma.turma_id) AS `Turmas vinculadas`,
    COUNT(DISTINCT CASE
        WHEN origem.tipo_vinculo = 'contra_turno' AND aluno.status = 'matriculado' THEN aluno.id
        WHEN origem.tipo_vinculo = 'principal' AND aluno.status <> 'pendente' THEN aluno.id
        ELSE NULL
    END) AS `Alunos elegiveis`,
    COUNT(DISTINCT pauta.id) AS `Pautas vinculadas`,
    COUNT(DISTINCT pauta.componente_curricular_id) AS `Componentes`,
    COUNT(DISTINCT fato.id) AS `Fatos`
FROM tmp_series_alvo alvo
LEFT JOIN tmp_turmas_alvo turma ON turma.serie_id = alvo.serie_id
LEFT JOIN tmp_turma_origem origem ON origem.turma_avaliativa_id = turma.turma_id
LEFT JOIN alunos aluno
  ON aluno.id_turma = origem.turma_origem_id
 AND BINARY aluno.tipo_vinculo = BINARY origem.tipo_vinculo
LEFT JOIN avaliacao_pauta ap ON ap.avaliacao_id = @avaliacao_id
LEFT JOIN pautas pauta
  ON pauta.id = ap.pauta_id
 AND pauta.serie_id = alvo.serie_id
 AND pauta.status = 1
LEFT JOIN avaliacao_dashboard_fatos fato
  ON fato.avaliacao_id = @avaliacao_id
 AND fato.serie_id = alvo.serie_id
GROUP BY alvo.ordem, alvo.nome
ORDER BY alvo.ordem;

-- Resultado 2: pendencias. Conjunto vazio significa que todas possuem fatos.
SELECT
    alvo.nome AS `Serie`,
    NULL AS `Escola`,
    NULL AS `Turma`,
    CONCAT_WS('; ',
        IF(NOT EXISTS (
            SELECT 1
            FROM avaliacao_turma av_turma
            JOIN turmas t ON t.id = av_turma.turma_id
            WHERE av_turma.avaliacao_id = @avaliacao_id
              AND t.id_serie = alvo.serie_id
        ), 'Serie sem turma vinculada', NULL),
        IF(NOT EXISTS (
            SELECT 1
            FROM avaliacao_pauta ap
            JOIN pautas p ON p.id = ap.pauta_id
            WHERE ap.avaliacao_id = @avaliacao_id
              AND p.serie_id = alvo.serie_id
              AND p.status = 1
        ), 'Serie sem pauta ativa vinculada', NULL),
        IF(NOT EXISTS (
            SELECT 1
            FROM avaliacao_dashboard_fatos f
            WHERE f.avaliacao_id = @avaliacao_id
              AND f.serie_id = alvo.serie_id
        ), 'Serie sem fatos gerados', NULL)
    ) AS `Pendencia`
FROM tmp_series_alvo alvo
WHERE NOT EXISTS (
        SELECT 1
        FROM avaliacao_turma av_turma
        JOIN turmas t ON t.id = av_turma.turma_id
        WHERE av_turma.avaliacao_id = @avaliacao_id
          AND t.id_serie = alvo.serie_id
    )
   OR NOT EXISTS (
        SELECT 1
        FROM avaliacao_pauta ap
        JOIN pautas p ON p.id = ap.pauta_id
        WHERE ap.avaliacao_id = @avaliacao_id
          AND p.serie_id = alvo.serie_id
          AND p.status = 1
    )
   OR NOT EXISTS (
        SELECT 1
        FROM avaliacao_dashboard_fatos f
        WHERE f.avaliacao_id = @avaliacao_id
          AND f.serie_id = alvo.serie_id
)
UNION ALL
SELECT
    turma.serie_nome,
    escola.nome,
    turma.turma_nome,
    'Turma integral sem alunos diretos e sem turma-base unica'
FROM tmp_turma_origem origem
JOIN tmp_turmas_alvo turma ON turma.turma_id = origem.turma_avaliativa_id
JOIN escolas escola ON escola.id = turma.escola_id
WHERE origem.tipo_origem = 'sem_origem'
ORDER BY `Serie`, `Escola`, `Turma`;

-- Resultado 3: prova de preservacao das fontes canonicas.
SELECT
    antes.documentos AS `Documentos antes`,
    depois.documentos AS `Documentos depois`,
    antes.payload_fingerprint AS `Fingerprint payload antes`,
    depois.payload_fingerprint AS `Fingerprint payload depois`,
    antes.versoes AS `Soma versoes antes`,
    depois.versoes AS `Soma versoes depois`,
    antes.historicos AS `Historicos antes`,
    depois.historicos AS `Historicos depois`,
    antes.historicos_fingerprint AS `Fingerprint historico antes`,
    depois.historicos_fingerprint AS `Fingerprint historico depois`,
    CASE
        WHEN antes.documentos = depois.documentos
         AND antes.payload_fingerprint = depois.payload_fingerprint
         AND antes.versoes = depois.versoes
         AND antes.historicos = depois.historicos
         AND antes.historicos_fingerprint = depois.historicos_fingerprint
        THEN 'PRESERVADO'
        ELSE 'DIVERGENCIA'
    END AS `Validacao`
FROM tmp_preservacao_antes antes
CROSS JOIN (
    SELECT
        (SELECT COUNT(*) FROM avaliacao_aluno_documentos WHERE avaliacao_id = @avaliacao_id) AS documentos,
        (SELECT COALESCE(SUM(CRC32(CAST(payload AS CHAR))), 0) FROM avaliacao_aluno_documentos WHERE avaliacao_id = @avaliacao_id) AS payload_fingerprint,
        (SELECT COALESCE(SUM(version), 0) FROM avaliacao_aluno_documentos WHERE avaliacao_id = @avaliacao_id) AS versoes,
        (SELECT COUNT(*) FROM avaliacao_aluno_documentos_historico WHERE avaliacao_id = @avaliacao_id) AS historicos,
        (SELECT COALESCE(SUM(CRC32(CAST(payload AS CHAR))), 0) FROM avaliacao_aluno_documentos_historico WHERE avaliacao_id = @avaliacao_id) AS historicos_fingerprint
) depois;

DROP TEMPORARY TABLE IF EXISTS tmp_preservacao_antes;
DROP TEMPORARY TABLE IF EXISTS tmp_turma_origem;
DROP TEMPORARY TABLE IF EXISTS tmp_turmas_alvo;
DROP TEMPORARY TABLE IF EXISTS tmp_series_alvo;
