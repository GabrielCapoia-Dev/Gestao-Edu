SELECT
    escolas.nome AS escola,
    series.nome AS serie,
    turmas.nome AS turma,
    CASE turmas.turno
        WHEN 'manha' THEN 'Manhã'
        WHEN 'tarde' THEN 'Tarde'
        WHEN 'noite' THEN 'Noite'
        WHEN 'integral' THEN 'Integral'
        ELSE turmas.turno
    END AS turno,
    cc.nome AS componente,
    COALESCE(professores.nome, 'Sem professor') AS professor,
    COALESCE(professores.matricula, 'Não informado') AS matricula,
    COALESCE(professores.email, 'Não informado') AS email
FROM turma_componente_professor AS tcp
INNER JOIN turmas
    ON turmas.id = tcp.turma_id
INNER JOIN escolas
    ON escolas.id = turmas.id_escola
INNER JOIN series
    ON series.id = turmas.id_serie
INNER JOIN componentes_curriculares AS cc
    ON cc.id = tcp.componente_curricular_id
LEFT JOIN professores
    ON professores.id = tcp.professor_id
ORDER BY
    escolas.nome,
    series.nome,
    turmas.nome,
    cc.nome;
