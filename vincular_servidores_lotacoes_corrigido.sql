/* Execute o arquivo inteiro. Preencha o INSERT com matricula e numero_lotacao. */
START TRANSACTION;

CREATE TABLE IF NOT EXISTS import_servidor_lotacao_staging (
    matricula VARCHAR(255) NOT NULL,
    numero_lotacao VARCHAR(100) NOT NULL,
    PRIMARY KEY (matricula),
    KEY idx_import_lotacao (numero_lotacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE FROM import_servidor_lotacao_staging;

INSERT INTO import_servidor_lotacao_staging (matricula, numero_lotacao) VALUES
    ('1081932', '013002665'),
    ('1082982', '013002666');

SELECT i.matricula, i.numero_lotacao, 'MATRICULA_NAO_ENCONTRADA' AS problema
FROM import_servidor_lotacao_staging i
LEFT JOIN servidores s ON TRIM(s.matricula) = TRIM(i.matricula) AND s.deleted_at IS NULL
WHERE s.id IS NULL;

SELECT i.matricula, i.numero_lotacao, 'LOTACAO_NAO_ENCONTRADA' AS problema
FROM import_servidor_lotacao_staging i
LEFT JOIN lotacoes l ON TRIM(l.codigo) = TRIM(i.numero_lotacao)
WHERE l.id IS NULL;

SELECT TRIM(s.matricula) AS matricula, COUNT(*) AS quantidade_servidores,
       'MATRICULA_DUPLICADA_NA_TABELA_SERVIDORES' AS problema
FROM servidores s
INNER JOIN import_servidor_lotacao_staging i ON TRIM(i.matricula) = TRIM(s.matricula)
WHERE s.deleted_at IS NULL
GROUP BY TRIM(s.matricula)
HAVING COUNT(*) > 1;

SELECT TRIM(l.codigo) AS numero_lotacao, COUNT(DISTINCT l.escola_id) AS quantidade_locais,
       'LOTACAO_DUPLICADA_EM_LOCAIS' AS problema
FROM lotacoes l
INNER JOIN import_servidor_lotacao_staging i ON TRIM(i.numero_lotacao) = TRIM(l.codigo)
GROUP BY TRIM(l.codigo)
HAVING COUNT(DISTINCT l.escola_id) > 1;

SELECT s.id AS servidor_id, s.matricula, s.nome AS servidor,
       s.lotacao_id AS lotacao_atual_id, l.id AS nova_lotacao_id,
       l.codigo AS numero_lotacao, l.nome AS lotacao
FROM import_servidor_lotacao_staging i
INNER JOIN servidores s ON TRIM(s.matricula) = TRIM(i.matricula) AND s.deleted_at IS NULL
INNER JOIN lotacoes l ON TRIM(l.codigo) = TRIM(i.numero_lotacao);

UPDATE servidores s
INNER JOIN import_servidor_lotacao_staging i ON TRIM(i.matricula) = TRIM(s.matricula)
INNER JOIN lotacoes l ON TRIM(l.codigo) = TRIM(i.numero_lotacao)
SET s.lotacao_id = l.id, s.updated_at = CURRENT_TIMESTAMP
WHERE s.deleted_at IS NULL;

SELECT ROW_COUNT() AS servidores_atualizados;

/* Após revisar os resultados, troque ROLLBACK por COMMIT. */
ROLLBACK;
