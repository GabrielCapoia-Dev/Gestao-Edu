-- Correção temporária da exportação de pareceres — Escola Tempo Integral
-- Avaliação: 3
--
-- Pré-requisitos:
-- 1. Fazer backup do banco.
-- 2. Executar 01-diagnostico-transferidos.sql e conferir os resultados.
-- 3. Confirmar que o banco selecionado é o ambiente correto.
--
-- Esta operação move somente alunos com status `transferido` e sem o
-- snapshot final do evento vigente do ciclo da avaliação 3.
-- Não altera status, snapshots, respostas, ciclos ou vínculos da avaliação.
-- O código da turma temporária preserva a turma original:
-- TMP-TRANSFERIDOS-{ID_TURMA_ORIGINAL}.

USE `gestao-edu`;

SET @avaliacao_id := 3;
SET @escola_id := 69;

START TRANSACTION;

-- Idempotente: turmas já existentes com o código esperado não são duplicadas.
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
    CONCAT('TMP-TRANSFERIDOS-', turma.id),
    CONCAT('TEMPORARIA - TRANSFERIDOS - ', turma.nome),
    turma.turno,
    turma.id_serie,
    turma.id_escola,
    NOW(),
    NOW()
FROM turmas AS turma
WHERE turma.id IN (1616, 1617, 1618)
  AND turma.id_escola = @escola_id
  AND NOT EXISTS (
      SELECT 1
      FROM turmas AS existente
      WHERE existente.codigo = CONCAT('TMP-TRANSFERIDOS-', turma.id)
  );

SELECT ROW_COUNT() AS turmas_temporarias_criadas;

-- Conferência de segurança: se algum código já existir em outra escola, a
-- atualização abaixo não encontra a turma temporária e nenhum aluno é movido.
SELECT
    turma.id AS turma_original_id,
    turma.codigo AS codigo_turma_original,
    turma.nome AS turma_original,
    temporaria.id AS turma_temporaria_id,
    temporaria.codigo AS codigo_turma_temporaria,
    temporaria.id_escola AS escola_turma_temporaria
FROM turmas AS turma
LEFT JOIN turmas AS temporaria
    ON temporaria.codigo = CONCAT('TMP-TRANSFERIDOS-', turma.id)
   AND temporaria.id_escola = turma.id_escola
WHERE turma.id IN (1616, 1617, 1618)
  AND turma.id_escola = @escola_id
ORDER BY turma.id;

-- Move apenas os casos diagnosticados como problemáticos.
UPDATE alunos AS aluno
INNER JOIN turmas AS origem
    ON origem.id = aluno.id_turma
INNER JOIN turmas AS temporaria
    ON temporaria.codigo = CONCAT('TMP-TRANSFERIDOS-', origem.id)
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
WHERE origem.id IN (1616, 1617, 1618)
  AND origem.id_escola = @escola_id
  AND ciclo.status = 'concluida'
  AND ciclo.snapshot_evento_atual_id IS NOT NULL
  AND aluno.status = 'transferido'
  AND snapshot.id IS NULL;

SET @alunos_movidos := ROW_COUNT();

SELECT @alunos_movidos AS alunos_movidos;

-- Lista final da operação dentro da transação.
SELECT
    aluno.id AS aluno_id,
    aluno.nome AS aluno,
    aluno.cgm,
    aluno.status,
    origem.id AS turma_original_id,
    origem.nome AS turma_original,
    temporaria.id AS turma_temporaria_id,
    temporaria.codigo AS turma_temporaria
FROM alunos AS aluno
INNER JOIN turmas AS temporaria
    ON temporaria.id = aluno.id_turma
INNER JOIN turmas AS origem
    ON temporaria.codigo = CONCAT('TMP-TRANSFERIDOS-', origem.id)
   AND temporaria.id_escola = origem.id_escola
WHERE temporaria.codigo LIKE 'TMP-TRANSFERIDOS-%'
  AND temporaria.id_escola = @escola_id
ORDER BY temporaria.id, aluno.nome;

-- Se a lista e a quantidade estiverem corretas, confirme a transação.
-- Em caso de divergência, substitua COMMIT por ROLLBACK antes de executar.
COMMIT;
