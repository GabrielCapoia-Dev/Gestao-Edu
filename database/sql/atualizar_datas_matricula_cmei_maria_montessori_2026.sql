-- Atualiza a data de matricula dos alunos listados na planilha
-- "Gestao EDU data inicio semestre (1).xls".
--
-- Fonte analisada:
-- - Ano letivo: 2026
-- - Unidade: Centro Municipal de Educação Infantil Maria Montessori
-- - Foram considerados somente registros "MATRIC." da coluna MOVIMENTAÇÃO.
-- - Registros "TR" (transferencia) foram excluidos por nao representarem
--   a data de matricula solicitada.
--
-- Seguranca:
-- - O UPDATE atinge somente a matricula principal atualmente matriculada,
--   identificada por alunos.cgm_matricula_ativa.
-- - Historicos, transferidos, remanejados, pendentes e contra turno nao sao
--   atualizados.
-- - Execute todo o arquivo na mesma sessao, pois ele usa tabela temporaria.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP TEMPORARY TABLE IF EXISTS tmp_datas_matricula_montessori_2026;

CREATE TEMPORARY TABLE tmp_datas_matricula_montessori_2026 (
    cgm VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY,
    data_matricula DATE NOT NULL,
    turma_planilha VARCHAR(50) NOT NULL,
    nome_planilha VARCHAR(255) NOT NULL
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO tmp_datas_matricula_montessori_2026
    (cgm, data_matricula, turma_planilha, nome_planilha)
VALUES
    ('1036337849', '2026-04-15', 'BERÇ. A', 'MARIA CECÍLIA DA SILVA SABINO'),
    ('1036560742', '2026-06-30', 'BERÇ. A', 'AYLLA MIKAELLY BOLETA DOS SANTOS MEDEIROS'),
    ('1036465960', '2026-05-25', 'INF. 1 B', 'MATTEO MACEDO DOS SANTOS'),
    ('1036077839', '2026-02-26', 'INF. 3 E', 'MATTEO MARAGNO MARQUES'),
    ('1032503353', '2026-03-05', 'INF. 3 E', 'SOPHIA VALENTYNA MIRANDA DOS SANTOS'),
    ('1036261990', '2026-03-26', 'INF. 1 G', 'ANDERSON MOREAU DUPENOR'),
    ('1036563067', '2026-07-01', 'INF. 1 G', 'AGATHA SILVA ROMÃO'),
    ('1035731853', '2026-07-01', 'INF. 1 G', 'HEITOR GABRIEL SOUZA ALMEIDA'),
    ('1036082433', '2026-02-25', 'INF. 2 I', 'DAVI HRASTEL SANTOS'),
    ('1032622565', '2026-04-15', 'INF. 2 I', 'THAEMILLY EMANUELLY PAWLIKOWSKI HONTIARTTI'),
    ('1036077510', '2026-02-25', 'INF. 3 J', 'NYCOLAS THAUÃ DOS SANTOS'),
    ('1036078827', '2026-02-26', 'INF. 3 J', 'LEVI DA SILVA DIAS'),
    ('1036345485', '2026-04-16', 'INF. 3 J', 'ERICK VINÍCIUS RODRIGUES ANDRADE'),
    ('1036428933', '2026-05-13', 'INF. 3 J', 'HELENA SILVA GUIMARÃES'),
    ('1036248293', '2026-03-24', 'INF. 3 K', 'DANIEL DE CASTRO LIMA FERNANDES'),
    ('1036341137', '2026-04-15', 'INF. 3 K', 'SAMUEL DE CASTRO LIMA FERNANDES'),
    ('1033609260', '2026-07-01', 'INF. 3 K', 'MARIA ISIS ALMEIDA SOUZA');

-- 1) Conferencia da fonte: deve retornar 17 registros e nenhuma duplicidade,
-- pois cgm e a chave primaria da tabela temporaria.
SELECT
    COUNT(*) AS total_matriculas_planilha,
    MIN(data_matricula) AS primeira_data,
    MAX(data_matricula) AS ultima_data
FROM tmp_datas_matricula_montessori_2026;

-- 2) Diagnostico antes da alteracao.
-- "matriculas_ativas_nao_encontradas" deve ser 0 para que todos os CGMs
-- tenham uma matricula principal ativa no banco.
SELECT
    COUNT(*) AS total_planilha,
    COUNT(a.id) AS matriculas_ativas_encontradas,
    COUNT(*) - COUNT(a.id) AS matriculas_ativas_nao_encontradas,
    SUM(
        CASE
            WHEN a.id IS NOT NULL
                AND NOT (a.data_matricula <=> origem.data_matricula)
            THEN 1
            ELSE 0
        END
    ) AS datas_que_serao_alteradas
FROM tmp_datas_matricula_montessori_2026 origem
LEFT JOIN alunos a
    ON a.cgm_matricula_ativa = origem.cgm
    AND a.tipo_vinculo = 'principal'
    AND a.status = 'matriculado';

-- 3) Relacao nominal para conferencia antes do UPDATE.
SELECT
    origem.turma_planilha,
    origem.cgm,
    origem.nome_planilha,
    a.id AS aluno_id,
    a.nome AS nome_banco,
    a.status,
    a.data_matricula AS data_atual,
    origem.data_matricula AS nova_data,
    CASE
        WHEN a.id IS NULL THEN 'MATRICULA ATIVA NAO ENCONTRADA'
        WHEN a.data_matricula <=> origem.data_matricula THEN 'JA ESTAVA CORRETA'
        ELSE 'SERA ATUALIZADA'
    END AS resultado_previsto
FROM tmp_datas_matricula_montessori_2026 origem
LEFT JOIN alunos a
    ON a.cgm_matricula_ativa = origem.cgm
    AND a.tipo_vinculo = 'principal'
    AND a.status = 'matriculado'
ORDER BY origem.turma_planilha, origem.nome_planilha;

START TRANSACTION;

-- 4) Atualizacao restrita a matricula principal ativa.
UPDATE alunos a
INNER JOIN tmp_datas_matricula_montessori_2026 origem
    ON origem.cgm = a.cgm_matricula_ativa
SET a.data_matricula = origem.data_matricula
WHERE
    a.tipo_vinculo = 'principal'
    AND a.status = 'matriculado'
    AND NOT (a.data_matricula <=> origem.data_matricula);

SELECT ROW_COUNT() AS datas_matricula_atualizadas;

-- 5) Validacao final.
-- "matriculas_ativas_nao_encontradas" e "datas_divergentes" devem ser 0.
SELECT
    SUM(CASE WHEN a.id IS NULL THEN 1 ELSE 0 END)
        AS matriculas_ativas_nao_encontradas,
    SUM(
        CASE
            WHEN a.id IS NOT NULL
                AND NOT (a.data_matricula <=> origem.data_matricula)
            THEN 1
            ELSE 0
        END
    ) AS datas_divergentes
FROM tmp_datas_matricula_montessori_2026 origem
LEFT JOIN alunos a
    ON a.cgm_matricula_ativa = origem.cgm
    AND a.tipo_vinculo = 'principal'
    AND a.status = 'matriculado';

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_datas_matricula_montessori_2026;
