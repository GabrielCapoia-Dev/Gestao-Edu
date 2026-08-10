-- Preenche somente a fonte canonica de matriculas da Equipe Gestora.
--
-- Fontes analisadas em 2026-08-05:
-- - dump gestao-edu.sql;
-- - planilha Teste da API nova.xlsx, aba Pagina1.
--
-- Resultado do cruzamento:
-- - 101 pessoas com vinculo ativo de Equipe Gestora;
-- - 6 ja tinham matricula canonica;
-- - 95 nao tinham registro em professor_matriculas;
-- - 93 foram localizadas na planilha e estao mapeadas abaixo;
-- - 2 nao foram localizadas e nao serao alteradas:
--   servidor 1592 / matricula legada 1231231;
--   servidor 1593 / matricula legada 1231232.
--
-- O turno foi inferido exclusivamente de horarioTrabalho:
-- - somente periodo matutino: manha;
-- - somente periodo vespertino: tarde;
-- - periodos matutino e vespertino: integral.
--
-- Seguranca:
-- - insere apenas em professor_matriculas;
-- - nao altera servidores, vinculos, escolas, usuarios, permissoes ou historicos;
-- - exige que ID, nome e matricula legada ainda correspondam ao dump analisado;
-- - exige vinculo ativo com funcao marcada como Equipe Gestora;
-- - nao insere se a pessoa ja possuir qualquer matricula canonica;
-- - se qualquer um dos 93 mapeamentos estiver divergente, nenhuma linha e inserida;
-- - pode ser executado novamente sem duplicar registros.
--
-- Atencao ao servidor 1605: o banco tinha matricula legada 891701, mas a linha
-- 2258 da planilha informa 998701 para Maria Jose Pereira da Silva. O script
-- cadastra 998701 apenas em professor_matriculas e preserva o valor legado.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP TEMPORARY TABLE IF EXISTS tmp_matriculas_equipe_gestora_api;

