-- Hub de testes: pautas do 1o ano. Uma unica instrucao; IDs explicitos e idempotentes.
INSERT INTO pautas
    (id, tipo_avaliacao_id, texto, componente_curricular_id, serie_id, status, created_at, updated_at)
VALUES
    (111, 2, 'Representa seu autorretrato.', 6, 5, 1, NOW(), NOW()),
    (112, 2, 'Identifica as cores primárias.', 6, 5, 1, NOW(), NOW()),
    (113, 2, 'Reconhece o retrato de Mona Lisa.', 6, 5, 1, NOW(), NOW()),
    (114, 2, 'Representa obra que condiz com um retrato.', 6, 5, 1, NOW(), NOW()),
    (115, 2, 'Participa expressivamente de brincadeiras cantadas.', 6, 5, 1, NOW(), NOW()),
    (116, 2, 'Demonstra expressividade nas brincadeiras de faz de conta e/ou imitação.', 6, 5, 1, NOW(), NOW()),
    (141, 2, 'Emprega meios para deslocar o corpo ou um segmento do corpo, numa unidade de tempo cada vez menor.', 7, 5, 1, NOW(), NOW()),
    (142, 2, 'Coordena o sentido da visão com os movimentos dos membros superiores.', 7, 5, 1, NOW(), NOW()),
    (143, 2, 'Coordena o sentido da visão com os movimentos dos membros inferiores.', 7, 5, 1, NOW(), NOW()),
    (144, 2, 'Reconhece o seu lado dominante.', 7, 5, 1, NOW(), NOW()),
    (145, 2, 'Joga o jogo do dominó tradicional respeitando suas regras.', 7, 5, 1, NOW(), NOW()),
    (146, 2, 'Diferencia o sistema de distância (longe e perto).', 7, 5, 1, NOW(), NOW()),
    (147, 2, 'Diferencia o sistema de localização (dentro, fora, em cima, embaixo).', 7, 5, 1, NOW(), NOW()),
    (148, 2, 'Diferencia conceitos de sucessão temporal (antes, agora e depois).', 7, 5, 1, NOW(), NOW()),
    (181, 2, 'Reconhece os laços de afeto que unem uma família.', 3, 5, 1, NOW(), NOW()),
    (182, 2, 'Diferencia famílias de outros tempos e da atualidade.', 3, 5, 1, NOW(), NOW()),
    (183, 2, 'Identifica festas e comemorações familiares como momentos que fortalecem os vínculos de afeto.', 3, 5, 1, NOW(), NOW()),
    (184, 2, 'Reconhece diferentes configurações familiares.', 3, 5, 1, NOW(), NOW()),
    (185, 2, 'Identifica atitudes de respeito, cuidado e colaboração no convívio familiar.', 3, 5, 1, NOW(), NOW()),
    (358, 2, 'Compreende e utiliza expressões de cortesia em situações simples (Thank you, Sorry, Please, Excuse me).', 8, 5, 1, NOW(), NOW()),
    (359, 2, 'Reconhece e nomeia cores em inglês.', 8, 5, 1, NOW(), NOW()),
    (360, 2, 'Reconhece e relaciona imagens ao vocabulário de brinquedos em inglês.', 8, 5, 1, NOW(), NOW()),
    (361, 2, 'Reconhece e nomeia formas geométricas em inglês (circle, square, triangle, rectangle).', 8, 5, 1, NOW(), NOW()),
    (362, 2, 'Reconhece e fala alguns números em inglês de 1 a 10.', 8, 5, 1, NOW(), NOW()),
    (363, 2, 'Reconhece e nomeia materiais escolares/artísticos em inglês (paper, glue, paint, yarn).', 8, 5, 1, NOW(), NOW()),
    (390, 2, 'Representa, por meio de desenho, partes do corpo humano.', 5, 5, 1, NOW(), NOW()),
    (391, 2, 'Relaciona as partes do corpo humano aos sentidos, reconhecendo o que podemos perceber por meio deles.', 5, 5, 1, NOW(), NOW()),
    (392, 2, 'Utiliza o corpo como referência para localizar elementos do local de vivência considerando referenciais espaciais.', 4, 5, 1, NOW(), NOW()),
    (393, 2, 'Lê palavra formada por sílaba canônica.', 1, 5, 1, NOW(), NOW()),
    (394, 2, 'Lê palavra formada por sílaba não canônica.', 1, 5, 1, NOW(), NOW()),
    (395, 2, 'Lê frases.', 1, 5, 1, NOW(), NOW()),
    (396, 2, 'Escreve o nome completo', 1, 5, 1, NOW(), NOW()),
    (397, 2, 'Identifica informações explícitas em texto ouvido.', 1, 5, 1, NOW(), NOW()),
    (398, 2, 'Reconhece o assunto do texto.', 1, 5, 1, NOW(), NOW()),
    (399, 2, 'Reconhece o gênero textual.', 1, 5, 1, NOW(), NOW()),
    (400, 2, 'Reconhece a finalidade do texto.', 1, 5, 1, NOW(), NOW()),
    (401, 2, 'Escreve palavras obedecendo aos princípios da ortografia.', 1, 5, 1, NOW(), NOW()),
    (402, 2, 'Escrever números convencionalmente, compreendendo o princípio de valor posicional dos números no Sistema de Numeração Decimal (SND).', 2, 5, 1, NOW(), NOW()),
    (403, 2, 'Lê números entre 0 e 50.', 2, 5, 1, NOW(), NOW()),
    (404, 2, 'Escreve números entre 0 e 50, em ordem ascendente.', 2, 5, 1, NOW(), NOW()),
    (405, 2, 'Associa a denominação do número a sua respectiva representação simbólica (em torno de 30 elementos).', 2, 5, 1, NOW(), NOW()),
    (406, 2, 'Calcula fatos básicos da adição, com a ideia de juntar, registrando os resultados numericamente.', 2, 5, 1, NOW(), NOW()),
    (407, 2, 'Calcula fatos básicos da adição, com a ideia de acrescentar, registrando os resultados numericamente.', 2, 5, 1, NOW(), NOW()),
    (408, 2, 'Calcula fatos básicos da subtração, com a ideia de tirar, registrando os resultados numericamente.', 2, 5, 1, NOW(), NOW()),
    (409, 2, 'Calcula fatos básicos da subtração, com a ideia de completar, registrando os resultados numericamente.', 2, 5, 1, NOW(), NOW()),
    (410, 2, 'Calcula fatos básicos da subtração, com a ideia de comparar, registrando os resultados numericamente.', 2, 5, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    updated_at = IF(tipo_avaliacao_id <> VALUES(tipo_avaliacao_id)
        OR NOT (texto <=> VALUES(texto))
        OR componente_curricular_id <> VALUES(componente_curricular_id)
        OR serie_id <> VALUES(serie_id)
        OR status <> VALUES(status), NOW(), updated_at),
    tipo_avaliacao_id = VALUES(tipo_avaliacao_id),
    texto = VALUES(texto),
    componente_curricular_id = VALUES(componente_curricular_id),
    serie_id = VALUES(serie_id),
    status = VALUES(status);

