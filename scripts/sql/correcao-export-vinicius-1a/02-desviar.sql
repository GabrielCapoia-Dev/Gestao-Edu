-- Desvio temporário para exportação de pareceres
-- Escola Vinicius de Morais — 1º Ano A — avaliação 3
--
-- Faça backup antes de executar.
-- Execute primeiro 01-diagnostico.sql e confira a lista.
-- Não altera status, snapshots, respostas ou ciclos.
-- Move somente transferido/remanejado sem snapshot final do ciclo concluído.

USE `gestao-edu`;

SET @avaliacao_id := 3;
SET @escola_id := 70;
SET @turma_id := 1645;

START TRANSACTION;

-- Idempotente: não duplica a turma temporária.
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
    CONCAT('TMP-EXPORT-PARECER-', turma.id),
    CONCAT('TEMPORARIA - EXPORTACAO PARECER - ', turma.nome),
    turma.turno,
    turma.id_serie,
    turma.id_escola,
    NOW(),
    NOW()
FROM turmas AS turma
WHERE turma.id = @turma_id
  AND turma.id_escola = @escola_id
  AND turma.id_serie = 5
  AND turma.nome = 'A'
  AND NOT EXISTS (
      SELECT 1
      FROM turmas AS existente
      WHERE existente.codigo = CONCAT('TMP-EXPORT-PARECER-', turma.id)
  );

SELECT ROW_COUNT() AS turmas_temporarias_criadas;

-- Confirme que a turma temporária pertence à mesma escola e série.
SELECT
    origem.id AS turma_original_id,
    origem.nome AS turma_original,
    origem.id_escola AS escola_original_id,
    origem.id_serie AS serie_original_id,
    temporaria.id AS turma_temporaria_id,
    temporaria.codigo AS turma_temporaria,
    temporaria.id_escola AS escola_temporaria_id,
    temporaria.id_serie AS serie_temporaria_id
FROM turmas AS origem
LEFT JOIN turmas AS temporaria
    ON temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', origem.id)
   AND temporaria.id_escola = origem.id_escola
WHERE origem.id = @turma_id
  AND origem.id_escola = @escola_id;

-- O filtro de status inclui remanejado porque foi o caso encontrado no dump.
-- A ausência de snapshot é verificada pelo evento final vigente do ciclo.
UPDATE alunos AS aluno
INNER JOIN turmas AS origem
    ON origem.id = aluno.id_turma
INNER JOIN turmas AS temporaria
    ON temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', origem.id)
   AND temporaria.id_escola = origem.id_escola
INNER JOIN avaliacao_turma_ciclos AS ciclo
    ON ciclo.avaliacao_id = @avaliacao_id
   AND ciclo.turma_avaliativa_id = origem.id
   AND ciclo.turma_origem_id = origem.id
LEFT JOIN avaliacao_aluno_snapshots AS snapshot
    ON snapshot.evento_id = ciclo.snapshot_evento_atual_id
   AND snapshot.aluno_id = aluno.id
SET aluno.id_turma = temporaria.id,
    aluno.updated_at = NOW()
WHERE origem.id = @turma_id
  AND origem.id_escola = @escola_id
  AND ciclo.status = 'concluida'
  AND ciclo.snapshot_evento_atual_id IS NOT NULL
  AND aluno.status IN ('transferido', 'remanejado')
  AND snapshot.id IS NULL;

SET @alunos_movidos := ROW_COUNT();

SELECT @alunos_movidos AS alunos_movidos;

SELECT
    aluno.id AS aluno_id,
    aluno.nome AS aluno,
    aluno.cgm,
    aluno.status,
    origem.id AS turma_original_id,
    temporaria.id AS turma_temporaria_id,
    temporaria.codigo AS turma_temporaria
FROM alunos AS aluno
INNER JOIN turmas AS temporaria
    ON temporaria.id = aluno.id_turma
INNER JOIN turmas AS origem
    ON temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', origem.id)
   AND temporaria.id_escola = origem.id_escola
WHERE temporaria.id_escola = @escola_id
  AND temporaria.codigo = CONCAT('TMP-EXPORT-PARECER-', @turma_id)
ORDER BY aluno.nome;

-- Se os resultados estiverem corretos, confirme. Em caso de divergência,
-- substitua COMMIT por ROLLBACK antes de executar.
COMMIT;
