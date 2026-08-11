-- Sincroniza pautas faltantes de uma avaliacao existente sem apagar respostas.
-- Compatibilidade: MySQL 8.0+.
--
-- Ordem obrigatoria:
-- 1. gerar e validar um backup do banco;
-- 2. colocar a aplicacao em manutencao para impedir respostas concorrentes;
-- 3. executar primeiro o script que importa/corrige o catalogo de pautas;
-- 4. informar abaixo o ID exato da avaliacao e executar este arquivo;
-- 5. confirmar `total_erros = 0` e `pautas_ainda_faltantes = 0`;
-- 6. aguardar pelo menos 60 segundos e atualizar o dashboard com Ctrl+F5.
--
-- Este script:
-- - adiciona somente pautas ativas ainda ausentes;
-- - respeita tipo, series e componentes da avaliacao;
-- - exige o vinculo serie x componente no catalogo curricular;
-- - nao remove pautas, alternativas, documentos, historicos ou respostas;
-- - nao altera o JSON `payload` dos documentos;
-- - atualiza apenas totais/status derivados dos documentos;
-- - reconstrói os fatos do dashboard e reaplica as respostas do JSON canonico;
-- - usa os alunos da turma-base quando uma turma "- Integral" legada estiver vazia;
-- - pode ser executado novamente sem duplicar vinculos.

-- CONFIGURACAO OBRIGATORIA: substitua NULL pelo ID real da avaliacao.
-- Exemplo para a avaliacao 3: SET @avaliacao_alvo_id := 3;
SET @avaliacao_alvo_id := 3;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Falha explicitamente se o arquivo for executado sem configurar o alvo.
DROP TEMPORARY TABLE IF EXISTS tmp_avaliacao_configuracao_obrigatoria;
CREATE TEMPORARY TABLE tmp_avaliacao_configuracao_obrigatoria (
    avaliacao_id bigint unsigned NOT NULL,
    CONSTRAINT chk_tmp_avaliacao_id_positivo CHECK (avaliacao_id > 0)
) ENGINE=InnoDB;
INSERT INTO tmp_avaliacao_configuracao_obrigatoria (avaliacao_id)
VALUES (@avaliacao_alvo_id);
DROP TEMPORARY TABLE tmp_avaliacao_configuracao_obrigatoria;

START TRANSACTION;

SET @avaliacao_matches := (
    SELECT COUNT(*)
    FROM avaliacoes
    WHERE id = @avaliacao_alvo_id
);

SET @avaliacao_tipo_id := (
    SELECT tipo_avaliacao_id
    FROM avaliacoes
    WHERE id = @avaliacao_alvo_id
    LIMIT 1
);

