/*
  Vínculo de servidores com lotações pela matrícula.

  Pré-requisito: preencher a tabela temporária abaixo com os dados da planilha.
  A planilha precisa ter, no mínimo:
    - matricula: matrícula do servidor
    - numero_lotacao: código da lotação, por exemplo 013002665

  Este script atualiza servidores.lotacao_id, que é a lotação principal do
  servidor. A tabela professor_matriculas não possui lotacao_id no banco atual.
*/

START TRANSACTION;

CREATE TEMPORARY TABLE tmp_servidor_lotacao (
    matricula VARCHAR(255) NOT NULL,
    numero_lotacao VARCHAR(100) NOT NULL,
    PRIMARY KEY (matricula),
    KEY idx_tmp_numero_lotacao (numero_lotacao)
) ENGINE=InnoDB;

/* Exemplo. Substitua pelos dados reais da planilha.
INSERT INTO tmp_servidor_lotacao (matricula, numero_lotacao) VALUES
    ('1081932', '013002665'),
    ('1082982', '013002666');
*/

/* 1) Matrículas da planilha sem servidor correspondente. */
SELECT
    t.matricula,
    t.numero_lotacao,
    'MATRICULA_NAO_ENCONTRADA' AS problema
FROM tmp_servidor_lotacao t
LEFT JOIN servidores s
    ON TRIM(s.matricula) = TRIM(t.matricula)
WHERE s.id IS NULL;

/* 2) Códigos de lotação inexistentes. */
SELECT
    t.matricula,
    t.numero_lotacao,
    'LOTACAO_NAO_ENCONTRADA' AS problema
FROM tmp_servidor_lotacao t
LEFT JOIN lotacoes l
    ON TRIM(l.codigo) = TRIM(t.numero_lotacao)
WHERE l.id IS NULL;

/* 3) Protege contra uma matrícula associada a servidores diferentes. */
SELECT
    TRIM(s.matricula) AS matricula,
    COUNT(*) AS quantidade_servidores,
    'MATRICULA_DUPLICADA_NA_TABELA_SERVIDORES' AS problema
FROM servidores s
JOIN tmp_servidor_lotacao t
    ON TRIM(s.matricula) = TRIM(t.matricula)
GROUP BY TRIM(s.matricula)
HAVING COUNT(*) > 1;

/* 4) Protege contra o mesmo código de lotação pertencente a locais diferentes. */
SELECT
    l.codigo AS numero_lotacao,
    COUNT(DISTINCT l.escola_id) AS quantidade_locais,
    'LOTACAO_DUPLICADA_EM_LOCAIS' AS problema
FROM lotacoes l
JOIN tmp_servidor_lotacao t
    ON TRIM(l.codigo) = TRIM(t.numero_lotacao)
GROUP BY l.codigo
HAVING COUNT(DISTINCT l.escola_id) > 1;

/* 5) Prévia dos vínculos que serão gravados. */
SELECT
    s.id AS servidor_id,
    s.matricula,
    s.nome AS servidor,
    s.lotacao_id AS lotacao_atual_id,
    l.id AS nova_lotacao_id,
    l.codigo AS numero_lotacao,
    l.nome AS lotacao
FROM tmp_servidor_lotacao t
JOIN servidores s
    ON TRIM(s.matricula) = TRIM(t.matricula)
JOIN lotacoes l
    ON TRIM(l.codigo) = TRIM(t.numero_lotacao)
WHERE s.deleted_at IS NULL;

/*
  6) Atualização segura.
  O WHERE NOT EXISTS impede executar a atualização se houver qualquer
  matrícula/código inválido ou matrícula duplicada em servidores.
*/
UPDATE servidores s
JOIN tmp_servidor_lotacao t
    ON TRIM(s.matricula) = TRIM(t.matricula)
JOIN lotacoes l
    ON TRIM(l.codigo) = TRIM(t.numero_lotacao)
SET
    s.lotacao_id = l.id,
    s.updated_at = CURRENT_TIMESTAMP
WHERE s.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM tmp_servidor_lotacao x
      LEFT JOIN servidores sx
          ON TRIM(sx.matricula) = TRIM(x.matricula)
         AND sx.deleted_at IS NULL
      LEFT JOIN lotacoes lx
          ON TRIM(lx.codigo) = TRIM(x.numero_lotacao)
      WHERE sx.id IS NULL OR lx.id IS NULL
  )
  AND NOT EXISTS (
      SELECT 1
      FROM servidores sd
      WHERE sd.deleted_at IS NULL
        AND TRIM(sd.matricula) = TRIM(s.matricula)
      GROUP BY TRIM(sd.matricula)
      HAVING COUNT(*) > 1
  );

SELECT ROW_COUNT() AS servidores_atualizados;

/*
  Revise os SELECTs e o ROW_COUNT().
  Se estiver correto, confirme:
    COMMIT;
  Se houver qualquer problema:
    ROLLBACK;
*/

ROLLBACK;
