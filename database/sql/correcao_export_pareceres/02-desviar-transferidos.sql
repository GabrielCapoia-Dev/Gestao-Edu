-- CORREÇÃO TEMPORÁRIA DE EXPORTAÇÃO DE PARECERES
-- Move somente alunos transferidos sem snapshot final do ciclo informado.
-- Pré-requisitos:
--   * backup recente;
--   * confirmar o diagnóstico do arquivo 01;
--   * executar a exportação antes de restaurar;
--   * executar no MySQL/phpMyAdmin com transações InnoDB habilitadas.
-- O script não altera status, turma_origem_id, avaliações ou snapshots.

SET @avaliacao_id := 0;
SET @turma_avaliativa_id := 0;

-- A turma original é derivada do ciclo; não informe uma segunda lista de IDs.
SET @turma_origem_id := (
    SELECT c.turma_origem_id
    FROM avaliacao_turma_ciclos AS c
    WHERE c.avaliacao_id = @avaliacao_id
      AND c.turma_avaliativa_id = @turma_avaliativa_id
      AND c.status = 'concluida'
      AND c.snapshot_evento_atual_id IS NOT NULL
    LIMIT 1
);

SET @codigo_tmp := IF(
    @turma_origem_id IS NULL,
    '',
    CONCAT('TMP-TRANSFERIDOS-', @turma_origem_id)
);

START TRANSACTION;

-- Cria uma turma compatível com a turma de origem e não duplica o código.
INSERT INTO turmas (
    codigo,
    nome,
    turno,
    id_serie,
    id_escola,
    created_at,
    updated_at
)
SELECT
    @codigo_tmp,
    CONCAT('TEMPORÁRIA - TRANSFERIDOS - ', origem.nome),
    origem.turno,
    origem.id_serie,
    origem.id_escola,
    NOW(),
    NOW()
FROM turmas AS origem
WHERE origem.id = @turma_origem_id
  AND @codigo_tmp <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM turmas AS existente
      WHERE existente.codigo = @codigo_tmp
  );

SET @turma_temporaria_id := (
    SELECT tmp.id
    FROM turmas AS tmp
    WHERE tmp.codigo = @codigo_tmp
    LIMIT 1
);

-- Se o código já existir com outra escola, série ou turno, nenhum aluno será movido.
SET @tmp_compativel := IF(
    @turma_temporaria_id IS NOT NULL
    AND EXISTS (
        SELECT 1
        FROM turmas AS origem
        INNER JOIN turmas AS tmp ON tmp.id = @turma_temporaria_id
        WHERE origem.id = @turma_origem_id
          AND tmp.id_escola = origem.id_escola
          AND tmp.id_serie = origem.id_serie
          AND tmp.turno = origem.turno
    ),
    1,
    0
);

SELECT
    @avaliacao_id AS avaliacao_id,
    @turma_avaliativa_id AS turma_avaliativa_id,
    @turma_origem_id AS turma_origem_id,
    @turma_temporaria_id AS turma_temporaria_id,
    @codigo_tmp AS codigo_temporario,
    @tmp_compativel AS turma_temporaria_compativel;

SET @problematicos_antes := (
    SELECT COUNT(*)
    FROM avaliacao_turma_ciclos AS c
    INNER JOIN alunos AS a
        ON a.id_turma = c.turma_origem_id
       AND a.status = 'transferido'
    WHERE c.avaliacao_id = @avaliacao_id
      AND c.turma_avaliativa_id = @turma_avaliativa_id
      AND c.status = 'concluida'
      AND c.snapshot_evento_atual_id IS NOT NULL
      AND NOT EXISTS (
          SELECT 1
          FROM avaliacao_aluno_snapshots AS snapshot
          WHERE snapshot.evento_id = c.snapshot_evento_atual_id
            AND snapshot.aluno_id = a.id
      )
);

-- Idempotência: só alcança alunos ainda na turma_origem. Alunos já desviados
-- permanecem na turma temporária e não são movidos novamente.
UPDATE alunos AS a
INNER JOIN avaliacao_turma_ciclos AS c
    ON c.avaliacao_id = @avaliacao_id
   AND c.turma_avaliativa_id = @turma_avaliativa_id
   AND c.turma_origem_id = @turma_origem_id
SET a.id_turma = @turma_temporaria_id,
    a.updated_at = NOW()
WHERE @tmp_compativel = 1
  AND @turma_temporaria_id IS NOT NULL
  AND c.status = 'concluida'
  AND c.snapshot_evento_atual_id IS NOT NULL
  AND a.id_turma = c.turma_origem_id
  AND a.status = 'transferido'
  AND NOT EXISTS (
      SELECT 1
      FROM avaliacao_aluno_snapshots AS snapshot
      WHERE snapshot.evento_id = c.snapshot_evento_atual_id
        AND snapshot.aluno_id = a.id
  );

SET @desviados := ROW_COUNT();

SELECT
    @problematicos_antes AS problematicos_identificados,
    @desviados AS alunos_movidos;

-- Lista os alunos atualmente na turma temporária. Em uma reexecução idempotente,
-- a lista também inclui os alunos que já haviam sido desviados anteriormente.
SELECT
    a.id AS aluno_id,
    a.nome AS aluno,
    a.cgm,
    a.status,
    a.tipo_vinculo,
    a.id_turma AS turma_atual_id,
    tmp.id AS turma_temporaria_id,
    tmp.codigo AS turma_temporaria_codigo,
    @turma_origem_id AS turma_original_id
FROM alunos AS a
INNER JOIN turmas AS tmp ON tmp.id = a.id_turma
WHERE tmp.id = @turma_temporaria_id
ORDER BY a.nome, a.id;

COMMIT;