SET @series_selecionadas := (
    SELECT COUNT(*)
    FROM avaliacao_serie
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @componentes_selecionados := (
    SELECT COUNT(*)
    FROM avaliacao_componente
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @turmas_selecionadas := (
    SELECT COUNT(*)
    FROM avaliacao_turma
    WHERE avaliacao_id = @avaliacao_alvo_id
);

DROP TEMPORARY TABLE IF EXISTS tmp_turmas_integrais_origem;
CREATE TEMPORARY TABLE tmp_turmas_integrais_origem (
    turma_avaliativa_id bigint unsigned NOT NULL PRIMARY KEY,
    turma_origem_alunos_id bigint unsigned NOT NULL,
    serie_avaliativa_id bigint unsigned NOT NULL,
    serie_origem_id bigint unsigned NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_turmas_integrais_origem
    (turma_avaliativa_id, turma_origem_alunos_id, serie_avaliativa_id, serie_origem_id)
SELECT t.id, MIN(origem.id), t.id_serie, origem.id_serie
FROM avaliacao_turma at
JOIN turmas t ON t.id = at.turma_id
JOIN series serie_integral ON serie_integral.id = t.id_serie
JOIN series serie_base
  ON serie_integral.nome = CONCAT(serie_base.nome, ' - Integral')
JOIN turmas origem
  ON origem.id_escola = t.id_escola
 AND origem.id_serie = serie_base.id
 AND origem.nome = t.nome
 AND origem.turno = t.turno
WHERE at.avaliacao_id = @avaliacao_alvo_id
  AND EXISTS (
      SELECT 1
      FROM alunos aluno_origem
      WHERE aluno_origem.id_turma = origem.id
        AND aluno_origem.tipo_vinculo = 'principal'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM alunos direto
      WHERE direto.id_turma = t.id
        AND direto.tipo_vinculo = 'principal'
  )
GROUP BY t.id, t.id_serie, origem.id_serie
HAVING COUNT(DISTINCT origem.id) = 1;

SET @turmas_integrais_pareadas := (
    SELECT COUNT(*) FROM tmp_turmas_integrais_origem
);

SET @turmas_integrais_sem_origem := (
    SELECT COUNT(*)
    FROM avaliacao_turma at
    JOIN turmas t ON t.id = at.turma_id
    JOIN series s ON s.id = t.id_serie
    WHERE at.avaliacao_id = @avaliacao_alvo_id
      AND s.nome LIKE '% - Integral'
      AND NOT EXISTS (
          SELECT 1 FROM alunos direto
          WHERE direto.id_turma = t.id
            AND direto.tipo_vinculo = 'principal'
      )
      AND NOT EXISTS (
          SELECT 1 FROM tmp_turmas_integrais_origem mapa
          WHERE mapa.turma_avaliativa_id = t.id
      )
);

DROP TEMPORARY TABLE IF EXISTS tmp_pautas_avaliacao_desejadas;
CREATE TEMPORARY TABLE tmp_pautas_avaliacao_desejadas (
    pauta_id bigint unsigned NOT NULL PRIMARY KEY,
    serie_id bigint unsigned NOT NULL,
    componente_curricular_id bigint unsigned NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_pautas_avaliacao_desejadas
    (pauta_id, serie_id, componente_curricular_id)
SELECT DISTINCT
    p.id,
    p.serie_id,
    p.componente_curricular_id
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
WHERE p.tipo_avaliacao_id = @avaliacao_tipo_id
  AND p.status = 1;

SET @pautas_desejadas := (
    SELECT COUNT(*)
    FROM tmp_pautas_avaliacao_desejadas
);

DROP TEMPORARY TABLE IF EXISTS tmp_pautas_avaliacao_faltantes;
CREATE TEMPORARY TABLE tmp_pautas_avaliacao_faltantes (
    pauta_id bigint unsigned NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO tmp_pautas_avaliacao_faltantes (pauta_id)
SELECT d.pauta_id
FROM tmp_pautas_avaliacao_desejadas d
WHERE NOT EXISTS (
    SELECT 1
    FROM avaliacao_pauta ap
    WHERE ap.avaliacao_id = @avaliacao_alvo_id
      AND ap.pauta_id = d.pauta_id
);

SET @pautas_faltantes_antes := (
    SELECT COUNT(*)
    FROM tmp_pautas_avaliacao_faltantes
);

SET @documentos_antes := (
    SELECT COUNT(*)
    FROM avaliacao_aluno_documentos
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @historicos_antes := (
    SELECT COUNT(*)
    FROM avaliacao_aluno_documentos_historico
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @respostas_payload_antes := (
    SELECT COALESCE(SUM(JSON_LENGTH(JSON_EXTRACT(payload, '$.pautas'))), 0)
    FROM avaliacao_aluno_documentos
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @payload_fingerprint_antes := (
    SELECT COALESCE(BIT_XOR(CRC32(CAST(payload AS CHAR))), 0)
    FROM avaliacao_aluno_documentos
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @tem_erros :=
      IF(@avaliacao_alvo_id IS NULL, 1, 0)
    + IF(@avaliacao_matches = 1, 0, 1)
    + IF(@avaliacao_tipo_id IS NOT NULL, 0, 1)
    + IF(@series_selecionadas > 0, 0, 1)
    + IF(@componentes_selecionados > 0, 0, 1)
    + IF(@turmas_selecionadas > 0, 0, 1)
    + IF(@pautas_desejadas > 0, 0, 1)
    + IF(@turmas_integrais_sem_origem = 0, 0, 1);

-- Diagnostico anterior a qualquer escrita. Se total_erros > 0, as escritas sao no-op.
SELECT 'avaliacao_alvo_id' AS validacao, @avaliacao_alvo_id AS valor
UNION ALL SELECT 'avaliacao_matches', @avaliacao_matches
UNION ALL SELECT 'avaliacao_tipo_id', @avaliacao_tipo_id
UNION ALL SELECT 'series_selecionadas', @series_selecionadas
UNION ALL SELECT 'componentes_selecionados', @componentes_selecionados
UNION ALL SELECT 'turmas_selecionadas', @turmas_selecionadas
UNION ALL SELECT 'turmas_integrais_pareadas', @turmas_integrais_pareadas
UNION ALL SELECT 'turmas_integrais_sem_origem', @turmas_integrais_sem_origem
UNION ALL SELECT 'pautas_desejadas', @pautas_desejadas
UNION ALL SELECT 'pautas_faltantes_antes', @pautas_faltantes_antes
UNION ALL SELECT 'documentos_antes', @documentos_antes
UNION ALL SELECT 'historicos_antes', @historicos_antes
UNION ALL SELECT 'respostas_payload_antes', @respostas_payload_antes
UNION ALL SELECT 'total_erros', @tem_erros;

INSERT INTO avaliacao_pauta (avaliacao_id, pauta_id, created_at, updated_at)
SELECT @avaliacao_alvo_id, f.pauta_id, NOW(), NOW()
FROM tmp_pautas_avaliacao_faltantes f
WHERE @tem_erros = 0
  AND NOT EXISTS (
      SELECT 1
      FROM avaliacao_pauta ap
      WHERE ap.avaliacao_id = @avaliacao_alvo_id
        AND ap.pauta_id = f.pauta_id
  );

-- Recalcula somente metadados derivados; payload, respostas e version nao sao alterados.
DROP TEMPORARY TABLE IF EXISTS tmp_documentos_avaliacao_totais;
CREATE TEMPORARY TABLE tmp_documentos_avaliacao_totais (
    documento_id bigint unsigned NOT NULL PRIMARY KEY,
    total_pautas_esperadas int unsigned NOT NULL
) ENGINE=InnoDB;

INSERT INTO tmp_documentos_avaliacao_totais
    (documento_id, total_pautas_esperadas)
SELECT
    d.id,
    COUNT(DISTINCT p.id) AS total_pautas_esperadas
FROM avaliacao_aluno_documentos d
LEFT JOIN avaliacao_pauta ap
  ON ap.avaliacao_id = d.avaliacao_id
LEFT JOIN pautas p
  ON p.id = ap.pauta_id
 AND p.status = 1
 AND (
      p.serie_id IS NULL
      OR p.serie_id = d.serie_id
      OR EXISTS (
          SELECT 1
          FROM tmp_turmas_integrais_origem mapa
          WHERE mapa.turma_origem_alunos_id = d.turma_id
            AND mapa.serie_avaliativa_id = p.serie_id
      )
 )
WHERE d.avaliacao_id = @avaliacao_alvo_id
GROUP BY d.id;

UPDATE avaliacao_aluno_documentos d
JOIN tmp_documentos_avaliacao_totais t ON t.documento_id = d.id
SET d.total_pautas_esperadas = t.total_pautas_esperadas,
    d.status_preenchimento = CASE
        WHEN d.total_pautas_respondidas > 0
         AND t.total_pautas_esperadas > 0
         AND d.total_pautas_respondidas >= t.total_pautas_esperadas
         AND d.observacoes_obrigatorias_pendentes = 0
            THEN 'completo'
        WHEN d.total_pautas_respondidas > 0 OR d.total_infos_complementares > 0
            THEN 'parcial'
        ELSE 'vazio'
    END,
    d.updated_at = NOW()
WHERE @tem_erros = 0
  AND (
      d.total_pautas_esperadas <> t.total_pautas_esperadas
      OR d.status_preenchimento <> CASE
          WHEN d.total_pautas_respondidas > 0
           AND t.total_pautas_esperadas > 0
           AND d.total_pautas_respondidas >= t.total_pautas_esperadas
           AND d.observacoes_obrigatorias_pendentes = 0
              THEN 'completo'
          WHEN d.total_pautas_respondidas > 0 OR d.total_infos_complementares > 0
              THEN 'parcial'
          ELSE 'vazio'
      END
  );

-- Reconstrói os fatos derivados da avaliacao. As respostas canonicas permanecem
-- nos documentos e sao reaplicadas abaixo, com a mesma consulta usada pelo Laravel.
SET @fatos_antes := (
    SELECT COUNT(*)
    FROM avaliacao_dashboard_fatos
    WHERE avaliacao_id = @avaliacao_alvo_id
);

DELETE FROM avaliacao_dashboard_fatos
WHERE avaliacao_id = @avaliacao_alvo_id
  AND @tem_erros = 0;

INSERT INTO avaliacao_dashboard_fatos (
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
    at.avaliacao_id,
    aln.id AS aluno_id,
    t.id AS turma_id,
    t.id_escola AS escola_id,
    t.id_serie AS serie_id,
    p.id AS pauta_id,
    p.componente_curricular_id,
    NULL AS professor_id,
    NULL AS alternativa_id,
    0 AS respondida,
    0 AS observacao_pendente,
    'pendente' AS status_resposta,
    NULL AS respondida_em,
    1 AS origem_version,
    NOW() AS created_at,
    NOW() AS updated_at
FROM avaliacao_turma at
JOIN turmas t ON t.id = at.turma_id
LEFT JOIN tmp_turmas_integrais_origem mapa
  ON mapa.turma_avaliativa_id = t.id
JOIN alunos aln
  ON aln.id_turma = COALESCE(mapa.turma_origem_alunos_id, t.id)
JOIN avaliacao_pauta ap ON ap.avaliacao_id = at.avaliacao_id
JOIN pautas p ON p.id = ap.pauta_id
WHERE at.avaliacao_id = @avaliacao_alvo_id
  AND @tem_erros = 0
  AND aln.status <> 'pendente'
  AND aln.tipo_vinculo = 'principal'
  AND p.status = 1
  AND (
      p.serie_id = t.id_serie
      OR (p.serie_id IS NULL AND mapa.turma_avaliativa_id IS NULL)
  );

UPDATE avaliacao_dashboard_fatos f
JOIN (
    SELECT
        d.avaliacao_id,
        d.aluno_id,
        jt.pauta_id,
        jt.alternativa_id,
        jt.professor_id,
        jt.componente_curricular_id,
        jt.observacao,
        jt.respondido_em,
        d.version
    FROM avaliacao_aluno_documentos d
    CROSS JOIN JSON_TABLE(
        COALESCE(d.payload, JSON_OBJECT()),
        '$.pautas.*' COLUMNS (
            pauta_id int PATH '$.pauta_id' NULL ON ERROR,
            alternativa_id int PATH '$.alternativa_id' NULL ON ERROR,
            professor_id int PATH '$.professor_id' NULL ON ERROR,
            componente_curricular_id int PATH '$.componente_curricular_id' NULL ON ERROR,
            observacao text PATH '$.observacao' NULL ON ERROR,
            respondido_em varchar(64) PATH '$.respondido_em' NULL ON ERROR
        )
    ) jt
    WHERE d.avaliacao_id = @avaliacao_alvo_id
      AND jt.pauta_id IS NOT NULL
      AND jt.alternativa_id IS NOT NULL
) r
  ON r.avaliacao_id = f.avaliacao_id
 AND r.aluno_id = f.aluno_id
 AND r.pauta_id = f.pauta_id
LEFT JOIN alternativas alt ON alt.id = r.alternativa_id
SET f.alternativa_id = r.alternativa_id,
    f.professor_id = NULLIF(r.professor_id, 0),
    f.componente_curricular_id = COALESCE(
        NULLIF(r.componente_curricular_id, 0),
        f.componente_curricular_id
    ),
    f.respondida = 1,
    f.observacao_pendente = CASE
        WHEN COALESCE(alt.tem_observacao, 0) = 1
         AND NULLIF(TRIM(r.observacao), '') IS NULL
            THEN 1
        ELSE 0
    END,
    f.status_resposta = CASE
        WHEN COALESCE(alt.tem_observacao, 0) = 1
         AND NULLIF(TRIM(r.observacao), '') IS NULL
            THEN 'pendente_observacao'
        ELSE 'respondida'
    END,
    f.respondida_em = r.respondido_em,
    f.origem_version = r.version,
    f.updated_at = NOW()
WHERE f.avaliacao_id = @avaliacao_alvo_id
  AND @tem_erros = 0;

SET @fatos_depois := (
    SELECT COUNT(*)
    FROM avaliacao_dashboard_fatos
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @fatos_respondidos_depois := (
    SELECT COUNT(*)
    FROM avaliacao_dashboard_fatos
    WHERE avaliacao_id = @avaliacao_alvo_id
      AND respondida = 1
);

INSERT INTO avaliacao_dashboard_consolidacoes
    (
        avaliacao_id,
        status,
        solicitada_em,
        iniciada_em,
        consolidada_em,
        erro,
        created_at,
        updated_at
    )
SELECT
    @avaliacao_alvo_id,
    'consolidado',
    NOW(),
    NOW(),
    NOW(),
    NULL,
    NOW(),
    NOW()
WHERE @tem_erros = 0
ON DUPLICATE KEY UPDATE
    status = 'consolidado',
    solicitada_em = NOW(),
    iniciada_em = NOW(),
    consolidada_em = NOW(),
    erro = NULL,
    updated_at = NOW();

SET @documentos_depois := (
    SELECT COUNT(*)
    FROM avaliacao_aluno_documentos
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @historicos_depois := (
    SELECT COUNT(*)
    FROM avaliacao_aluno_documentos_historico
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @respostas_payload_depois := (
    SELECT COALESCE(SUM(JSON_LENGTH(JSON_EXTRACT(payload, '$.pautas'))), 0)
    FROM avaliacao_aluno_documentos
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @payload_fingerprint_depois := (
    SELECT COALESCE(BIT_XOR(CRC32(CAST(payload AS CHAR))), 0)
    FROM avaliacao_aluno_documentos
    WHERE avaliacao_id = @avaliacao_alvo_id
);

SET @pautas_ainda_faltantes := (
    SELECT COUNT(*)
    FROM tmp_pautas_avaliacao_desejadas d
    WHERE NOT EXISTS (
        SELECT 1
        FROM avaliacao_pauta ap
        WHERE ap.avaliacao_id = @avaliacao_alvo_id
          AND ap.pauta_id = d.pauta_id
    )
);

-- Nao usa ROW_COUNT(): interfaces como phpMyAdmin podem executar consultas internas
-- entre os comandos e zerar esse valor antes de o resumo final ser calculado.
SET @pautas_adicionadas := @pautas_faltantes_antes - @pautas_ainda_faltantes;

SET @preservacao_documentos_ok := IF(@documentos_antes = @documentos_depois, 1, 0);
SET @preservacao_historicos_ok := IF(@historicos_antes = @historicos_depois, 1, 0);
SET @preservacao_respostas_ok := IF(@respostas_payload_antes = @respostas_payload_depois, 1, 0);
SET @preservacao_payload_ok := IF(@payload_fingerprint_antes = @payload_fingerprint_depois, 1, 0);

SELECT
    IF(
        @tem_erros = 0
        AND @pautas_ainda_faltantes = 0
        AND @preservacao_documentos_ok = 1
        AND @preservacao_historicos_ok = 1
        AND @preservacao_respostas_ok = 1
        AND @preservacao_payload_ok = 1,
        'OK - pautas e dashboard sincronizados; respostas preservadas',
        'ERRO - revise as validacoes antes de liberar a aplicacao'
    ) AS resultado,
    @tem_erros AS total_erros,
    @pautas_desejadas AS pautas_desejadas,
    @pautas_faltantes_antes AS pautas_faltantes_antes,
    IF(@tem_erros = 0, @pautas_adicionadas, 0) AS pautas_adicionadas,
    @pautas_ainda_faltantes AS pautas_ainda_faltantes,
    @turmas_integrais_pareadas AS turmas_integrais_pareadas,
    @turmas_integrais_sem_origem AS turmas_integrais_sem_origem,
    @fatos_antes AS fatos_antes,
    @fatos_depois AS fatos_depois,
    @fatos_respondidos_depois AS fatos_respondidos_depois,
    @documentos_antes AS documentos_preservados,
    @historicos_antes AS historicos_preservados,
    @respostas_payload_antes AS respostas_preservadas,
    @preservacao_documentos_ok AS preservacao_documentos_ok,
    @preservacao_historicos_ok AS preservacao_historicos_ok,
    @preservacao_respostas_ok AS preservacao_respostas_ok,
    @preservacao_payload_ok AS preservacao_payload_ok;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_documentos_avaliacao_totais;
DROP TEMPORARY TABLE IF EXISTS tmp_pautas_avaliacao_faltantes;
DROP TEMPORARY TABLE IF EXISTS tmp_pautas_avaliacao_desejadas;
DROP TEMPORARY TABLE IF EXISTS tmp_turmas_integrais_origem;

-- Mantem um resultado util como ultima consulta para interfaces como phpMyAdmin.
SELECT
    IF(
        @tem_erros = 0
        AND @pautas_ainda_faltantes = 0
        AND @preservacao_documentos_ok = 1
        AND @preservacao_historicos_ok = 1
        AND @preservacao_respostas_ok = 1
        AND @preservacao_payload_ok = 1,
        'OK - pautas e dashboard sincronizados; respostas preservadas',
        'ERRO - revise as validacoes antes de liberar a aplicacao'
    ) AS resultado,
    @tem_erros AS total_erros,
    @pautas_adicionadas AS pautas_adicionadas,
    @pautas_ainda_faltantes AS pautas_ainda_faltantes,
    @turmas_integrais_pareadas AS turmas_integrais_pareadas,
    @turmas_integrais_sem_origem AS turmas_integrais_sem_origem,
    @fatos_antes AS fatos_antes,
    @fatos_depois AS fatos_depois,
    @fatos_respondidos_depois AS fatos_respondidos_depois,
    @preservacao_documentos_ok AS preservacao_documentos_ok,
    @preservacao_respostas_ok AS preservacao_respostas_ok,
    @preservacao_payload_ok AS preservacao_payload_ok;