CREATE TEMPORARY TABLE tmp_matriculas_equipe_gestora_api (
    servidor_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    nome_banco VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    matricula_legada VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    matricula_api VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    turno VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    linha_planilha INT UNSIGNED NOT NULL,
    divergencia_documentada TINYINT(1) NOT NULL DEFAULT 0
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO tmp_matriculas_equipe_gestora_api
    (servidor_id, nome_banco, matricula_legada, matricula_api, turno, linha_planilha, divergencia_documentada)
VALUES
    (1269, 'Cristiane Fabiano da Silva Merisse Joaquim', '975602', '975602', 'manha', 644, 0),
    (1270, 'Luciana Costa de Souza dos Santos', '940801', '940801', 'integral', 1996, 0),
    (1271, 'CLAUDIA MARIA DA SILVA AGUIAR', '998241', '998241', 'manha', 573, 0),
    (1272, 'Maria Ivonete Lopes', '976161', '976161', 'integral', 2250, 0),
    (1273, 'Luriane Rafaela dos Santos de Melo', '1000711', '1000711', 'integral', 2071, 0),
    (1274, 'Luzinete Cesario', '900691', '900691', 'integral', 2083, 0),
    (1275, 'Fabricia Alessandra Garcia Mello de Oliveira', '1008467', '1008467', 'tarde', 1131, 0),
    (1277, 'Daniele Fernanda Contato Gimenes', '1001001', '1001001', 'integral', 729, 0),
    (1278, 'Patricia Santos de Paiva Barzon', '977211', '977211', 'integral', 2576, 0),
    (1279, 'Isabel Cristina Mendes Maiante', '1000771', '1000771', 'integral', 1441, 0),
    (1282, 'Andrea Dias Lopes', '998201', '998201', 'manha', 285, 0),
    (1283, 'Elaine Natalina Ferrarezi', '861772', '861772', 'tarde', 959, 0),
    (1285, 'Thuany Soares Teixeira', '1001041', '1001041', 'integral', 3127, 0),
    (1286, 'Anália Libanio dos Santos Heins', '977561', '977561', 'integral', 260, 0),
    (1287, 'Maria Madalena Jose Pereira', '900771', '900771', 'integral', 2270, 0),
    (1288, 'Isabela de Souza Vigo Chimenes', '1000651', '1000651', 'integral', 1447, 0),
    (1289, 'Ana Carolina Andrade Caobianco Castaldo', '1000491', '1000491', 'integral', 193, 0),
    (1290, 'Shirlei Cordeiro', '901741', '901741', 'integral', 2897, 0),
    (1291, 'Kelly Cristine da Silva', '1001361', '1001361', 'integral', 1803, 0),
    (1292, 'Pricilla Ribeiro de Queiros', '900931', '900931', 'integral', 2605, 0),
    (1293, 'Ana Ligia de Oliveira Sarmento Binati', '977641', '977641', 'integral', 219, 0),
    (1294, 'Amanda Delgado Banhara', '1000741', '1000741', 'integral', 173, 0),
    (1295, 'Sara Hungaro Lazaretti', '999691', '999691', 'integral', 2873, 0),
    (1296, 'Desirre Beatriz Ramos Marcelino Ziroldo', '1000731', '1000731', 'integral', 820, 0),
    (1297, 'Mariane Vinha Julião', '1001081', '1001081', 'integral', 2312, 0),
    (1298, 'Adriana Patricia Landim', '1001381', '1001381', 'integral', 47, 0),
    (1299, 'Analu Aparecida Lourenço da Cunha Ribeiro', '975431', '975431', 'integral', 261, 0),
    (1300, 'Marcia Tiago de Sá', '978451', '978451', 'integral', 2140, 0),
    (1301, 'PATRICIA MENEGASSI', '1001061', '1001061', 'integral', 2568, 0),
    (1302, 'Daniela Andreia de Souza', '999621', '999621', 'manha', 706, 0),
    (1304, 'Thais Vilalva Furlan', '982642', '982642', 'tarde', 3109, 0),
    (1306, 'Adriana Regina Pensin de Oliveira', '864102', '864102', 'tarde', 50, 0),
    (1308, 'Raimunda Maria da Silva', '792431', '792431', 'manha', 2637, 0),
    (1309, 'Ana Cristina de Oliveira Garcia', '997551', '997551', 'manha', 209, 0),
    (1310, 'Fabiana Felix de Araujo Oliveira', '1082270', '1082270', 'tarde', 1110, 0),
    (1311, 'Iara Jane Doenia Mandotti', '827404', '827404', 'tarde', 1421, 0),
    (1313, 'Lerci de Jesus', '885282', '885282', 'integral', 1907, 0),
    (1314, 'Bruna Izabelly Martin Antonio Herrera', '1000261', '1000261', 'manha', 443, 0),
    (1315, 'Vera Lucia da Silva', '997642', '997642', 'manha', 3230, 0),
    (1316, 'Andreia dos Santos', '926651', '926651', 'manha', 307, 0),
    (1317, 'Andrea Maria Lazof Benedito Santana', '855882', '855882', 'tarde', 287, 0),
    (1318, 'Maria Emilia de Araujo Sousa', '796262', '796262', 'tarde', 2233, 0),
    (1319, 'Rosangela Aparecida Marques de Morais Bragatto', '857662', '857662', 'manha', 2756, 0),
    (1320, 'Mariana Caroline de Oliveira Silva', '1082244', '1082244', 'manha', 2298, 0),
    (1321, 'Amanda Cristina Sousa de Oliveira Gonzaga', '939711', '939711', 'manha', 166, 0),
    (1323, 'Marinez Vanusa Lima Guebara', '995622', '995622', 'tarde', 2337, 0),
    (1325, 'Eliane Zamberlan Rocha Grossi', '898002', '898002', 'tarde', 989, 0),
    (1332, 'Kelen Cristina Fracassi', '991471', '991471', 'manha', 1800, 0),
    (1337, 'Lucilene Soares de Souza', '615144', '615144', 'tarde', 2037, 0),
    (1346, 'Fabricia Silva de Melo Ricas', '896642', '896642', 'tarde', 1134, 0),
    (1347, 'Elizabete Grando', '896131', '896131', 'tarde', 1017, 0),
    (1348, 'Juliana Angelina Lavagnini', '997422', '997422', 'tarde', 1691, 0),
    (1351, 'Adriana Mafalda da Silva Bergamo', '1000281', '1000281', 'manha', 43, 0),
    (1356, 'Marta Regina Batista Evangelista', '990311', '990311', 'manha', 2365, 0),
    (1358, 'Ana Maria Lima Morais', '882691', '882691', 'manha', 227, 0),
    (1359, 'Ana Paula Silva Menegasso', '1008540', '1008540', 'manha', 237, 0),
    (1360, 'Olinda de Sousa Caldas Bravin', '851701', '851701', 'manha', 2533, 0),
    (1361, 'Cidalva Franciscato', '911203', '911203', 'tarde', 552, 0),
    (1362, 'Wanessa Dhiane da Costa Oliveira', '997581', '997581', 'integral', 3292, 0),
    (1363, 'Maria Jose Vieprz Zabumba', '891841', '891841', 'manha', 2261, 0),
    (1364, 'Samanta Jander Chimene Brill', '982052', '982052', 'manha', 2838, 0),
    (1365, 'Juliane Siqueira de Souza Lavado', '1008563', '1008563', 'tarde', 1718, 0),
    (1367, 'Suely Maria de Souza', '856002', '856002', 'manha', 3022, 0),
    (1368, 'Joyce Pereira Manoel', '995542', '995542', 'manha', 1664, 0),
    (1369, 'Jéssica Aline de Jesus de Lima', '1008577', '1008577', 'tarde', 1550, 0),
    (1370, 'Lidiane Pacagnam', '994491', '994491', 'manha', 1931, 0),
    (1371, 'Lucimara Comitre', '992601', '992601', 'tarde', 2041, 0),
    (1372, 'Maria Aparecida Santos Barbosa', '884552', '884552', 'manha', 2193, 0),
    (1373, 'Danielle Cristina Bighetti', '990071', '990071', 'manha', 737, 0),
    (1374, 'Aline Barros dos Santos', '646022', '646022', 'manha', 131, 0),
    (1382, 'Cyntia Alcantara de Oliveira Felizardo', '1000961', '1000961', 'tarde', 674, 0),
    (1384, 'Maria Aparecida Vargens Perez', '883311', '883311', 'manha', 2195, 0),
    (1385, 'Sandra Renata Expedito', '938821', '938821', 'manha', 2867, 0),
    (1387, 'Kassia Meury Pereira', '1002561', '1002561', 'manha', 1775, 0),
    (1388, 'Maria Magali Bondezan', '1008607', '1008607', 'manha', 2271, 0),
    (1389, 'Edivany Cazelotto Dela Valentina', '994571', '994571', 'integral', 896, 0),
    (1390, 'Rosangela Naves da Silva', '848752', '848752', 'tarde', 2764, 0),
    (1391, 'Julio Cezar de Oliveira', '1008478', '1008478', 'manha', 1727, 0),
    (1392, 'FERNANDA CRISTINA TAIETE', '974033', '974033', 'manha', 1161, 0),
    (1393, 'Cristiane Machado Sitoni', '980602', '980602', 'tarde', 653, 0),
    (1394, 'Alessandra Vignoto Corradini', '997281', '997281', 'manha', 101, 0),
    (1395, 'Regina de Cassia Codato Cuculo', '994901', '994901', 'manha', 2656, 0),
    (1396, 'Deisy Aparecida Duarte', '997701', '997701', 'tarde', 800, 0),
    (1513, 'CAMILA FERREIRA DA SILVA', '1000621', '1000621', 'integral', 467, 0),
    (1514, 'THAILA FERNANDA GOVEIA', '1000571', '1000571', 'integral', 3092, 0),
    (1538, 'MARLEI CARVALHO DA COSTA', '847003', '847003', 'integral', 2348, 0),
    (1595, 'GIZELE RIBEIRO DOS SANTOS BARBOSA', '872972', '872972', 'manha', 1340, 0),
    (1603, 'Michelle Daiana Robatino Navarro', '896992', '896992', 'manha', 2415, 0),
    (1604, 'Jaqueline Marino', '1001451', '1001451', 'integral', 1521, 0),
    (1605, 'Maria José Pereira da Silva', '891701', '998701', 'tarde', 2258, 1),
    (1607, 'Juliana Boleta Mattos', '912431', '912431', 'manha', 1699, 0),
    (1608, 'Patrícia Karla da Silva Mantovi', '1000931', '1000931', 'manha', 2567, 0),
    (1609, 'Eliza Revesso Vieira Pereira', '838522', '838522', 'tarde', 1014, 0);

-- Diagnostico nominal. Antes da primeira execucao, resultado_previsto deve ser
-- INSERIR para as 93 linhas. Em uma reexecucao, deve ser JA CADASTRADA.
SELECT
    origem.servidor_id,
    origem.nome_banco,
    origem.matricula_legada,
    origem.matricula_api,
    origem.turno,
    origem.linha_planilha,
    CASE
        WHEN s.id IS NULL THEN 'SERVIDOR NAO ENCONTRADO'
        WHEN NOT (s.nome <=> origem.nome_banco) THEN 'NOME DO BANCO MUDOU'
        WHEN TRIM(COALESCE(s.matricula, '')) <> origem.matricula_legada THEN 'MATRICULA LEGADA MUDOU'
        WHEN NOT EXISTS (
            SELECT 1
            FROM servidor_funcao_administrativa sfa
            INNER JOIN funcao_administrativa fa
                ON fa.id = sfa.funcao_administrativa_id
            WHERE
                sfa.servidor_id = origem.servidor_id
                AND sfa.status = 'ativo'
                AND (
                    fa.direcao_escolar = 1
                    OR fa.coordenacao_pedagogica = 1
                    OR fa.secretaria_escolar = 1
                )
        ) THEN 'SEM VINCULO ATIVO DE EQUIPE GESTORA'
        WHEN NOT EXISTS (
            SELECT 1
            FROM professor_matriculas pm
            WHERE pm.servidor_id = origem.servidor_id
        ) THEN 'INSERIR'
        WHEN (
            SELECT COUNT(*)
            FROM professor_matriculas pm
            WHERE pm.servidor_id = origem.servidor_id
        ) = 1 AND EXISTS (
            SELECT 1
            FROM professor_matriculas pm
            WHERE
                pm.servidor_id = origem.servidor_id
                AND pm.matricula = origem.matricula_api
                AND pm.turno = origem.turno
        ) THEN 'JA CADASTRADA'
        ELSE 'MATRICULA CANONICA DIVERGENTE'
    END AS resultado_previsto
FROM tmp_matriculas_equipe_gestora_api origem
LEFT JOIN servidores s
    ON s.id = origem.servidor_id
ORDER BY origem.servidor_id;

START TRANSACTION;

SET @total_esperado = (
    SELECT COUNT(*)
    FROM tmp_matriculas_equipe_gestora_api
);

-- Um mapeamento e valido se o cadastro ainda corresponde ao dump, continua
-- na Equipe Gestora e nao possui matricula canonica, ou ja possui exatamente
-- a matricula e o turno esperados.
SET @total_valido = (
    SELECT COUNT(*)
    FROM tmp_matriculas_equipe_gestora_api origem
    INNER JOIN servidores s
        ON s.id = origem.servidor_id
        AND s.nome = origem.nome_banco
        AND TRIM(COALESCE(s.matricula, '')) = origem.matricula_legada
    WHERE
        s.status = 'ativo'
        AND EXISTS (
            SELECT 1
            FROM servidor_funcao_administrativa sfa
            INNER JOIN funcao_administrativa fa
                ON fa.id = sfa.funcao_administrativa_id
            WHERE
                sfa.servidor_id = origem.servidor_id
                AND sfa.status = 'ativo'
                AND (
                    fa.direcao_escolar = 1
                    OR fa.coordenacao_pedagogica = 1
                    OR fa.secretaria_escolar = 1
                )
        )
        AND (
            NOT EXISTS (
                SELECT 1
                FROM professor_matriculas pm
                WHERE pm.servidor_id = origem.servidor_id
            )
            OR (
                (
                    SELECT COUNT(*)
                    FROM professor_matriculas pm
                    WHERE pm.servidor_id = origem.servidor_id
                ) = 1
                AND EXISTS (
                    SELECT 1
                    FROM professor_matriculas pm
                    WHERE
                        pm.servidor_id = origem.servidor_id
                        AND pm.matricula = origem.matricula_api
                        AND pm.turno = origem.turno
                )
            )
        )
);

INSERT INTO professor_matriculas
    (servidor_id, matricula, turno, created_at, updated_at)
SELECT
    origem.servidor_id,
    origem.matricula_api,
    origem.turno,
    NOW(),
    NOW()
FROM tmp_matriculas_equipe_gestora_api origem
INNER JOIN servidores s
    ON s.id = origem.servidor_id
    AND s.nome = origem.nome_banco
    AND TRIM(COALESCE(s.matricula, '')) = origem.matricula_legada
WHERE
    @total_valido = @total_esperado
    AND s.status = 'ativo'
    AND EXISTS (
        SELECT 1
        FROM servidor_funcao_administrativa sfa
        INNER JOIN funcao_administrativa fa
            ON fa.id = sfa.funcao_administrativa_id
        WHERE
            sfa.servidor_id = origem.servidor_id
            AND sfa.status = 'ativo'
            AND (
                fa.direcao_escolar = 1
                OR fa.coordenacao_pedagogica = 1
                OR fa.secretaria_escolar = 1
            )
    )
    AND NOT EXISTS (
        SELECT 1
        FROM professor_matriculas pm
        WHERE pm.servidor_id = origem.servidor_id
    )
ORDER BY origem.servidor_id;

SET @total_inserido = ROW_COUNT();

-- Resultado esperado na primeira execucao:
-- total_esperado = 93, total_valido = 93, total_inserido = 93, resultado = OK.
-- Em uma reexecucao: total_inserido = 0 e resultado = OK.
SELECT
    @total_esperado AS total_esperado,
    @total_valido AS total_valido,
    @total_inserido AS total_inserido,
    CASE
        WHEN @total_valido = @total_esperado THEN 'OK'
        ELSE 'ABORTADO: REVISE O DIAGNOSTICO; NENHUMA LINHA FOI INSERIDA'
    END AS resultado;

-- Validacao final: cadastradas_corretamente deve ser 93 e divergentes deve ser 0.
SELECT
    SUM(
        CASE
            WHEN pm.servidor_id IS NOT NULL THEN 1
            ELSE 0
        END
    ) AS cadastradas_corretamente,
    SUM(
        CASE
            WHEN pm.servidor_id IS NULL THEN 1
            ELSE 0
        END
    ) AS divergentes
FROM tmp_matriculas_equipe_gestora_api origem
LEFT JOIN professor_matriculas pm
    ON pm.servidor_id = origem.servidor_id
    AND pm.matricula = origem.matricula_api
    AND pm.turno = origem.turno;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_matriculas_equipe_gestora_api;
