-- CORREÇÃO TEMPORÁRIA — COORDENAÇÃO DO 2º ANO — PAULO FREIRE (ID 62)
--
-- Pré-requisitos:
--   * Fazer backup antes da execução.
--   * Executar conectado ao banco gestao-edu.
--   * Não executar no arquivo de dump.
--
-- O dump mostrou que os snapshots concluídos da avaliação 3 ainda guardam
-- SUELY MARIA DE SOUZA, embora o vínculo vigente já esteja em JULIANE
-- SIQUEIRA DE SOUZA LAVADO. O exportador lê o coordenador salvo no snapshot;
-- por isso a correção atualiza o vínculo e os JSONs de documentos/snapshots.
--
-- ATENÇÃO: payload_hash dos snapshots é um hash canônico calculado pela
-- aplicação. Este script não tenta fabricar esse hash no MySQL. A alteração
-- é temporária para exportação manual; depois, a integridade dos snapshots
-- deve ser regularizada pela aplicação em procedimento próprio.

SET @escola_pf = 62;
SET @avaliacao_parecer = 3;
SET @juliane_servidor = (
    SELECT s.id
    FROM servidores AS s
    WHERE s.id_escola = @escola_pf
      AND s.nome = 'JULIANE SIQUEIRA DE SOUZA LAVADO'
      AND s.status = 'ativo'
    ORDER BY s.id
    LIMIT 1
);
SET @juliane_vinculo = (
    SELECT sfa.id
    FROM servidor_funcao_administrativa AS sfa
    WHERE sfa.servidor_id = @juliane_servidor
      AND sfa.id_escola = @escola_pf
      AND sfa.funcao_administrativa_id = 4
      AND sfa.status = 'ativo'
    ORDER BY sfa.id
    LIMIT 1
);

-- Conferência antes da alteração.
SELECT
    @juliane_servidor AS juliane_servidor_id,
    @juliane_vinculo AS juliane_vinculo_administrativo_id;

SELECT
    vft.id AS vinculo_turma_id,
    vft.turma_id,
    t.codigo AS turma,
    vft.servidor_funcao_administrativa_id AS vinculo_administrativo_id,
    s.nome AS coordenador,
    vft.status,
    vft.data_inicio,
    vft.data_fim
FROM servidor_funcao_turma AS vft
INNER JOIN turmas AS t ON t.id = vft.turma_id
INNER JOIN servidor_funcao_administrativa AS sfa
    ON sfa.id = vft.servidor_funcao_administrativa_id
INNER JOIN servidores AS s ON s.id = sfa.servidor_id
WHERE t.id_escola = @escola_pf
  AND t.id_serie = 6
  AND vft.status = 'ativo'
ORDER BY t.id, vft.id;

START TRANSACTION;

-- Encerra somente os vínculos ativos da coordenadora anterior nas quatro
-- turmas do 2º Ano. O histórico permanece preservado.
UPDATE servidor_funcao_turma AS vft
INNER JOIN turmas AS t ON t.id = vft.turma_id
INNER JOIN servidor_funcao_administrativa AS sfa
    ON sfa.id = vft.servidor_funcao_administrativa_id
INNER JOIN servidores AS s ON s.id = sfa.servidor_id
SET vft.status = 'inativo',
    vft.data_fim = COALESCE(vft.data_fim, CURDATE()),
    vft.updated_at = NOW()
WHERE t.id_escola = @escola_pf
  AND t.id_serie = 6
  AND s.nome = 'SUELY MARIA DE SOUZA'
  AND vft.status = 'ativo';

-- Garante um vínculo vigente de Juliane por turma, sem duplicar vínculos.
INSERT INTO servidor_funcao_turma (
    servidor_funcao_administrativa_id,
    turma_id,
    principal,
    status,
    data_inicio,
    data_fim,
    created_at,
    updated_at
)
SELECT
    @juliane_vinculo,
    t.id,
    0,
    'ativo',
    COALESCE((
        SELECT sfa.data_inicio
        FROM servidor_funcao_administrativa AS sfa
        WHERE sfa.id = @juliane_vinculo
    ), CURDATE()),
    NULL,
    NOW(),
    NOW()
FROM turmas AS t
WHERE t.id_escola = @escola_pf
  AND t.id_serie = 6
  AND @juliane_vinculo IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM servidor_funcao_turma AS existente
      WHERE existente.turma_id = t.id
        AND existente.servidor_funcao_administrativa_id = @juliane_vinculo
        AND existente.status = 'ativo'
  );

-- Atualiza documentos relacionais usados por exportações individuais.
UPDATE avaliacao_aluno_documentos AS d
INNER JOIN turmas AS t ON t.id = d.turma_id
INNER JOIN servidor_funcao_turma AS vft
    ON vft.turma_id = t.id
   AND vft.servidor_funcao_administrativa_id = @juliane_vinculo
   AND vft.status = 'ativo'
INNER JOIN servidor_funcao_administrativa AS sfa
    ON sfa.id = @juliane_vinculo
