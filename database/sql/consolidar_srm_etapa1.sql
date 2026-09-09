-- ETAPA 1: consolidacao SRM por escola/turno. MySQL 8 / phpMyAdmin.
-- Execute INTEIRO com backup atualizado e sem usuarios/fila gravando durante a operacao.
-- Nao execute scripts de exclusao depois: envie o backup resultante para a etapa 2.
-- Mantem IDs/status dos alunos; preserva registros antigos e snapshots.
-- Novos destinos/ciclos abertos. Sem professor continua sem professor.
-- Mesma pessoa/usuario com mais de uma matricula: menor ID ativo no destino;
-- autores das respostas e cadastros originais nao sao alterados.
-- DDL de auditoria abaixo fica fora da transacao; falha deixa tabelas vazias.
-- Nao apague as tabelas srm_20260909_*: sao a trilha para a conferencia.
-- Comparacoes explicitas tambem atendem auxiliares ja criadas com utf8mb4_0900_ai_ci.
-- Nao altera a collation das tabelas existentes nem executa ALTER TABLE.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS srm_20260909_execucao (
    id INT NOT NULL PRIMARY KEY, concluida_em DATETIME NULL,
    alunos_movidos INT NOT NULL DEFAULT 0, respostas_movidas INT NOT NULL DEFAULT 0,
    respostas_json INT NOT NULL DEFAULT 0, infos_movidas INT NOT NULL DEFAULT 0,
    infos_json INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS srm_20260909_destinos (
    escola_id BIGINT UNSIGNED NOT NULL, serie_id BIGINT UNSIGNED NOT NULL,
    turno VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, turma_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (escola_id,serie_id,turno), UNIQUE KEY (turma_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS srm_20260909_mapa (
    turma_origem_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    turma_destino_id BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS srm_20260909_ciclos_mapa (
    ciclo_origem_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    ciclo_destino_id BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS srm_20260909_professores (
    turma_id BIGINT UNSIGNED NOT NULL, componente_id BIGINT UNSIGNED NOT NULL,
    professor_id BIGINT UNSIGNED NULL, identidades INT NOT NULL,
    PRIMARY KEY(turma_id,componente_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS srm_20260909_json (
    avaliacao_id BIGINT UNSIGNED NOT NULL, aluno_id BIGINT UNSIGNED NOT NULL,
    ciclo_origem_id BIGINT UNSIGNED NOT NULL, ciclo_destino_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(16) NOT NULL, referencia_id BIGINT UNSIGNED NOT NULL,
    payload JSON NOT NULL, PRIMARY KEY(avaliacao_id,aluno_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS srm_20260909_turmas_antes LIKE turmas;
CREATE TABLE IF NOT EXISTS srm_20260909_alunos_antes LIKE alunos;
CREATE TABLE IF NOT EXISTS srm_20260909_vinculos_antes LIKE turma_componente_professor;
CREATE TABLE IF NOT EXISTS srm_20260909_ciclos_antes LIKE avaliacao_turma_ciclos;
CREATE TABLE IF NOT EXISTS srm_20260909_tokens_antes LIKE avaliacao_turma_tokens_escrita;
CREATE TABLE IF NOT EXISTS srm_20260909_respostas_antes LIKE avaliacao_respostas_operacionais;
CREATE TABLE IF NOT EXISTS srm_20260909_infos_antes LIKE avaliacao_informacoes_operacionais;
CREATE TABLE IF NOT EXISTS srm_20260909_documentos_antes LIKE avaliacao_aluno_documentos;
CREATE TABLE IF NOT EXISTS srm_20260909_snapshots_antes LIKE avaliacao_aluno_snapshots;
CREATE TABLE IF NOT EXISTS srm_20260909_avaliacoes_antes LIKE avaliacoes;
CREATE TABLE IF NOT EXISTS srm_20260909_respostas_json LIKE avaliacao_respostas_operacionais;
CREATE TABLE IF NOT EXISTS srm_20260909_infos_json LIKE avaliacao_informacoes_operacionais;
DROP PROCEDURE IF EXISTS consolidar_srm_etapa1_20260909;
DELIMITER $
CREATE PROCEDURE consolidar_srm_etapa1_20260909()
main: BEGIN
    DECLARE v_lock INT DEFAULT 0;
    DECLARE v_timezone VARCHAR(64);
    DECLARE v_nome_lock VARCHAR(64);
    DECLARE v_alunos INT DEFAULT 0;
    DECLARE v_respostas INT DEFAULT 0;
    DECLARE v_infos INT DEFAULT 0;
    DECLARE v_rjson INT DEFAULT 0;
    DECLARE v_ijson INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        IF v_timezone IS NOT NULL THEN SET SESSION time_zone=v_timezone; END IF;
        DO RELEASE_LOCK(v_nome_lock);
        RESIGNAL;
    END;
    SET v_timezone=@@session.time_zone;
    SET SESSION time_zone='+00:00';
    SET v_nome_lock=CONCAT('srm:',LEFT(SHA2(DATABASE(),256),48));
    SELECT GET_LOCK(v_nome_lock,0) INTO v_lock;
    IF v_lock<>1 OR v_lock IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Outra consolidacao SRM esta em execucao.';
    END IF;
    START TRANSACTION;
    IF EXISTS (SELECT 1 FROM srm_20260909_execucao WHERE id=1 AND concluida_em IS NOT NULL) THEN
        COMMIT;
        SET SESSION time_zone=v_timezone;
        DO RELEASE_LOCK(v_nome_lock);
        SELECT 'ETAPA 1 JA CONCLUIDA: nenhuma alteracao repetida' AS resultado,e.*
        FROM srm_20260909_execucao e WHERE id=1;
        LEAVE main;
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema=DATABASE()
          AND (table_name IN ('alunos','turmas','turma_componente_professor','avaliacoes',
               'avaliacao_turma','avaliacao_turma_ciclos','avaliacao_turma_tokens_escrita',
               'avaliacao_respostas_operacionais','avaliacao_informacoes_operacionais')
               OR LEFT(table_name,13)='srm_20260909_')
          AND engine<>'InnoDB'
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Uma tabela afetada nao e InnoDB. Operacao cancelada.'; END IF;
    IF (SELECT COUNT(*) FROM series WHERE codigo='srm_serie')<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Serie SRM ausente ou ambigua.';
    END IF;
    IF EXISTS (SELECT 1 FROM srm_20260909_alunos_antes)
       OR EXISTS (SELECT 1 FROM srm_20260909_destinos)
       OR EXISTS (SELECT 1 FROM srm_20260909_execucao) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria anterior incompleta: nao reutilizar sem conferir.';
    END IF;
    INSERT INTO srm_20260909_execucao(id) VALUES(1);

    -- Bloqueia os registros de origem sem produzir a lista de IDs na interface.
    SELECT COUNT(*) INTO v_lock FROM turmas t JOIN series s ON s.id=t.id_serie
    WHERE s.codigo='srm_serie' FOR UPDATE;
    SELECT COUNT(*) INTO v_lock FROM alunos a JOIN turmas t ON t.id=a.id_turma
    JOIN series s ON s.id=t.id_serie WHERE s.codigo='srm_serie' FOR UPDATE;
    SELECT COUNT(*) INTO v_lock FROM avaliacao_turma_ciclos c JOIN turmas t ON t.id=c.turma_avaliativa_id
    JOIN series s ON s.id=t.id_serie WHERE s.codigo='srm_serie' FOR UPDATE;
    SELECT COUNT(*) INTO v_lock FROM avaliacao_turma_tokens_escrita tk
    JOIN avaliacao_turma_ciclos c ON c.id=tk.ciclo_id JOIN turmas t ON t.id=c.turma_avaliativa_id
    JOIN series s ON s.id=t.id_serie WHERE s.codigo='srm_serie' FOR UPDATE;
    SELECT COUNT(*) INTO v_lock FROM turma_componente_professor tc JOIN turmas t ON t.id=tc.turma_id
    JOIN series s ON s.id=t.id_serie WHERE s.codigo='srm_serie' FOR UPDATE;

    INSERT INTO srm_20260909_turmas_antes
    -- BEGIN ORIGENS
    SELECT t.* FROM turmas t JOIN series s ON s.id=t.id_serie
    WHERE s.codigo='srm_serie' AND EXISTS (
        SELECT 1 FROM alunos a JOIN turmas ta ON ta.id=a.id_turma
        WHERE ta.id_escola=t.id_escola AND ta.id_serie=t.id_serie
          AND a.status IN ('matriculado','pendente')
    )
    -- END ORIGENS
    ;
    IF NOT EXISTS (SELECT 1 FROM srm_20260909_turmas_antes) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nenhuma turma SRM com alunos ativos encontrada.';
    END IF;
    IF EXISTS (SELECT 1 FROM srm_20260909_turmas_antes WHERE turno NOT IN ('manha','tarde'))
       OR EXISTS (SELECT 1 FROM srm_20260909_turmas_antes WHERE nome IN ('Manhã','Tarde')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Turno inesperado ou turma consolidada ja existente: conferir antes.';
    END IF;
    INSERT INTO srm_20260909_alunos_antes
    SELECT a.* FROM alunos a JOIN srm_20260909_turmas_antes t ON t.id=a.id_turma;
    INSERT INTO srm_20260909_vinculos_antes
    SELECT tc.* FROM turma_componente_professor tc JOIN srm_20260909_turmas_antes t ON t.id=tc.turma_id;
    INSERT INTO srm_20260909_ciclos_antes
    SELECT c.* FROM avaliacao_turma_ciclos c JOIN srm_20260909_turmas_antes t ON t.id=c.turma_avaliativa_id;
    INSERT INTO srm_20260909_tokens_antes
    SELECT tk.* FROM avaliacao_turma_tokens_escrita tk JOIN srm_20260909_ciclos_antes c ON c.id=tk.ciclo_id;
    INSERT INTO srm_20260909_respostas_antes
    SELECT r.* FROM avaliacao_respostas_operacionais r JOIN srm_20260909_alunos_antes a ON a.id=r.aluno_id;
    INSERT INTO srm_20260909_infos_antes
    SELECT i.* FROM avaliacao_informacoes_operacionais i JOIN srm_20260909_alunos_antes a ON a.id=i.aluno_id;
    INSERT INTO srm_20260909_documentos_antes
    SELECT d.* FROM avaliacao_aluno_documentos d JOIN srm_20260909_alunos_antes a ON a.id=d.aluno_id;
    INSERT INTO srm_20260909_snapshots_antes
    SELECT sn.* FROM avaliacao_aluno_snapshots sn JOIN srm_20260909_alunos_antes a ON a.id=sn.aluno_id;
    INSERT INTO srm_20260909_avaliacoes_antes
    SELECT av.* FROM avaliacoes av WHERE EXISTS (
        SELECT 1 FROM avaliacao_turma atv JOIN srm_20260909_turmas_antes t ON t.id=atv.turma_id
        WHERE atv.avaliacao_id=av.id
    );

    IF EXISTS (SELECT 1 FROM srm_20260909_ciclos_antes
               WHERE turma_origem_id<>turma_avaliativa_id OR status NOT IN ('aberta','reaberta','concluida')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ciclo SRM tem origem/status inesperado.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM avaliacao_turma atv JOIN srm_20260909_turmas_antes t ON t.id=atv.turma_id
        LEFT JOIN srm_20260909_ciclos_antes c ON c.avaliacao_id=atv.avaliacao_id AND c.turma_avaliativa_id=t.id
        WHERE c.id IS NULL
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Avaliacao de origem sem ciclo: conferir estrutura.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_respostas_antes r JOIN srm_20260909_alunos_antes a ON a.id=r.aluno_id
        LEFT JOIN srm_20260909_ciclos_antes c ON c.id=r.ciclo_id
        WHERE a.status IN ('matriculado','pendente')
          AND (c.id IS NULL OR r.turma_origem_id<>a.id_turma OR r.turma_avaliativa_id<>a.id_turma
               OR c.status='concluida' OR c.avaliacao_id<>r.avaliacao_id)
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Resposta ativa fora do ciclo esperado. Nenhuma informacao descartada.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_infos_antes r JOIN srm_20260909_alunos_antes a ON a.id=r.aluno_id
        LEFT JOIN srm_20260909_ciclos_antes c ON c.id=r.ciclo_id
        WHERE a.status IN ('matriculado','pendente')
          AND (c.id IS NULL OR r.turma_origem_id<>a.id_turma OR r.turma_avaliativa_id<>a.id_turma
               OR c.status='concluida' OR c.avaliacao_id<>r.avaliacao_id)
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informacao complementar fora do ciclo esperado.'; END IF;

    INSERT INTO srm_20260909_destinos(escola_id,serie_id,turno)
    SELECT DISTINCT t.id_escola,t.id_serie,tr.turno
    FROM srm_20260909_turmas_antes t
    CROSS JOIN (SELECT 'manha' AS turno UNION ALL SELECT 'tarde') tr;
    INSERT INTO turmas(codigo,nome,turno,id_serie,id_escola,created_at,updated_at)
    SELECT UUID(),CASE WHEN turno='manha' THEN 'Manhã' ELSE 'Tarde' END,
           turno,serie_id,escola_id,NOW(),NOW() FROM srm_20260909_destinos;
    UPDATE srm_20260909_destinos d JOIN turmas t
      ON t.id_escola=d.escola_id AND t.id_serie=d.serie_id
     AND t.turno COLLATE utf8mb4_unicode_ci=d.turno COLLATE utf8mb4_unicode_ci
     AND t.nome=CASE WHEN d.turno='manha' THEN 'Manhã' ELSE 'Tarde' END
    SET d.turma_id=t.id;
    INSERT INTO srm_20260909_mapa
    SELECT t.id,d.turma_id FROM srm_20260909_turmas_antes t JOIN srm_20260909_destinos d
      ON d.escola_id=t.id_escola AND d.serie_id=t.id_serie
     AND d.turno COLLATE utf8mb4_unicode_ci=t.turno COLLATE utf8mb4_unicode_ci;

    -- Mantem todos os componentes da serie; sem responsavel nao recebe atribuicao inventada.
    INSERT INTO srm_20260909_professores
    -- BEGIN PROFESSORES
    SELECT d.turma_id,sc.componente_curricular_id,
           COALESCE(MIN(CASE WHEN p.ativo=1 THEN p.id END),MIN(p.id)),
           COUNT(DISTINCT CASE WHEN p.id IS NOT NULL THEN
               CASE WHEN p.user_id IS NOT NULL THEN CONCAT('u:',p.user_id) ELSE CONCAT('p:',p.id) END END)
    FROM srm_20260909_destinos d
    JOIN serie_componente_curricular sc ON sc.serie_id=d.serie_id
    LEFT JOIN srm_20260909_turmas_antes t ON t.id_escola=d.escola_id AND t.id_serie=d.serie_id
      AND t.turno COLLATE utf8mb4_unicode_ci=d.turno COLLATE utf8mb4_unicode_ci
    LEFT JOIN srm_20260909_vinculos_antes tc ON tc.turma_id=t.id
      AND tc.componente_curricular_id=sc.componente_curricular_id AND tc.tem_professor=1
    LEFT JOIN professores p ON p.id=tc.professor_id
    GROUP BY d.turma_id,sc.componente_curricular_id
    -- END PROFESSORES
    ;
    IF EXISTS (SELECT 1 FROM srm_20260909_professores WHERE identidades>1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Mais de um usuario professor no mesmo destino/componente. Operacao desfeita.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_professores p JOIN professores pr ON pr.id=p.professor_id
        JOIN turmas t ON t.id=p.turma_id WHERE pr.id_escola<>t.id_escola
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Professor vinculado pertence a outra escola. Operacao desfeita.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_vinculos_antes tc JOIN srm_20260909_mapa m ON m.turma_origem_id=tc.turma_id
        LEFT JOIN srm_20260909_professores p ON p.turma_id=m.turma_destino_id AND p.componente_id=tc.componente_curricular_id
        WHERE p.turma_id IS NULL
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Componente de origem ausente na serie. Vinculo nao sera descartado.'; END IF;
    INSERT INTO turma_componente_professor(turma_id,componente_curricular_id,professor_id,tem_professor,created_at,updated_at)
    SELECT turma_id,componente_id,professor_id,professor_id IS NOT NULL,NOW(),NOW()
    FROM srm_20260909_professores;

    -- Cada destino recebe as avaliacoes presentes nas turmas originais do mesmo turno.
    INSERT INTO avaliacao_turma(avaliacao_id,turma_id,created_at,updated_at)
    SELECT DISTINCT c.avaliacao_id,m.turma_destino_id,NOW(),NOW()
    FROM srm_20260909_ciclos_antes c JOIN srm_20260909_mapa m ON m.turma_origem_id=c.turma_avaliativa_id;
    INSERT INTO avaliacao_turma_ciclos(avaliacao_id,turma_avaliativa_id,turma_origem_id,status,roster_mode,
                                     operacional_inicializado_em,created_at,updated_at)
    SELECT atv.avaliacao_id,atv.turma_id,atv.turma_id,'aberta','dinamico',NOW(),NOW(),NOW()
    FROM avaliacao_turma atv JOIN srm_20260909_destinos d ON d.turma_id=atv.turma_id;
    INSERT INTO avaliacao_turma_tokens_escrita(ciclo_id,generation_uuid,created_at,updated_at)
    SELECT c.id,UUID(),NOW(),NOW() FROM avaliacao_turma_ciclos c
    JOIN srm_20260909_destinos d ON d.turma_id=c.turma_avaliativa_id;
    INSERT INTO srm_20260909_ciclos_mapa
    SELECT old.id,nc.id FROM srm_20260909_ciclos_antes old
    JOIN srm_20260909_mapa m ON m.turma_origem_id=old.turma_avaliativa_id
    JOIN avaliacao_turma_ciclos nc ON nc.avaliacao_id=old.avaliacao_id AND nc.turma_avaliativa_id=m.turma_destino_id;

    -- Fontes JSON: SOMENTE ciclos ainda nao inicializados e snapshot atual de concluido.
    -- Documentos de outras avaliacoes/JSON obsoleto de ciclos ja migrados ficam preservados.
    INSERT INTO srm_20260909_json
    SELECT c.avaliacao_id,a.id,c.id,cm.ciclo_destino_id,'legado',d.id,d.payload
    FROM srm_20260909_ciclos_antes c JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=c.id
    JOIN srm_20260909_alunos_antes a ON a.id_turma=c.turma_origem_id AND a.status IN ('matriculado','pendente')
    JOIN srm_20260909_documentos_antes d ON d.avaliacao_id=c.avaliacao_id AND d.aluno_id=a.id AND d.turma_id=c.turma_origem_id
    WHERE c.status='aberta' AND c.operacional_inicializado_em IS NULL;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_ciclos_antes c
        JOIN srm_20260909_alunos_antes a ON a.id_turma=c.turma_origem_id AND a.status IN ('matriculado','pendente')
        LEFT JOIN srm_20260909_snapshots_antes sn ON sn.evento_id=c.snapshot_evento_atual_id AND sn.aluno_id=a.id
        WHERE c.status='concluida' AND sn.id IS NULL
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aluno de turma concluida sem snapshot atual. Operacao desfeita.'; END IF;
    INSERT INTO srm_20260909_json
    SELECT c.avaliacao_id,a.id,c.id,cm.ciclo_destino_id,'snapshot',sn.id,sn.payload
    FROM srm_20260909_ciclos_antes c JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=c.id
    JOIN srm_20260909_alunos_antes a ON a.id_turma=c.turma_origem_id AND a.status IN ('matriculado','pendente')
    JOIN srm_20260909_snapshots_antes sn ON sn.evento_id=c.snapshot_evento_atual_id AND sn.aluno_id=a.id
    WHERE c.status='concluida';
    IF EXISTS (SELECT 1 FROM srm_20260909_json
        WHERE (JSON_LENGTH(JSON_EXTRACT(payload,'$.pautas'))>0 AND JSON_TYPE(JSON_EXTRACT(payload,'$.pautas'))<>'OBJECT')
           OR (JSON_LENGTH(JSON_EXTRACT(payload,'$.informacoes_complementares'))>0
               AND JSON_TYPE(JSON_EXTRACT(payload,'$.informacoes_complementares'))<>'OBJECT')
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='JSON fora do formato conhecido. Nenhuma resposta descartada.'; END IF;
    -- O campo componente deve corresponder a chave canonica da informacao.
    IF EXISTS (
        SELECT 1 FROM srm_20260909_json f
        JOIN JSON_TABLE(JSON_KEYS(JSON_EXTRACT(f.payload,'$.informacoes_complementares')),
            '$[*]' COLUMNS(chave VARCHAR(64) PATH '$')) k ON TRUE
        WHERE k.chave NOT REGEXP '^[0-9]+$'
           OR CAST(k.chave AS UNSIGNED)<>COALESCE(CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(
               f.payload,CONCAT('$.informacoes_complementares."',k.chave,'".componente_curricular_id'))),'null') AS UNSIGNED),0)
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chave/componente do JSON divergente. Nenhuma informacao descartada.'; END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_srm_respostas_json;
    CREATE TEMPORARY TABLE tmp_srm_respostas_json (
        avaliacao_id BIGINT UNSIGNED NOT NULL, aluno_id BIGINT UNSIGNED NOT NULL,
        ciclo_id BIGINT UNSIGNED NOT NULL, pauta_id BIGINT UNSIGNED NOT NULL,
        componente_id BIGINT UNSIGNED NULL, professor_id BIGINT UNSIGNED NULL,
        alternativa_id BIGINT UNSIGNED NOT NULL, observacao LONGTEXT NULL,
        data_raw VARCHAR(64) NULL, respondido_em DATETIME NULL,
        PRIMARY KEY(ciclo_id,aluno_id,pauta_id)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    INSERT INTO tmp_srm_respostas_json(avaliacao_id,aluno_id,ciclo_id,pauta_id,componente_id,professor_id,alternativa_id,observacao,data_raw)
    SELECT f.avaliacao_id,f.aluno_id,f.ciclo_destino_id,j.pauta_id,j.componente_id,j.professor_id,j.alternativa_id,j.observacao,j.data_raw
    FROM srm_20260909_json f JOIN JSON_TABLE(f.payload,'$.pautas.*' COLUMNS(
        pauta_id BIGINT PATH '$.pauta_id' ERROR ON EMPTY ERROR ON ERROR,
        componente_id BIGINT PATH '$.componente_curricular_id' NULL ON EMPTY ERROR ON ERROR,
        professor_id BIGINT PATH '$.professor_id' NULL ON EMPTY ERROR ON ERROR,
        alternativa_id BIGINT PATH '$.alternativa_id' NULL ON EMPTY ERROR ON ERROR,
        observacao LONGTEXT PATH '$.observacao' NULL ON EMPTY ERROR ON ERROR,
        data_raw VARCHAR(64) PATH '$.respondido_em' NULL ON EMPTY ERROR ON ERROR
    )) j ON TRUE WHERE j.alternativa_id>0;
    UPDATE tmp_srm_respostas_json SET respondido_em=CASE
        WHEN data_raw IS NULL OR data_raw='' THEN NULL
        WHEN RIGHT(data_raw,1)='Z' THEN STR_TO_DATE(LEFT(data_raw,19),'%Y-%m-%dT%H:%i:%s')
        WHEN RIGHT(data_raw,6) REGEXP '^[+-][0-9]{2}:[0-9]{2}$'
          THEN CONVERT_TZ(STR_TO_DATE(LEFT(data_raw,19),'%Y-%m-%dT%H:%i:%s'),RIGHT(data_raw,6),'+00:00')
        ELSE STR_TO_DATE(REPLACE(LEFT(data_raw,19),'T',' '),'%Y-%m-%d %H:%i:%s')
    END;
    IF EXISTS (SELECT 1 FROM tmp_srm_respostas_json WHERE data_raw IS NOT NULL AND data_raw<>'' AND respondido_em IS NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Data de resposta JSON invalida. Operacao desfeita.';
    END IF;
    DROP TEMPORARY TABLE IF EXISTS tmp_srm_infos_json;
    CREATE TEMPORARY TABLE tmp_srm_infos_json (
        avaliacao_id BIGINT UNSIGNED NOT NULL, aluno_id BIGINT UNSIGNED NOT NULL,
        ciclo_id BIGINT UNSIGNED NOT NULL, componente_id BIGINT UNSIGNED NULL,
        componente_chave BIGINT UNSIGNED NOT NULL, professor_id BIGINT UNSIGNED NULL, texto LONGTEXT NULL,
        PRIMARY KEY(ciclo_id,aluno_id,componente_chave)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    INSERT INTO tmp_srm_infos_json
    SELECT f.avaliacao_id,f.aluno_id,f.ciclo_destino_id,j.componente_id,COALESCE(j.componente_id,0),j.professor_id,j.texto
    FROM srm_20260909_json f JOIN JSON_TABLE(f.payload,'$.informacoes_complementares.*' COLUMNS(
        componente_id BIGINT PATH '$.componente_curricular_id' NULL ON EMPTY ERROR ON ERROR,
        professor_id BIGINT PATH '$.professor_id' NULL ON EMPTY ERROR ON ERROR,
        texto LONGTEXT PATH '$.texto' NULL ON EMPTY ERROR ON ERROR
    )) j ON TRUE WHERE j.texto IS NOT NULL AND TRIM(j.texto)<>'';

    -- Rejeita fontes simultaneas; nao escolhe silenciosamente uma resposta divergente.
    IF EXISTS (
        SELECT 1 FROM tmp_srm_respostas_json j
        JOIN srm_20260909_respostas_antes r ON r.avaliacao_id=j.avaliacao_id AND r.aluno_id=j.aluno_id AND r.pauta_id=j.pauta_id
    ) OR EXISTS (
        SELECT 1 FROM tmp_srm_infos_json j
        JOIN srm_20260909_infos_antes i ON i.avaliacao_id=j.avaliacao_id AND i.aluno_id=j.aluno_id AND i.componente_chave=j.componente_chave
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aluno possui JSON e operacional simultaneos. Conferir conflito antes de consolidar.'; END IF;

    UPDATE avaliacao_respostas_operacionais r
    JOIN srm_20260909_alunos_antes a ON a.id=r.aluno_id AND a.status IN ('matriculado','pendente')
    JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=r.ciclo_id
    JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
    JOIN avaliacao_turma_tokens_escrita tk ON tk.ciclo_id=nc.id
    SET r.ciclo_id=nc.id,r.token_escrita_id=tk.id,r.turma_avaliativa_id=nc.turma_avaliativa_id,
        r.turma_origem_id=nc.turma_origem_id,r.version=r.version+1,r.updated_at=NOW();
    SET v_respostas=ROW_COUNT();
    UPDATE avaliacao_informacoes_operacionais i
    JOIN srm_20260909_alunos_antes a ON a.id=i.aluno_id AND a.status IN ('matriculado','pendente')
    JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=i.ciclo_id
    JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
    JOIN avaliacao_turma_tokens_escrita tk ON tk.ciclo_id=nc.id
    SET i.ciclo_id=nc.id,i.token_escrita_id=tk.id,i.turma_avaliativa_id=nc.turma_avaliativa_id,
        i.turma_origem_id=nc.turma_origem_id,i.version=i.version+1,i.updated_at=NOW();
    SET v_infos=ROW_COUNT();
    INSERT INTO avaliacao_respostas_operacionais(token_escrita_id,ciclo_id,avaliacao_id,turma_avaliativa_id,turma_origem_id,
        aluno_id,pauta_id,componente_curricular_id,professor_id,alternativa_id,observacao,respondido_em,version,created_at,updated_at)
    SELECT tk.id,c.id,j.avaliacao_id,c.turma_avaliativa_id,c.turma_origem_id,
           j.aluno_id,j.pauta_id,j.componente_id,j.professor_id,j.alternativa_id,j.observacao,j.respondido_em,1,NOW(),NOW()
    FROM tmp_srm_respostas_json j JOIN avaliacao_turma_ciclos c ON c.id=j.ciclo_id
    JOIN avaliacao_turma_tokens_escrita tk ON tk.ciclo_id=c.id;
    SET v_rjson=ROW_COUNT();
    INSERT INTO avaliacao_informacoes_operacionais(token_escrita_id,ciclo_id,avaliacao_id,turma_avaliativa_id,turma_origem_id,
        aluno_id,componente_curricular_id,componente_chave,professor_id,texto,version,created_at,updated_at)
    SELECT tk.id,c.id,j.avaliacao_id,c.turma_avaliativa_id,c.turma_origem_id,
           j.aluno_id,j.componente_id,j.componente_chave,j.professor_id,j.texto,1,NOW(),NOW()
    FROM tmp_srm_infos_json j JOIN avaliacao_turma_ciclos c ON c.id=j.ciclo_id
    JOIN avaliacao_turma_tokens_escrita tk ON tk.ciclo_id=c.id;
    SET v_ijson=ROW_COUNT();
    INSERT INTO srm_20260909_respostas_json
    SELECT r.* FROM avaliacao_respostas_operacionais r
    JOIN tmp_srm_respostas_json j ON j.ciclo_id=r.ciclo_id AND j.aluno_id=r.aluno_id AND j.pauta_id=r.pauta_id;
    INSERT INTO srm_20260909_infos_json
    SELECT i.* FROM avaliacao_informacoes_operacionais i
    JOIN tmp_srm_infos_json j ON j.ciclo_id=i.ciclo_id AND j.aluno_id=i.aluno_id AND j.componente_chave=i.componente_chave;

    UPDATE alunos a JOIN srm_20260909_alunos_antes old ON old.id=a.id
    JOIN srm_20260909_mapa m ON m.turma_origem_id=old.id_turma
    SET a.id_turma=m.turma_destino_id,a.updated_at=NOW()
    WHERE old.status IN ('matriculado','pendente');
    SET v_alunos=ROW_COUNT();

    -- Fontes antigas vazias nao podem reimportar JSON nem duplicar o progresso concluido.
    -- O snapshot de conclusao continua integral, com seu evento/hash e turma de origem.
    UPDATE avaliacao_turma_ciclos c JOIN srm_20260909_ciclos_antes old ON old.id=c.id
    SET c.status=IF(old.status='concluida','reaberta',old.status),c.roster_mode='dinamico',
        c.operacional_inicializado_em=COALESCE(c.operacional_inicializado_em,NOW()),
        c.reaberta_em=IF(old.status='concluida',NOW(),c.reaberta_em),
        c.motivo_reabertura=IF(old.status='concluida','Consolidacao SRM por escola e turno: respostas continuadas no novo destino; snapshot original preservado.',c.motivo_reabertura),
        c.updated_at=NOW();
    INSERT INTO avaliacao_turma_tokens_escrita(ciclo_id,generation_uuid,created_at,updated_at)
    SELECT c.id,UUID(),NOW(),NOW() FROM srm_20260909_ciclos_antes c
    WHERE c.status='concluida' AND NOT EXISTS (
        SELECT 1 FROM avaliacao_turma_tokens_escrita tk WHERE tk.ciclo_id=c.id
    );
    UPDATE avaliacoes av JOIN srm_20260909_avaliacoes_antes old ON old.id=av.id
    SET av.status='ativa',av.updated_at=NOW() WHERE av.status='encerrada';

    -- VALIDACOES ANTES DO COMMIT: contagens e conteudo, nao somente percentual.
    IF v_alunos<>(SELECT COUNT(*) FROM srm_20260909_alunos_antes WHERE status IN ('matriculado','pendente'))
       OR (SELECT COUNT(*) FROM srm_20260909_destinos)<>(SELECT 2*COUNT(DISTINCT id_escola) FROM srm_20260909_turmas_antes)
    THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Contagem de alunos/destinos divergente. Operacao desfeita.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_alunos_antes old
        LEFT JOIN alunos a ON a.id=old.id JOIN srm_20260909_mapa m ON m.turma_origem_id=old.id_turma
        WHERE a.id IS NULL OR NOT(a.status<=>old.status) OR NOT(a.cgm<=>old.cgm)
          OR NOT(a.tipo_vinculo<=>old.tipo_vinculo) OR NOT(a.aluno_origem_id<=>old.aluno_origem_id)
          OR NOT(a.pendencia_origem_aluno_id<=>old.pendencia_origem_aluno_id)
          OR NOT(a.id_turma<=>IF(old.status IN ('matriculado','pendente'),m.turma_destino_id,old.id_turma))
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aluno ou historico divergente. Operacao desfeita.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_alunos_antes old JOIN alunos a ON a.id=old.id
        JOIN turmas novo ON novo.id=a.id_turma JOIN srm_20260909_turmas_antes origem ON origem.id=old.id_turma
        WHERE novo.id_escola<>origem.id_escola OR novo.id_serie<>origem.id_serie OR novo.turno<>origem.turno
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Escola, serie ou turno alterado indevidamente. Operacao desfeita.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_respostas_antes old
        JOIN srm_20260909_alunos_antes a ON a.id=old.aluno_id
        LEFT JOIN avaliacao_respostas_operacionais r ON r.id=old.id
        LEFT JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=old.ciclo_id
        LEFT JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
        LEFT JOIN avaliacao_turma_tokens_escrita tk ON tk.ciclo_id=nc.id
        WHERE r.id IS NULL
          OR NOT(r.avaliacao_id<=>old.avaliacao_id)
          OR NOT(r.aluno_id<=>old.aluno_id)
          OR NOT(r.pauta_id<=>old.pauta_id)
          OR NOT(r.componente_curricular_id<=>old.componente_curricular_id)
          OR NOT(r.professor_id<=>old.professor_id)
          OR NOT(r.alternativa_id<=>old.alternativa_id)
          OR NOT(r.observacao<=>old.observacao)
          OR NOT(r.respondido_em<=>old.respondido_em)
          OR NOT(r.created_at<=>old.created_at)
          OR NOT(r.ciclo_id<=>IF(a.status IN ('matriculado','pendente'),nc.id,old.ciclo_id))
          OR NOT(r.token_escrita_id<=>IF(a.status IN ('matriculado','pendente'),tk.id,old.token_escrita_id))
          OR NOT(r.turma_avaliativa_id<=>IF(a.status IN ('matriculado','pendente'),nc.turma_avaliativa_id,old.turma_avaliativa_id))
          OR NOT(r.turma_origem_id<=>IF(a.status IN ('matriculado','pendente'),nc.turma_origem_id,old.turma_origem_id))
          OR NOT(r.version<=>old.version+IF(a.status IN ('matriculado','pendente'),1,0))
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao de respostas falhou. Operacao desfeita.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_infos_antes old
        JOIN srm_20260909_alunos_antes a ON a.id=old.aluno_id
        LEFT JOIN avaliacao_informacoes_operacionais r ON r.id=old.id
        LEFT JOIN srm_20260909_ciclos_mapa cm ON cm.ciclo_origem_id=old.ciclo_id
        LEFT JOIN avaliacao_turma_ciclos nc ON nc.id=cm.ciclo_destino_id
        LEFT JOIN avaliacao_turma_tokens_escrita tk ON tk.ciclo_id=nc.id
        WHERE r.id IS NULL
          OR NOT(r.avaliacao_id<=>old.avaliacao_id)
          OR NOT(r.aluno_id<=>old.aluno_id)
          OR NOT(r.componente_curricular_id<=>old.componente_curricular_id)
          OR NOT(r.componente_chave<=>old.componente_chave)
          OR NOT(r.professor_id<=>old.professor_id)
          OR NOT(r.texto<=>old.texto)
          OR NOT(r.created_at<=>old.created_at)
          OR NOT(r.ciclo_id<=>IF(a.status IN ('matriculado','pendente'),nc.id,old.ciclo_id))
          OR NOT(r.token_escrita_id<=>IF(a.status IN ('matriculado','pendente'),tk.id,old.token_escrita_id))
          OR NOT(r.turma_avaliativa_id<=>IF(a.status IN ('matriculado','pendente'),nc.turma_avaliativa_id,old.turma_avaliativa_id))
          OR NOT(r.turma_origem_id<=>IF(a.status IN ('matriculado','pendente'),nc.turma_origem_id,old.turma_origem_id))
          OR NOT(r.version<=>old.version+IF(a.status IN ('matriculado','pendente'),1,0))
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preservacao de infos falhou. Operacao desfeita.'; END IF;
    IF v_respostas<>(SELECT COUNT(*) FROM srm_20260909_respostas_antes r JOIN srm_20260909_alunos_antes a ON a.id=r.aluno_id WHERE a.status IN ('matriculado','pendente'))
       OR v_infos<>(SELECT COUNT(*) FROM srm_20260909_infos_antes r JOIN srm_20260909_alunos_antes a ON a.id=r.aluno_id WHERE a.status IN ('matriculado','pendente'))
       OR v_rjson<>(SELECT COUNT(*) FROM tmp_srm_respostas_json)
       OR v_ijson<>(SELECT COUNT(*) FROM tmp_srm_infos_json)
    THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Quantidade de respostas/informacoes divergente. Operacao desfeita.'; END IF;
    IF EXISTS (
        SELECT 1 FROM tmp_srm_respostas_json j LEFT JOIN avaliacao_respostas_operacionais r
        ON r.ciclo_id=j.ciclo_id AND r.aluno_id=j.aluno_id AND r.pauta_id=j.pauta_id
        WHERE r.id IS NULL OR NOT(r.alternativa_id<=>j.alternativa_id)
          OR NOT(CAST(r.observacao AS BINARY)<=>CAST(j.observacao AS BINARY)) OR NOT(r.professor_id<=>j.professor_id)
          OR NOT(r.respondido_em<=>j.respondido_em) OR NOT(r.componente_curricular_id<=>j.componente_id)
    ) OR EXISTS (
        SELECT 1 FROM tmp_srm_infos_json j LEFT JOIN avaliacao_informacoes_operacionais r
        ON r.ciclo_id=j.ciclo_id AND r.aluno_id=j.aluno_id AND r.componente_chave=j.componente_chave
        WHERE r.id IS NULL OR NOT(CAST(r.texto AS BINARY)<=>CAST(j.texto AS BINARY)) OR NOT(r.professor_id<=>j.professor_id)
          OR NOT(r.componente_curricular_id<=>j.componente_id)
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reidratacao de JSON/snapshot divergente. Operacao desfeita.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_documentos_antes old LEFT JOIN avaliacao_aluno_documentos d ON d.id=old.id
        WHERE d.id IS NULL OR NOT(d.payload<=>old.payload) OR NOT(d.turma_id<=>old.turma_id)
          OR NOT(d.aluno_id<=>old.aluno_id) OR NOT(d.version<=>old.version)
    ) OR EXISTS (
        SELECT 1 FROM srm_20260909_snapshots_antes old LEFT JOIN avaliacao_aluno_snapshots sn ON sn.id=old.id
        WHERE sn.id IS NULL OR NOT(sn.payload<=>old.payload) OR NOT(sn.payload_hash<=>old.payload_hash)
          OR NOT(sn.evento_id<=>old.evento_id) OR NOT(sn.ciclo_id<=>old.ciclo_id)
          OR NOT(sn.aluno_id<=>old.aluno_id) OR NOT(sn.turma_avaliativa_id<=>old.turma_avaliativa_id)
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Documento ou snapshot historico alterado. Operacao desfeita.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_vinculos_antes old LEFT JOIN turma_componente_professor tc ON tc.id=old.id
        WHERE tc.id IS NULL OR NOT(tc.turma_id<=>old.turma_id)
          OR NOT(tc.professor_id<=>old.professor_id) OR NOT(tc.tem_professor<=>old.tem_professor)
          OR NOT(tc.componente_curricular_id<=>old.componente_curricular_id)
    ) OR EXISTS (
        SELECT 1 FROM srm_20260909_professores p LEFT JOIN turma_componente_professor tc
          ON tc.turma_id=p.turma_id AND tc.componente_curricular_id=p.componente_id
        WHERE tc.id IS NULL OR NOT(tc.professor_id<=>p.professor_id)
          OR tc.tem_professor<>(p.professor_id IS NOT NULL)
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Vinculos de professor divergentes. Operacao desfeita.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_ciclos_mapa cm
        JOIN avaliacao_turma_ciclos c ON c.id=cm.ciclo_destino_id
        LEFT JOIN avaliacao_turma_tokens_escrita tk ON tk.ciclo_id=c.id
        WHERE c.status<>'aberta' OR c.roster_mode<>'dinamico' OR tk.id IS NULL OR c.operacional_inicializado_em IS NULL
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ciclo de destino nao esta pronto para escrita. Operacao desfeita.'; END IF;
    IF EXISTS (
        SELECT 1 FROM srm_20260909_ciclos_antes old JOIN avaliacao_turma_ciclos c ON c.id=old.id
        WHERE c.status='concluida' OR c.operacional_inicializado_em IS NULL
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ciclo antigo ainda pode duplicar o progresso. Operacao desfeita.'; END IF;
    UPDATE srm_20260909_execucao SET concluida_em=NOW(),alunos_movidos=v_alunos,
        respostas_movidas=v_respostas,respostas_json=v_rjson,infos_movidas=v_infos,infos_json=v_ijson WHERE id=1;
    COMMIT;
    SET SESSION time_zone=v_timezone;
    DO RELEASE_LOCK(v_nome_lock);
    SELECT 'COMMIT realizado - consolidacao SRM etapa 1' AS resultado,
           v_alunos AS alunos_movidos,v_respostas AS respostas_operacionais_preservadas,
           v_rjson AS respostas_recuperadas_de_json_ou_snapshot,
           v_infos AS informacoes_operacionais_preservadas,v_ijson AS informacoes_recuperadas_de_json,
           (SELECT COUNT(*) FROM srm_20260909_destinos) AS turmas_destino,
           (SELECT COUNT(*) FROM srm_20260909_snapshots_antes) AS snapshots_originais_preservados;
    SELECT e.nome AS escola,d.turno,t.nome AS turma,d.turma_id,
           COUNT(DISTINCT a.id) AS alunos_ativos,p.professor_id,pr.nome AS professor
    FROM srm_20260909_destinos d JOIN turmas t ON t.id=d.turma_id JOIN escolas e ON e.id=d.escola_id
    LEFT JOIN alunos a ON a.id_turma=d.turma_id AND a.status IN ('matriculado','pendente')
    LEFT JOIN srm_20260909_professores p ON p.turma_id=d.turma_id
    LEFT JOIN professores pr ON pr.id=p.professor_id
    GROUP BY e.nome,d.turno,t.nome,d.turma_id,p.professor_id,pr.nome ORDER BY e.nome,d.turno;
    DROP TEMPORARY TABLE tmp_srm_respostas_json;
    DROP TEMPORARY TABLE tmp_srm_infos_json;
END$$
DELIMITER ;
CALL consolidar_srm_etapa1_20260909();
DROP PROCEDURE consolidar_srm_etapa1_20260909;
