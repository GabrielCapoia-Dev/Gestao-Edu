-- CORREÇÃO TEMPORÁRIA DE EXPORTAÇÃO DE PARECERES
-- Somente leitura. Não execute sem configurar as variáveis abaixo.
-- Pré-requisitos: backup recente e schema relacional de avaliações aplicado.
-- Não alterar status, snapshots ou qualquer dado neste diagnóstico.

SET @avaliacao_id := 0;
SET @turma_avaliativa_id := 0;

-- Confirma o ciclo, a turma avaliativa, a turma de origem e o evento atual.
SELECT
    av.id AS avaliacao_id,
    av.nome AS avaliacao,
    c.id AS ciclo_id,
    c.status AS ciclo_status,
    c.turma_avaliativa_id,
    turma_avaliativa.codigo AS turma_avaliativa_codigo,
    turma_avaliativa.nome AS turma_avaliativa,
    c.turma_origem_id,
    turma_origem.codigo AS turma_origem_codigo,
    turma_origem.nome AS turma_origem,
    c.snapshot_evento_atual_id,
    evento.tipo AS evento_tipo,
    evento.publicado_em
FROM avaliacao_turma_ciclos AS c
INNER JOIN avaliacoes AS av ON av.id = c.avaliacao_id
INNER JOIN turmas AS turma_avaliativa ON turma_avaliativa.id = c.turma_avaliativa_id
INNER JOIN turmas AS turma_origem ON turma_origem.id = c.turma_origem_id
LEFT JOIN avaliacao_snapshot_eventos AS evento ON evento.id = c.snapshot_evento_atual_id
WHERE c.avaliacao_id = @avaliacao_id
  AND c.turma_avaliativa_id = @turma_avaliativa_id;

-- Critério exato do problema:
-- 1) aluno está na turma_origem do ciclo;
-- 2) status é exatamente 'transferido';
-- 3) ciclo está concluído e possui evento final atual;
-- 4) não existe snapshot para esse aluno no evento final atual.
-- O exportador atual exclui apenas 'pendente', portanto todos os demais
-- status, inclusive 'transferido', entram no roster da turma.
SELECT
    a.id AS aluno_id,
    a.nome AS aluno,
    a.cgm,
    a.status AS aluno_status,
    a.tipo_vinculo,
    a.id_turma AS aluno_turma_atual_id,
    turma_origem.id AS turma_original_id,
    turma_origem.codigo AS turma_original_codigo,
    turma_origem.nome AS turma_original,
    av.id AS avaliacao_id,
    av.nome AS avaliacao,
    c.id AS ciclo_id,
    c.status AS ciclo_status,
    c.turma_avaliativa_id,
    c.turma_origem_id,
    c.snapshot_evento_atual_id,
    evento.tipo AS evento_tipo,
    snapshot.id AS snapshot_id,
    CASE
        WHEN c.status = 'concluida'
         AND c.snapshot_evento_atual_id IS NOT NULL
         AND snapshot.id IS NULL
        THEN 'PROBLEMÁTICO: snapshot final ausente'
        ELSE 'Não atende ao critério de falha'
    END AS diagnostico
FROM avaliacao_turma_ciclos AS c
INNER JOIN avaliacoes AS av ON av.id = c.avaliacao_id
INNER JOIN turmas AS turma_origem ON turma_origem.id = c.turma_origem_id
INNER JOIN alunos AS a
    ON a.id_turma = c.turma_origem_id
   AND a.status = 'transferido'
LEFT JOIN avaliacao_snapshot_eventos AS evento ON evento.id = c.snapshot_evento_atual_id
LEFT JOIN avaliacao_aluno_snapshots AS snapshot
    ON snapshot.evento_id = c.snapshot_evento_atual_id
   AND snapshot.aluno_id = a.id
WHERE c.avaliacao_id = @avaliacao_id
  AND c.turma_avaliativa_id = @turma_avaliativa_id
ORDER BY a.nome, a.id;
