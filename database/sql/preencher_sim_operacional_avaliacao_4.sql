-- MySQL 8 / phpMyAdmin. Execute INTEIRO, com backup e sem gravacoes concorrentes.
-- Avaliacao 4: somente pautas sem resposta e com alternativa valida Sim.
-- Avaliacao 4. Requer CREATE ROUTINE. Falhas na rotina causam ROLLBACK.
-- Trata JSON apenas em ciclos ainda nao inicializados. Nao atribui professor.
DELIMITER $$
CREATE PROCEDURE preencher_sim_av4_20260909()
BEGIN
    DECLARE v_antes BIGINT DEFAULT 0;
    DECLARE v_novas BIGINT DEFAULT 0;
    DECLARE v_vazias BIGINT DEFAULT 0;
    DECLARE v_depois BIGINT DEFAULT 0;
    DECLARE v_legado BIGINT DEFAULT 0;
    DECLARE v_legado_antes BIGINT DEFAULT 0;
    DECLARE v_legado_depois BIGINT DEFAULT 0;
    DECLARE v_esperadas BIGINT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    START TRANSACTION;
    SELECT id AS ciclo_bloqueado FROM avaliacao_turma_ciclos
    WHERE avaliacao_id = 4 ORDER BY id FOR UPDATE;
    IF NOT EXISTS (SELECT 1 FROM avaliacao_turma_ciclos WHERE avaliacao_id = 4)
       OR EXISTS (
           SELECT 1 FROM avaliacao_turma atv WHERE atv.avaliacao_id = 4
           AND NOT EXISTS (SELECT 1 FROM avaliacao_turma_ciclos c
                          WHERE c.avaliacao_id = 4 AND c.turma_avaliativa_id = atv.turma_id)
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ciclos ausentes. Nenhuma resposta alterada.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM avaliacao_turma_ciclos c
        LEFT JOIN avaliacao_turma_tokens_escrita tk ON tk.ciclo_id = c.id
        WHERE c.avaliacao_id = 4 AND c.status IN ('aberta', 'reaberta')
        AND tk.id IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ciclo sem token. Nenhuma resposta alterada.';
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_sim_matriz;
    CREATE TEMPORARY TABLE tmp_sim_matriz (
        ciclo_id BIGINT UNSIGNED NOT NULL,
        token_escrita_id BIGINT UNSIGNED NOT NULL,
        turma_avaliativa_id BIGINT UNSIGNED NOT NULL,
        turma_origem_id BIGINT UNSIGNED NOT NULL,
        aluno_id BIGINT UNSIGNED NOT NULL,
        pauta_id BIGINT UNSIGNED NOT NULL,
        componente_curricular_id BIGINT UNSIGNED NULL,
        PRIMARY KEY (ciclo_id, aluno_id, pauta_id)
    ) ENGINE=InnoDB;
    INSERT INTO tmp_sim_matriz
    -- BEGIN MATRIZ
    SELECT c.id, tk.id, c.turma_avaliativa_id, c.turma_origem_id,
           aln.id, p.id, p.componente_curricular_id
    FROM avaliacao_turma_ciclos c
    JOIN avaliacao_turma_tokens_escrita tk ON tk.ciclo_id = c.id
    JOIN turmas t ON t.id = c.turma_avaliativa_id
    JOIN alunos aln ON aln.id_turma = c.turma_origem_id
    JOIN avaliacao_pauta ap ON ap.avaliacao_id = c.avaliacao_id
    JOIN pautas p ON p.id = ap.pauta_id
    WHERE c.avaliacao_id = 4 AND c.status IN ('aberta', 'reaberta')
      AND p.status = 1 AND (p.serie_id IS NULL OR p.serie_id = t.id_serie)
      AND aln.status IN ('matriculado', 'pendente')
      AND (aln.status <> 'pendente' OR aln.pendencia_origem_aluno_id IS NULL
           OR aln.pendencia_origem_aluno_id <= 0)
    -- END MATRIZ
    ;
    DROP TEMPORARY TABLE IF EXISTS tmp_sim_opcoes;
    CREATE TEMPORARY TABLE tmp_sim_opcoes (
        pauta_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        alternativa_id BIGINT UNSIGNED NOT NULL,
        quantidade BIGINT NOT NULL
    ) ENGINE=InnoDB;
    INSERT INTO tmp_sim_opcoes
    -- BEGIN OPCOES
    SELECT p.id, MIN(s.id), COUNT(DISTINCT s.id)
    FROM avaliacao_pauta avp
    JOIN avaliacoes av ON av.id = avp.avaliacao_id
    JOIN pautas p ON p.id = avp.pauta_id
    JOIN alternativas s ON s.status = 1 AND LOWER(TRIM(s.nome)) = 'sim'
    WHERE av.id = 4 AND p.status = 1 AND (
        EXISTS (SELECT 1 FROM avaliacao_pauta_alternativa o
                WHERE o.avaliacao_id = 4 AND o.pauta_id = p.id AND o.alternativa_id = s.id)
        OR (
            NOT EXISTS (SELECT 1 FROM avaliacao_pauta_alternativa o
                        WHERE o.avaliacao_id = 4 AND o.pauta_id = p.id)
            AND (
                EXISTS (SELECT 1 FROM alternativa_pauta a
                        WHERE a.pauta_id = p.id AND a.alternativa_id = s.id)
                OR (NOT EXISTS (SELECT 1 FROM alternativa_pauta a WHERE a.pauta_id = p.id)
                    AND (s.tipo_avaliacao_id = p.tipo_avaliacao_id OR s.tipo_avaliacao_id = av.tipo_avaliacao_id))
            )
        )
    ) GROUP BY p.id
    -- END OPCOES
    ;
    IF EXISTS (SELECT 1 FROM tmp_sim_opcoes WHERE quantidade <> 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Alternativa Sim ambigua. Nenhuma resposta alterada.';
    END IF;
    -- Separa a unica fonte valida de cada ciclo. Nao promove JSON antigo ao operacional.
    DROP TEMPORARY TABLE IF EXISTS tmp_sim_legado;
    CREATE TEMPORARY TABLE tmp_sim_legado AS
    SELECT m.* FROM tmp_sim_matriz m JOIN avaliacao_turma_ciclos c ON c.id=m.ciclo_id
    WHERE c.status='aberta' AND c.operacional_inicializado_em IS NULL;
    SELECT COUNT(*) INTO v_esperadas FROM tmp_sim_matriz;
    SELECT COUNT(*) INTO v_legado_antes FROM tmp_sim_legado m
    JOIN avaliacao_aluno_documentos d ON d.avaliacao_id=4 AND d.aluno_id=m.aluno_id AND d.turma_id=m.turma_origem_id
    WHERE COALESCE(JSON_UNQUOTE(JSON_EXTRACT(d.payload,CONCAT('$.pautas."',m.pauta_id,'".alternativa_id'))),'null') NOT IN ('null','0','');
    DELETE m FROM tmp_sim_matriz m JOIN avaliacao_turma_ciclos c ON c.id=m.ciclo_id
    WHERE c.status='aberta' AND c.operacional_inicializado_em IS NULL;
    SELECT COUNT(*) INTO v_antes FROM tmp_sim_matriz m
    JOIN avaliacao_respostas_operacionais r
      ON r.ciclo_id = m.ciclo_id AND r.aluno_id = m.aluno_id AND r.pauta_id = m.pauta_id
    WHERE r.alternativa_id IS NOT NULL;

    -- Novos documentos somente quando existe pauta elegivel para Sim.
    INSERT INTO avaliacao_aluno_documentos
        (avaliacao_id,aluno_id,cgm,turma_id,escola_id,serie_id,payload,
         alternativa_ids,professor_ids,pauta_ids_respondidas,created_at,updated_at)
    SELECT DISTINCT 4,aln.id,aln.cgm,m.turma_origem_id,t.id_escola,t.id_serie,
           JSON_OBJECT('v',1,'pautas',JSON_OBJECT(),'informacoes_complementares',JSON_OBJECT()),
           JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY(),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP
    FROM tmp_sim_legado m JOIN tmp_sim_opcoes s ON s.pauta_id=m.pauta_id
    JOIN alunos aln ON aln.id=m.aluno_id JOIN turmas t ON t.id=m.turma_origem_id
    WHERE NOT EXISTS (SELECT 1 FROM avaliacao_aluno_documentos d WHERE d.avaliacao_id=4 AND d.aluno_id=m.aluno_id);

    DROP TEMPORARY TABLE IF EXISTS tmp_sim_json;
    CREATE TEMPORARY TABLE tmp_sim_json AS
    SELECT DISTINCT d.id AS documento_id,m.pauta_id,s.alternativa_id,m.componente_curricular_id
    FROM tmp_sim_legado m JOIN tmp_sim_opcoes s ON s.pauta_id=m.pauta_id
    JOIN avaliacao_aluno_documentos d ON d.avaliacao_id=4 AND d.aluno_id=m.aluno_id AND d.turma_id=m.turma_origem_id
    WHERE COALESCE(JSON_UNQUOTE(JSON_EXTRACT(d.payload,CONCAT('$.pautas."',m.pauta_id,'".alternativa_id'))),'null') IN ('null','0','');
    SELECT COUNT(*) INTO v_legado FROM tmp_sim_json;
    DROP TEMPORARY TABLE IF EXISTS tmp_sim_json_grupos;
    CREATE TEMPORARY TABLE tmp_sim_json_grupos AS
    SELECT j.documento_id,JSON_OBJECTAGG(CAST(j.pauta_id AS CHAR),
        JSON_MERGE_PATCH(
            COALESCE(JSON_EXTRACT(d.payload,CONCAT('$.pautas."',j.pauta_id,'"')),JSON_OBJECT()),
            JSON_OBJECT('pauta_id',j.pauta_id,'alternativa_id',j.alternativa_id,
                        'componente_curricular_id',j.componente_curricular_id,
                        'respondido_em',DATE_FORMAT(UTC_TIMESTAMP(),'%Y-%m-%dT%H:%i:%s+00:00'))
        )) AS respostas
    FROM tmp_sim_json j JOIN avaliacao_aluno_documentos d ON d.id=j.documento_id GROUP BY j.documento_id;
    UPDATE avaliacao_aluno_documentos d JOIN tmp_sim_json_grupos g ON g.documento_id=d.id
    SET d.payload=JSON_SET(d.payload,'$.pautas',JSON_MERGE_PATCH(
            IF(JSON_TYPE(JSON_EXTRACT(d.payload,'$.pautas'))='OBJECT',JSON_EXTRACT(d.payload,'$.pautas'),JSON_OBJECT()),g.respostas)),
        d.version=d.version+1,d.updated_at=CURRENT_TIMESTAMP,
        d.primeira_resposta_em=COALESCE(d.primeira_resposta_em,CURRENT_TIMESTAMP),d.ultima_resposta_em=CURRENT_TIMESTAMP;

    DROP TEMPORARY TABLE IF EXISTS tmp_sim_json_itens;
    CREATE TEMPORARY TABLE tmp_sim_json_itens AS
    SELECT d.id AS documento_id,j.pauta_id,j.alternativa_id,
           IF(a.tem_observacao=1 AND TRIM(COALESCE(j.observacao,''))='',1,0) AS pendente
    FROM avaliacao_aluno_documentos d JOIN tmp_sim_json_grupos g ON g.documento_id=d.id
    JOIN JSON_TABLE(d.payload,'$.pautas.*' COLUMNS (
        pauta_id BIGINT PATH '$.pauta_id', alternativa_id BIGINT PATH '$.alternativa_id',
        observacao TEXT PATH '$.observacao' NULL ON EMPTY
    )) j ON TRUE LEFT JOIN alternativas a ON a.id=j.alternativa_id
    WHERE j.alternativa_id > 0;
    DROP TEMPORARY TABLE IF EXISTS tmp_sim_json_metricas;
    CREATE TEMPORARY TABLE tmp_sim_json_metricas AS
    SELECT documento_id,COUNT(*) AS respondidas,SUM(pendente) AS pendentes,JSON_ARRAYAGG(pauta_id) AS pautas
    FROM tmp_sim_json_itens GROUP BY documento_id;
    DROP TEMPORARY TABLE IF EXISTS tmp_sim_json_alternativas;
    CREATE TEMPORARY TABLE tmp_sim_json_alternativas AS
    SELECT documento_id,JSON_ARRAYAGG(alternativa_id) AS alternativas FROM (
        SELECT DISTINCT documento_id,alternativa_id FROM tmp_sim_json_itens
    ) ids GROUP BY documento_id;
    DROP TEMPORARY TABLE IF EXISTS tmp_sim_json_esperadas;
    CREATE TEMPORARY TABLE tmp_sim_json_esperadas AS
    SELECT aluno_id,COUNT(DISTINCT pauta_id) AS esperadas FROM tmp_sim_legado GROUP BY aluno_id;
    UPDATE avaliacao_aluno_documentos d JOIN tmp_sim_json_metricas mt ON mt.documento_id=d.id
    JOIN tmp_sim_json_alternativas a ON a.documento_id=d.id
    JOIN tmp_sim_json_esperadas e ON e.aluno_id=d.aluno_id
    SET d.alternativa_ids=a.alternativas,d.pauta_ids_respondidas=mt.pautas,
        d.total_pautas_esperadas=e.esperadas,
        d.total_pautas_respondidas=mt.respondidas,d.observacoes_obrigatorias_pendentes=mt.pendentes,
        d.status_preenchimento=IF(e.esperadas>0 AND mt.respondidas>=e.esperadas AND mt.pendentes=0,'completo','parcial');
    SELECT COUNT(*) INTO v_legado_depois FROM tmp_sim_legado m
    JOIN avaliacao_aluno_documentos d ON d.avaliacao_id=4 AND d.aluno_id=m.aluno_id AND d.turma_id=m.turma_origem_id
    WHERE COALESCE(JSON_UNQUOTE(JSON_EXTRACT(d.payload,CONCAT('$.pautas."',m.pauta_id,'".alternativa_id'))),'null') NOT IN ('null','0','');
    IF v_legado_depois <> v_legado_antes + v_legado OR EXISTS (
        SELECT 1 FROM tmp_sim_legado m JOIN tmp_sim_opcoes s ON s.pauta_id=m.pauta_id
        LEFT JOIN avaliacao_aluno_documentos d ON d.avaliacao_id=4 AND d.aluno_id=m.aluno_id AND d.turma_id=m.turma_origem_id
        WHERE COALESCE(JSON_UNQUOTE(JSON_EXTRACT(d.payload,CONCAT('$.pautas."',m.pauta_id,'".alternativa_id'))),'null') IN ('null','0','')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Legado divergente ou incompleto. Operacao desfeita.';
    END IF;

    UPDATE avaliacao_respostas_operacionais r
    JOIN tmp_sim_matriz m ON m.ciclo_id = r.ciclo_id AND m.aluno_id = r.aluno_id AND m.pauta_id = r.pauta_id
    JOIN tmp_sim_opcoes s ON s.pauta_id = m.pauta_id
    SET r.alternativa_id = s.alternativa_id, r.respondido_em = CURRENT_TIMESTAMP,
        r.updated_at = CURRENT_TIMESTAMP, r.version = r.version + 1
    WHERE r.alternativa_id IS NULL;
    SET v_vazias = ROW_COUNT();
    INSERT INTO avaliacao_respostas_operacionais (
        token_escrita_id, ciclo_id, avaliacao_id, turma_avaliativa_id,
        turma_origem_id, aluno_id, pauta_id, componente_curricular_id,
        professor_id, alternativa_id, observacao, respondido_em, version, created_at, updated_at
    )
    SELECT m.token_escrita_id, m.ciclo_id, 4, m.turma_avaliativa_id,
           m.turma_origem_id, m.aluno_id, m.pauta_id, m.componente_curricular_id,
           NULL, s.alternativa_id, NULL, CURRENT_TIMESTAMP, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
    FROM tmp_sim_matriz m JOIN tmp_sim_opcoes s ON s.pauta_id = m.pauta_id
    WHERE NOT EXISTS (
        SELECT 1 FROM avaliacao_respostas_operacionais r
        WHERE r.ciclo_id = m.ciclo_id AND r.aluno_id = m.aluno_id AND r.pauta_id = m.pauta_id
    );
    SET v_novas = ROW_COUNT();
    IF EXISTS (
        SELECT 1 FROM tmp_sim_matriz m JOIN tmp_sim_opcoes s ON s.pauta_id = m.pauta_id
        LEFT JOIN avaliacao_respostas_operacionais r
          ON r.ciclo_id = m.ciclo_id AND r.aluno_id = m.aluno_id AND r.pauta_id = m.pauta_id
        WHERE r.alternativa_id IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Restaram pautas elegiveis sem resposta. Operacao desfeita.';
    END IF;
    SELECT COUNT(*) INTO v_depois FROM tmp_sim_matriz m
    JOIN avaliacao_respostas_operacionais r
      ON r.ciclo_id = m.ciclo_id AND r.aluno_id = m.aluno_id AND r.pauta_id = m.pauta_id
    WHERE r.alternativa_id IS NOT NULL;
    IF v_depois <> v_antes + v_novas + v_vazias THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Contagem divergente. Operacao desfeita.';
    END IF;
    COMMIT;
    SELECT 'COMMIT realizado' AS resultado, v_antes+v_legado_antes AS respondidas_antes,
           v_novas AS novas_respostas, v_vazias AS linhas_vazias_preenchidas,
           v_depois+v_legado_depois AS respondidas_depois, v_esperadas AS esperadas,
           v_esperadas-v_depois-v_legado_depois AS restantes_sem_sim,
           ROUND(100.0 * (v_depois+v_legado_depois) / NULLIF(v_esperadas, 0), 2) AS percentual_com_alternativa,
           v_legado AS respostas_sim_no_legado;
    -- Alternativas que exigem observacao podem continuar incompletas no painel.
    DROP TEMPORARY TABLE tmp_sim_opcoes;
    DROP TEMPORARY TABLE tmp_sim_matriz;
    DROP TEMPORARY TABLE tmp_sim_legado;
    DROP TEMPORARY TABLE tmp_sim_json;
    DROP TEMPORARY TABLE tmp_sim_json_grupos;
    DROP TEMPORARY TABLE tmp_sim_json_itens;
    DROP TEMPORARY TABLE tmp_sim_json_metricas;
    DROP TEMPORARY TABLE tmp_sim_json_alternativas;
    DROP TEMPORARY TABLE tmp_sim_json_esperadas;
END$$
DELIMITER ;
CALL preencher_sim_av4_20260909();
DROP PROCEDURE preencher_sim_av4_20260909;