SET d.responsaveis_snapshot = JSON_SET(
        COALESCE(d.responsaveis_snapshot, JSON_OBJECT()),
        '$.coordenador.vinculo_id', sfa.id,
        '$.coordenador.pessoa_id', @juliane_servidor,
        '$.coordenador.funcao_administrativa_id', sfa.funcao_administrativa_id,
        '$.coordenador.nome', 'JULIANE SIQUEIRA DE SOUZA LAVADO',
        '$.coordenador.portaria', sfa.portaria,
        '$.coordenador.inicio', sfa.data_inicio,
        '$.coordenador.fim', sfa.data_fim,
        '$.coordenador.vinculo_turma_id', vft.id,
        '$.coordenador.vinculo_turma_inicio', vft.data_inicio,
        '$.coordenador.vinculo_turma_fim', vft.data_fim
    ),
    d.payload = JSON_SET(
        d.payload,
        '$.responsaveis_snapshot.coordenador.vinculo_id', sfa.id,
        '$.responsaveis_snapshot.coordenador.pessoa_id', @juliane_servidor,
        '$.responsaveis_snapshot.coordenador.funcao_administrativa_id', sfa.funcao_administrativa_id,
        '$.responsaveis_snapshot.coordenador.nome', 'JULIANE SIQUEIRA DE SOUZA LAVADO',
        '$.responsaveis_snapshot.coordenador.portaria', sfa.portaria,
        '$.responsaveis_snapshot.coordenador.inicio', sfa.data_inicio,
        '$.responsaveis_snapshot.coordenador.fim', sfa.data_fim,
        '$.responsaveis_snapshot.coordenador.vinculo_turma_id', vft.id,
        '$.responsaveis_snapshot.coordenador.vinculo_turma_inicio', vft.data_inicio,
        '$.responsaveis_snapshot.coordenador.vinculo_turma_fim', vft.data_fim
    ),
    d.updated_at = NOW()
WHERE d.avaliacao_id = @avaliacao_parecer
  AND d.escola_id = @escola_pf
  AND t.id_serie = 6;

-- Atualiza o coordenador dentro do payload dos snapshots concluídos da
-- avaliação 3. O restante das respostas e informações é preservado.
UPDATE avaliacao_aluno_snapshots AS s
INNER JOIN avaliacao_turma_ciclos AS c
    ON c.id = s.ciclo_id
   AND c.avaliacao_id = s.avaliacao_id
   AND c.turma_avaliativa_id = s.turma_avaliativa_id
   AND c.turma_origem_id = s.turma_origem_id
INNER JOIN turmas AS t ON t.id = c.turma_avaliativa_id
INNER JOIN servidor_funcao_turma AS vft
    ON vft.turma_id = t.id
   AND vft.servidor_funcao_administrativa_id = @juliane_vinculo
   AND vft.status = 'ativo'
INNER JOIN servidor_funcao_administrativa AS sfa
    ON sfa.id = @juliane_vinculo
SET s.payload = JSON_SET(
        s.payload,
        '$.responsaveis_snapshot.coordenador.vinculo_id', sfa.id,
        '$.responsaveis_snapshot.coordenador.pessoa_id', @juliane_servidor,
        '$.responsaveis_snapshot.coordenador.funcao_administrativa_id', sfa.funcao_administrativa_id,
        '$.responsaveis_snapshot.coordenador.nome', 'JULIANE SIQUEIRA DE SOUZA LAVADO',
        '$.responsaveis_snapshot.coordenador.portaria', sfa.portaria,
        '$.responsaveis_snapshot.coordenador.inicio', sfa.data_inicio,
        '$.responsaveis_snapshot.coordenador.fim', sfa.data_fim,
        '$.responsaveis_snapshot.coordenador.vinculo_turma_id', vft.id,
        '$.responsaveis_snapshot.coordenador.vinculo_turma_inicio', vft.data_inicio,
        '$.responsaveis_snapshot.coordenador.vinculo_turma_fim', vft.data_fim
    ),
    s.updated_at = NOW()
WHERE s.avaliacao_id = @avaliacao_parecer
  AND s.tipo = 'conclusao'
  AND c.status IN ('concluida', 'reaberta')
  AND t.id_escola = @escola_pf
  AND t.id_serie = 6;

SELECT ROW_COUNT() AS snapshots_atualizados;

COMMIT;

-- Conferência final: deve exibir Juliane como coordenadora ativa nas quatro
-- turmas. Confira também os PDFs gerados antes de restaurar qualquer desvio.
SELECT
    t.id AS turma_id,
    t.codigo AS turma,
    s.nome AS coordenador,
    vft.status,
    vft.data_inicio,
    vft.data_fim
FROM turmas AS t
INNER JOIN servidor_funcao_turma AS vft
    ON vft.turma_id = t.id
INNER JOIN servidor_funcao_administrativa AS sfa
    ON sfa.id = vft.servidor_funcao_administrativa_id
INNER JOIN servidores AS s ON s.id = sfa.servidor_id
WHERE t.id_escola = @escola_pf
  AND t.id_serie = 6
  AND vft.status = 'ativo'
ORDER BY t.id;

SELECT
    s.id AS snapshot_id,
    s.turma_avaliativa_id AS turma_id,
    JSON_UNQUOTE(JSON_EXTRACT(s.payload, '$.responsaveis_snapshot.coordenador.nome')) AS coordenador_no_snapshot
FROM avaliacao_aluno_snapshots AS s
WHERE s.avaliacao_id = @avaliacao_parecer
  AND s.tipo = 'conclusao'
  AND s.turma_avaliativa_id IN (1366, 1367, 1368, 1369)
ORDER BY s.turma_avaliativa_id, s.id
LIMIT 20;
