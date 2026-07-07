-- Desbloqueia apenas copias avaliativas do aluno atual matriculado por remanejamento.
--
-- Causa do bloqueio:
-- - A tela de avaliacoes bloqueia edicao quando avaliacao_respostas.bloqueada = 1.
-- - Registros antigos copiados para a matricula de destino por remanejamento podem ter
--   ficado com bloqueada = 1.
-- - Se bloqueio_tipo ja foi limpo manualmente, a correcao ainda identifica as copias
--   por resposta_origem_id/informacao_origem_id, aluno_origem_id e movimentacao_origem.
--
-- Escopo seguro:
-- - Corrige apenas registros do aluno de destino ainda matriculado.
-- - Exige movimentacao_origem = 'remanejamento' no aluno de destino.
-- - Exige que aluno_origem_id do registro bata com aluno_origem_id da matricula.
-- - Nao altera respostas da matricula historica/remanejada de origem.

-- 1) Diagnostico antes da correcao.
SELECT COUNT(*) AS respostas_bloqueadas_destino_remanejamento
FROM avaliacao_respostas ar
INNER JOIN alunos a ON a.id = ar.aluno_id
WHERE ar.bloqueada = 1
  AND ar.resposta_origem_id IS NOT NULL
  AND ar.aluno_origem_id IS NOT NULL
  AND a.status = 'matriculado'
  AND a.movimentacao_origem = 'remanejamento'
  AND a.aluno_origem_id = ar.aluno_origem_id
  AND (ar.bloqueio_tipo = 'remanejamento' OR ar.bloqueio_tipo IS NULL);

SELECT COUNT(*) AS informacoes_bloqueadas_destino_remanejamento
FROM avaliacao_informacoes_complementares aic
INNER JOIN alunos a ON a.id = aic.aluno_id
WHERE aic.bloqueada = 1
  AND aic.informacao_origem_id IS NOT NULL
  AND aic.aluno_origem_id IS NOT NULL
  AND a.status = 'matriculado'
  AND a.movimentacao_origem = 'remanejamento'
  AND a.aluno_origem_id = aic.aluno_origem_id
  AND (aic.bloqueio_tipo = 'remanejamento' OR aic.bloqueio_tipo IS NULL);

START TRANSACTION;

-- 2) Correcao das respostas copiadas para a matricula de destino.
UPDATE avaliacao_respostas ar
INNER JOIN alunos a ON a.id = ar.aluno_id
SET
    ar.bloqueada = 0,
    ar.bloqueio_tipo = NULL,
    ar.updated_at = CURRENT_TIMESTAMP
WHERE ar.bloqueada = 1
  AND ar.resposta_origem_id IS NOT NULL
  AND ar.aluno_origem_id IS NOT NULL
  AND a.status = 'matriculado'
  AND a.movimentacao_origem = 'remanejamento'
  AND a.aluno_origem_id = ar.aluno_origem_id
  AND (ar.bloqueio_tipo = 'remanejamento' OR ar.bloqueio_tipo IS NULL);

SELECT ROW_COUNT() AS respostas_corrigidas;

-- 3) Correcao das informacoes complementares copiadas para a matricula de destino.
UPDATE avaliacao_informacoes_complementares aic
INNER JOIN alunos a ON a.id = aic.aluno_id
SET
    aic.bloqueada = 0,
    aic.bloqueio_tipo = NULL,
    aic.updated_at = CURRENT_TIMESTAMP
WHERE aic.bloqueada = 1
  AND aic.informacao_origem_id IS NOT NULL
  AND aic.aluno_origem_id IS NOT NULL
  AND a.status = 'matriculado'
  AND a.movimentacao_origem = 'remanejamento'
  AND a.aluno_origem_id = aic.aluno_origem_id
  AND (aic.bloqueio_tipo = 'remanejamento' OR aic.bloqueio_tipo IS NULL);

SELECT ROW_COUNT() AS informacoes_corrigidas;

-- 4) Validacao apos a correcao. Os dois contadores devem retornar zero.
SELECT COUNT(*) AS respostas_ainda_bloqueadas_destino_remanejamento
FROM avaliacao_respostas ar
INNER JOIN alunos a ON a.id = ar.aluno_id
WHERE ar.bloqueada = 1
  AND ar.resposta_origem_id IS NOT NULL
  AND ar.aluno_origem_id IS NOT NULL
  AND a.status = 'matriculado'
  AND a.movimentacao_origem = 'remanejamento'
  AND a.aluno_origem_id = ar.aluno_origem_id
  AND (ar.bloqueio_tipo = 'remanejamento' OR ar.bloqueio_tipo IS NULL);

SELECT COUNT(*) AS informacoes_ainda_bloqueadas_destino_remanejamento
FROM avaliacao_informacoes_complementares aic
INNER JOIN alunos a ON a.id = aic.aluno_id
WHERE aic.bloqueada = 1
  AND aic.informacao_origem_id IS NOT NULL
  AND aic.aluno_origem_id IS NOT NULL
  AND a.status = 'matriculado'
  AND a.movimentacao_origem = 'remanejamento'
  AND a.aluno_origem_id = aic.aluno_origem_id
  AND (aic.bloqueio_tipo = 'remanejamento' OR aic.bloqueio_tipo IS NULL);

COMMIT;
