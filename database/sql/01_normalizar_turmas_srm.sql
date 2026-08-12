-- Normaliza as turmas de Sala de Recursos Multifuncionais (SRM).
-- Compatibilidade: MySQL 8.0+.
--
-- Resultado esperado por escola:
--   SRM | Turma A | manha
--   SRM | Turma B | tarde
--
-- O escopo inclui:
-- - escolas que ja possuem alguma turma da serie SRM; e
-- - escolas presentes na aba Matriculados da planilha de 12/08/2026.
--
-- O script nao apaga nem funde turmas. Se encontrar duas turmas SRM no mesmo
-- turno da mesma escola, uma escola ausente ou turno invalido, ele interrompe
-- antes de alterar qualquer registro.

SET NAMES utf8mb4;

DROP TEMPORARY TABLE IF EXISTS tmp_srm_escolas_planilha;
CREATE TEMPORARY TABLE tmp_srm_escolas_planilha (
    escola_nome varchar(255) NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO tmp_srm_escolas_planilha (escola_nome) VALUES
('ESCOLA - Analides de Oliveira Caruso'),
('ESCOLA - Cândido Portinari'),
('ESCOLA - Dr. Ângelo Moreira da Fonseca'),
('ESCOLA - Dr. Germano Norberto Rudner'),
('ESCOLA - Jardim União'),
('ESCOLA - Malba Tahan'),
('ESCOLA - Manuel Bandeira'),
('ESCOLA - Ouro Branco'),
('ESCOLA - Padre José de Anchieta'),
('ESCOLA - Papa Pio XII'),
('ESCOLA - Paulo Freire'),
('ESCOLA - Rui Barbosa'),
('ESCOLA - São Cristóvão'),
('ESCOLA - São Francisco de Assis'),
('ESCOLA - Sebastião de Mattos'),
('ESCOLA - Senador Souza Naves'),
('ESCOLA - Serra dos Dourados'),
('ESCOLA - Vinicius de Morais');

SET @srm_serie_matches := (
    SELECT COUNT(*)
    FROM series
    WHERE codigo = 'srm_serie'
       OR nome = 'Sala de Recursos Multifuncionais'
);

SET @srm_serie_id := (
    SELECT id
    FROM series
    WHERE codigo = 'srm_serie'
       OR nome = 'Sala de Recursos Multifuncionais'
    ORDER BY (codigo = 'srm_serie') DESC, id
    LIMIT 1
);

DROP TEMPORARY TABLE IF EXISTS tmp_srm_escolas_alvo;
CREATE TEMPORARY TABLE tmp_srm_escolas_alvo (
    escola_id bigint unsigned NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT IGNORE INTO tmp_srm_escolas_alvo (escola_id)
SELECT DISTINCT id_escola
FROM turmas
WHERE id_serie = @srm_serie_id;

INSERT IGNORE INTO tmp_srm_escolas_alvo (escola_id)
SELECT e.id
FROM tmp_srm_escolas_planilha p
JOIN escolas e ON BINARY e.nome = BINARY p.escola_nome;

SET @srm_escolas_planilha_ausentes := (
    SELECT COUNT(*)
    FROM tmp_srm_escolas_planilha p
    WHERE (SELECT COUNT(*) FROM escolas e WHERE BINARY e.nome = BINARY p.escola_nome) <> 1
);

SET @srm_turmas_turno_duplicado := (
    SELECT COUNT(*)
    FROM (
        SELECT t.id_escola, t.turno
        FROM turmas t
        JOIN tmp_srm_escolas_alvo alvo ON alvo.escola_id = t.id_escola
        WHERE t.id_serie = @srm_serie_id
          AND t.turno IN ('manha', 'tarde')
        GROUP BY t.id_escola, t.turno
        HAVING COUNT(*) > 1
    ) duplicadas
);

SET @srm_turmas_turno_invalido := (
    SELECT COUNT(*)
    FROM turmas t
    JOIN tmp_srm_escolas_alvo alvo ON alvo.escola_id = t.id_escola
    WHERE t.id_serie = @srm_serie_id
      AND t.turno NOT IN ('manha', 'tarde')
);

SET @srm_total_erros :=
      IF(@srm_serie_matches = 1, 0, 1)
    + IF(@srm_escolas_planilha_ausentes = 0, 0, 1)
    + IF(@srm_turmas_turno_duplicado = 0, 0, 1)
    + IF(@srm_turmas_turno_invalido = 0, 0, 1);

DROP TEMPORARY TABLE IF EXISTS tmp_srm_guard;
CREATE TEMPORARY TABLE tmp_srm_guard (
    total_erros int NOT NULL,
    CONSTRAINT chk_tmp_srm_sem_erros CHECK (total_erros = 0)
) ENGINE=InnoDB;

-- Interrompe explicitamente antes de qualquer escrita quando a estrutura e ambigua.
INSERT INTO tmp_srm_guard (total_erros) VALUES (@srm_total_erros);
DROP TEMPORARY TABLE tmp_srm_guard;

START TRANSACTION;

UPDATE turmas t
JOIN tmp_srm_escolas_alvo alvo ON alvo.escola_id = t.id_escola
SET t.nome = CASE t.turno
        WHEN 'manha' THEN 'A'
        WHEN 'tarde' THEN 'B'
    END,
    t.updated_at = NOW()
WHERE t.id_serie = @srm_serie_id
  AND t.turno IN ('manha', 'tarde')
  AND BINARY t.nome <> BINARY (
      CASE t.turno
          WHEN 'manha' THEN 'A'
          WHEN 'tarde' THEN 'B'
      END
  );

INSERT INTO turmas
    (codigo, nome, turno, id_serie, id_escola, created_at, updated_at)
SELECT
    UUID(),
    turno_desejado.nome,
    turno_desejado.turno,
    @srm_serie_id,
    alvo.escola_id,
    NOW(),
    NOW()
FROM tmp_srm_escolas_alvo alvo
CROSS JOIN (
    SELECT 'A' AS nome, 'manha' AS turno
    UNION ALL
    SELECT 'B', 'tarde'
) turno_desejado
WHERE NOT EXISTS (
    SELECT 1
    FROM turmas existente
    WHERE existente.id_escola = alvo.escola_id
      AND existente.id_serie = @srm_serie_id
      AND BINARY existente.turno = BINARY turno_desejado.turno
);

-- Inclui as turmas SRM criadas agora nas avaliacoes que ja selecionaram
-- simultaneamente a serie SRM e a respectiva escola.
INSERT IGNORE INTO avaliacao_turma
    (avaliacao_id, turma_id, created_at, updated_at)
SELECT
    avs.avaliacao_id,
    t.id,
    NOW(),
    NOW()
FROM avaliacao_serie avs
JOIN avaliacao_escola ave
  ON ave.avaliacao_id = avs.avaliacao_id
JOIN turmas t
  ON t.id_serie = avs.serie_id
 AND t.id_escola = ave.escola_id
JOIN tmp_srm_escolas_alvo alvo ON alvo.escola_id = t.id_escola
WHERE avs.serie_id = @srm_serie_id
  AND t.turno IN ('manha', 'tarde');

COMMIT;

-- Unico conjunto de resultados do script, conforme solicitado.
SELECT
    e.nome AS `Escola`,
    'Sala de Recursos Multifuncionais' AS `SRM`,
    t.nome AS `Turma`,
    CASE t.turno
        WHEN 'manha' THEN 'Manha'
        WHEN 'tarde' THEN 'Tarde'
        ELSE t.turno
    END AS `Turno`
FROM tmp_srm_escolas_alvo alvo
JOIN escolas e ON e.id = alvo.escola_id
JOIN turmas t
  ON t.id_escola = alvo.escola_id
 AND t.id_serie = @srm_serie_id
WHERE t.turno IN ('manha', 'tarde')
ORDER BY e.nome, FIELD(t.turno, 'manha', 'tarde');

DROP TEMPORARY TABLE IF EXISTS tmp_srm_escolas_alvo;
DROP TEMPORARY TABLE IF EXISTS tmp_srm_escolas_planilha;
