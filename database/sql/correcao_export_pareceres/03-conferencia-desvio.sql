-- CORREÇÃO TEMPORÁRIA DE EXPORTAÇÃO DE PARECERES
-- Somente leitura. Executar depois do arquivo 02 e antes da exportação.

SET @avaliacao_id := 0;
SET @turma_avaliativa_id := 0;

SET @turma_origem_id := (
    SELECT c.turma_origem_id
    FROM avaliacao_turma_ciclos AS c
    WHERE c.avaliacao_id = @avaliacao_id
      AND c.turma_avaliativa_id = @turma_avaliativa_id
    LIMIT 1
);

SET @codigo_tmp := IF(
    @turma_origem_id IS NULL,
    '',
    CONCAT('TMP-TRANSFERIDOS-', @turma_origem_id)
);

-- Turma temporária e compatibilidade estrutural.
SELECT
    tmp.id AS turma_temporaria_id,
    tmp.codigo,
    tmp.nome,
    tmp.turno,
    tmp.id_serie,
    tmp.id_escola,
    origem.id AS turma_original_id,
    origem.codigo AS turma_original_codigo,
    origem.nome AS turma_original,
    COUNT(a.id) AS quantidade_alunos
FROM turmas AS tmp
LEFT JOIN turmas AS origem
    ON origem.id = @turma_origem_id
   AND tmp.id_escola = origem.id_escola
   AND tmp.id_serie = origem.id_serie
   AND tmp.turno = origem.turno
LEFT JOIN alunos AS a ON a.id_turma = tmp.id
WHERE tmp.codigo = @codigo_tmp
GROUP BY
    tmp.id, tmp.codigo, tmp.nome, tmp.turno, tmp.id_serie, tmp.id_escola,
    origem.id, origem.codigo, origem.nome;

-- Confirma status transferido e ausência do snapshot do evento final atual.
SELECT
    a.id AS aluno_id,
    a.nome AS aluno,
    a.cgm,
    a.status AS aluno_status,
    a.id_turma AS turma_temporaria_id,
    tmp.codigo AS turma_temporaria_codigo,
    c.id AS ciclo_id,
    c.avaliacao_id,
    c.turma_avaliativa_id,
    c.turma_origem_id AS turma_original_id,
    c.status AS ciclo_status,
    c.snapshot_evento_atual_id,
    evento.tipo AS evento_tipo,
    snapshot.id AS snapshot_id,
    CASE
        WHEN a.status = 'transferido'
         AND c.status = 'concluida'
         AND c.snapshot_evento_atual_id IS NOT NULL
         AND snapshot.id IS NULL
        THEN 'OK: transferido sem snapshot final; fora do roster da turma original'
        ELSE 'ATENÇÃO: revisar antes de exportar'
    END AS conferencia
FROM alunos AS a
INNER JOIN turmas AS tmp ON tmp.id = a.id_turma
INNER JOIN avaliacao_turma_ciclos AS c
    ON c.avaliacao_id = @avaliacao_id
   AND c.turma_avaliativa_id = @turma_avaliativa_id
   AND c.turma_origem_id = @turma_origem_id
LEFT JOIN avaliacao_snapshot_eventos AS evento ON evento.id = c.snapshot_evento_atual_id
LEFT JOIN avaliacao_aluno_snapshots AS snapshot
    ON snapshot.evento_id = c.snapshot_evento_atual_id
   AND snapshot.aluno_id = a.id
WHERE tmp.id = (
    SELECT id FROM turmas WHERE codigo = @codigo_tmp LIMIT 1
)
ORDER BY a.nome, a.id;
