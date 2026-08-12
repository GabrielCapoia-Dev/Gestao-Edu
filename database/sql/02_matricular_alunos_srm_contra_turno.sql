-- Matricula, como contra-turno, os alunos de Sala de Recursos Multifuncionais.
-- Fonte: aba Matriculados de Consolidacao.xlsx, curso
-- "SALA DE REC-MULTIFUNC.SERIES INICIAIS".
-- Compatibilidade: MySQL 8.0+.
--
-- Execute primeiro: 01_normalizar_turmas_srm.sql
--
-- A planilha possui 414 linhas no filtro. Este script usa 398 registros unicos;
-- 16 repeticoes exatas de escola + turno + CGM foram removidas.
--
-- Nao substitui matriculas existentes nem encerra contra-turnos em outra turma.
-- A execucao e idempotente: vinculos corretos nao sao duplicados.

SET NAMES utf8mb4;

DROP TEMPORARY TABLE IF EXISTS tmp_srm_planilha;
CREATE TEMPORARY TABLE tmp_srm_planilha (
    linha_planilha int unsigned NOT NULL,
    escola_nome varchar(255) NOT NULL,
    turno_srm enum('manha', 'tarde') NOT NULL,
    cgm varchar(255) NOT NULL,
    nome_planilha varchar(255) NOT NULL,
    PRIMARY KEY (escola_nome, turno_srm, cgm)
) ENGINE=InnoDB;

INSERT INTO tmp_srm_planilha
    (linha_planilha, escola_nome, turno_srm, cgm, nome_planilha)
VALUES
(760, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1017739340', 'ANA MILLY CONCEIÇÃO TEIXEIRA'),
(761, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1025237737', 'ANTONY EMANUEL DA COSTA DA'),
(762, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1024790173', 'MIGUEL TITATO DA SILVA'),
(763, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1013931220', 'EMANUELY FERNANDA DE MELO'),
(764, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1020125817', 'LUCCA DE OLIVEIRA MENDONÇA'),
(765, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1013933258', 'THOMAZ DE OLIVEIRA MENDONÇA'),
(766, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1022590215', 'ENZO GABRIEL PEDROSO DA SILVA'),
(767, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1030765881', 'DAVI DA SILVA'),
(768, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1025226107', 'HEYTOR MESSIAS XAVIER DE MATTOS'),
(769, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1027715199', 'MOISES RIBEIRO DOS SANTOS'),
(770, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1029625324', 'OZIEL RODRIGUES MARTINS'),
(771, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1025786838', 'EDUARDO RAMIREZ DOS SANTOS BUENO'),
(772, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1027801168', 'EMANUEL RIBEIRO DE LIMA'),
(773, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1020686274', 'JÚLIA DOS SANTOS DA SILVA'),
(774, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1020146938', 'HEYTOR MIGUEL ORRIOLE PEREIRA'),
(775, 'ESCOLA - Analides de Oliveira Caruso', 'tarde', '1019587327', 'NICOLAS MATHEUS FREITAS DE LIMA'),
(3746, 'ESCOLA - Analides de Oliveira Caruso', 'manha', '1019673487', 'BRENDA KAUANY DA SILVA LUPATELLI'),
(3747, 'ESCOLA - Analides de Oliveira Caruso', 'manha', '1019669110', 'BRYAN HENRIQUE DA SILVA LUPATELLI'),
(3748, 'ESCOLA - Analides de Oliveira Caruso', 'manha', '1027719470', 'JOSÉ FELIPE NEVES RODRIGUEZ'),
(3923, 'ESCOLA - Cândido Portinari', 'manha', '1020126651', 'THAYLOR GABRIEL DE OLIVEIRA AZARIAS'),
(3924, 'ESCOLA - Cândido Portinari', 'manha', '1022163775', 'ALEXSANDER LOMBARDINE FERREIRA'),
(3925, 'ESCOLA - Cândido Portinari', 'manha', '1022666300', 'MARIA CLARA OLIVEIRA DA SILVA'),
(3926, 'ESCOLA - Cândido Portinari', 'manha', '1028545297', 'MATEUS HENRIQUE DOS SANTOS MILITÃO'),
(3927, 'ESCOLA - Cândido Portinari', 'manha', '1017722944', 'HELOISA GABRIELLY DA SILVA MARCIANO'),
(3928, 'ESCOLA - Cândido Portinari', 'manha', '1025247163', 'DANIEL ARTHUR BRUNO'),
(3929, 'ESCOLA - Cândido Portinari', 'manha', '1022364240', 'DAVI AUGUSTO PEREIRA DA SILVA'),
(4085, 'ESCOLA - Cândido Portinari', 'tarde', '1028223974', 'JOÃO MIGUEL DOS SANTOS MARTINS'),
(4086, 'ESCOLA - Cândido Portinari', 'tarde', '1017716804', 'KAUAN SARTORI AGUIAR'),
(4087, 'ESCOLA - Cândido Portinari', 'tarde', '1022576751', 'CARLOS FELIPE DOS SANTOS'),
(4088, 'ESCOLA - Cândido Portinari', 'tarde', '1030736547', 'JOSE LUCIO FIGUEIREDO FILHO'),
(4089, 'ESCOLA - Cândido Portinari', 'tarde', '1022668605', 'JOSÉ AUGUSTO RAIS DE LIMA'),
(4090, 'ESCOLA - Cândido Portinari', 'tarde', '1024759020', 'MIGUEL MARCHITTI DE CARVALHO'),
(4091, 'ESCOLA - Cândido Portinari', 'tarde', '1022062146', 'ENZO SAQUETTI NAVARRO'),
(4092, 'ESCOLA - Cândido Portinari', 'tarde', '1026437411', 'MATHEUS HENRIQUE DA SILVA SOARES'),
(4093, 'ESCOLA - Cândido Portinari', 'tarde', '1021380829', 'GUILHERME MOREIRA LEITE'),
(4094, 'ESCOLA - Cândido Portinari', 'tarde', '1012700497', 'TAYLOR ANDRÉ MACHADO'),
(4463, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'manha', '1029936117', 'JOSÉ PAULO ROCHA BERNARDO'),
(4464, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'manha', '1022145793', 'JOÃO PEDRO DA SILVA'),
(4465, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'manha', '1020260641', 'GEOVANNA DE ALMEIDA LOPES'),
(4466, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'manha', '1027803870', 'MIGUEL SILVA DE SANTANA MARTINS'),
(4467, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'manha', '1024779889', 'DAVI FERREIRA DINIZ'),
(4468, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'manha', '1022107549', 'JOÃO GABRIEL DA SILVA FURTADO'),
(4469, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'manha', '1015363327', 'CALEBE MATEUS DE OLIVEIRA'),
(4470, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'manha', '1017637467', 'DAVI LUCCA LIMA VIEIRA'),
(4471, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'manha', '1025772390', 'ANA BEATRIZ SILVA MEDEIROS'),
(4639, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1022110302', 'ALICE LUNARDE DA SILVA'),
(4640, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1023477137', 'ARTHUR GABRIEL FREITAS GARCES'),
(4641, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1028223885', 'LUCAS LIMA SANTOS'),
(4642, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1011722845', 'RHAIURI GABRIELE DE SOUZA DA SILVA'),
(4643, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1024209160', 'ANA LUIZA DE SOUZA'),
(4644, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1027513146', 'JOÃO GABRIEL DA SILVA DOS SANTOS COSTA'),
(4645, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1020244620', 'LARA GABRIELLE DE JESUS FANTIN'),
(4646, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1020156666', 'MARIA EDUARDA MANTOVANI VIEIRA'),
(4647, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1021434783', 'MIGUEL LORENZO DA SILVA ANTUNES DE'),
(4648, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1020139192', 'MURIEL DE CARVALHO BORTOLOTO'),
(4649, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1019400200', 'DANIEL HORVATH BAPTISTA'),
(4650, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1017697940', 'FELIPE EMANUEL VOLPI DA SILVA'),
(4651, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1033185169', 'JOÃO MIGUEL BONASSOLI'),
(4652, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1024188589', 'YASMIN MARIA RODRIGUES ALVES PEREIRA'),
(4653, 'ESCOLA - Dr. Ângelo Moreira da Fonseca', 'tarde', '1026867963', 'KAIO GABRIEL FERNANDES PINTO'),
(4805, 'ESCOLA - Dr. Germano Norberto Rudner', 'manha', '1025464679', 'ANA BEATRIZ DE LAU TERASSINI'),
(4806, 'ESCOLA - Dr. Germano Norberto Rudner', 'manha', '1021604980', 'EDU LORENZO GOMES FERNANDES'),
(4807, 'ESCOLA - Dr. Germano Norberto Rudner', 'manha', '1024101866', 'LARISSA EMANUELY FRANCISQUETI'),
(4808, 'ESCOLA - Dr. Germano Norberto Rudner', 'manha', '1025226042', 'MATHEUS JOSÉ RACCANELLI DA SILVA'),
(4809, 'ESCOLA - Dr. Germano Norberto Rudner', 'manha', '1019793784', 'RAFAELA DE OLIVEIRA MENDES'),
(4900, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1016046104', 'BENÍCIO CAUMO DE BONA'),
(4901, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1022175501', 'LORENA SILVA DE JESUS'),
(4902, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1025060306', 'MARIA CLARA DOS SANTOS DO VALE'),
(4903, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1018583743', 'MICHEL HENRIQUE DE ARAUJO'),
(4904, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1032649854', 'GUSTAVO FERREIRA CAROBA'),
(4905, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1020162186', 'AMABILE SANTANA HIDALGO'),
(4906, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1020327827', 'MICAELLY HIPOLITO CABRERA'),
(4907, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1020191089', 'VITOR HUGO DA SILVA DELTRINO'),
(4908, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1024304392', 'GABRIEL ROBERTI MARQUES'),
(4909, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1027518121', 'LORENA PEREIRA DE SOUZA'),
(4910, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1022109789', 'FRANCISCO CARDOSO'),
(4911, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1026228650', 'THIAGO TAVARES GOUVÊA'),
(4912, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1022593575', 'VITOR DANIEL ROCHA BOSCARIOLI'),
(4913, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1022176818', 'MARIA GABRIELA CESÁRIO DA SILVA'),
(4914, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1033027644', 'FELIPE CAUMO DE BONA'),
(4915, 'ESCOLA - Dr. Germano Norberto Rudner', 'tarde', '1030992551', 'JOSÉ EMANOEL DENKE TEIXEIRA'),
(5543, 'ESCOLA - Jardim União', 'manha', '1022107140', 'ALICE VICTÓRIA DE OLIVEIRA FERREIRA'),
(5544, 'ESCOLA - Jardim União', 'manha', '1022290483', 'JOÃO GABRIEL LOURENÇO DE SOUZA'),
(5545, 'ESCOLA - Jardim União', 'manha', '1024215217', 'MATHEUS HENRIQUE RODRIGUES MANI'),
(5546, 'ESCOLA - Jardim União', 'manha', '1020269223', 'MIGUEL HENRIQUE RANGEL DE SOUZA'),
(5547, 'ESCOLA - Jardim União', 'manha', '1024729822', 'PEDRO FERNANDO CAMARGO DE DEUS'),
(5548, 'ESCOLA - Jardim União', 'manha', '1023605542', 'ANA LAURA LADEZ MOREIRA'),
(5549, 'ESCOLA - Jardim União', 'manha', '1024587866', 'DAVI RODRIGO FERREIRA FREITAS'),
(5550, 'ESCOLA - Jardim União', 'manha', '1024294079', 'EMANUELLY CAVALCANTI DE OLIVEIRA'),
(5551, 'ESCOLA - Jardim União', 'manha', '1028600786', 'MARIANA DE MENDONÇA PERAÇOLI'),
(5552, 'ESCOLA - Jardim União', 'manha', '1026166370', 'RAFAEL FORMIGONI MENEGUETI'),
(5553, 'ESCOLA - Jardim União', 'manha', '1026924800', 'DANIEL NATHAN DA SILVA COSTA'),
(5554, 'ESCOLA - Jardim União', 'manha', '1022178128', 'LORENZO SOARES BONFIM'),
(5555, 'ESCOLA - Jardim União', 'manha', '1022348643', 'MARIAH ALICE CAMILO SOUZA'),
(5556, 'ESCOLA - Jardim União', 'manha', '1019713438', 'MURYLO SANTOS DA SILVA'),
(5557, 'ESCOLA - Jardim União', 'manha', '1022528404', 'TIAGO FERNANDO ECHS SIMÕES'),
(5558, 'ESCOLA - Jardim União', 'manha', '1021332476', 'ANA VITÓRIA DA SILVA ALEXANDRE'),
(5559, 'ESCOLA - Jardim União', 'manha', '1018792245', 'GABRIEL HONORATO DA SILVA'),
(5560, 'ESCOLA - Jardim União', 'manha', '1019669633', 'GUSTAVO VINICIUS DE SOUZA DIAS'),
(5561, 'ESCOLA - Jardim União', 'manha', '1020366709', 'KAUÃ RODRIGO FERREIRA FREITAS'),
(5562, 'ESCOLA - Jardim União', 'manha', '1024289377', 'MARIA FERNANDA MAZZORANA DE OLIVEIRA'),
(5563, 'ESCOLA - Jardim União', 'manha', '1023011758', 'MIGUEL GONÇALVES ROCHA'),
(5771, 'ESCOLA - Jardim União', 'tarde', '1023494368', 'EMANUEL DE FRANÇA SANTANA DE JESUS'),
(5772, 'ESCOLA - Jardim União', 'tarde', '1024382911', 'JOÃO PEDRO SANCHES PEREIRA'),
(5773, 'ESCOLA - Jardim União', 'tarde', '1010399935', 'JOÃO VITOR DO PRADO DE ALMEIDA'),
(5774, 'ESCOLA - Jardim União', 'tarde', '1022344710', 'MIGUEL DE OLIVEIRA BOA SORTE'),
(5775, 'ESCOLA - Jardim União', 'tarde', '1024846802', 'DAVI LUCIANO ORTIZ'),
(5776, 'ESCOLA - Jardim União', 'tarde', '1028583970', 'MIGUEL HENRIQUE DAS NEVES SILVA'),
(5777, 'ESCOLA - Jardim União', 'tarde', '1030783391', 'RICARDO MONTEIRO ORTIZ'),
(5778, 'ESCOLA - Jardim União', 'tarde', '1019716984', 'GIOVANNA SOPHIE PISTORI TORRES'),
(5779, 'ESCOLA - Jardim União', 'tarde', '1025524671', 'HENZELL ADONAI DE SOUZA'),
(5780, 'ESCOLA - Jardim União', 'tarde', '1019522403', 'LUCAS JAVIER ZÚÑIGA MORELI'),
(5781, 'ESCOLA - Jardim União', 'tarde', '1022361283', 'LUCAS PONTES DA SILVA'),
(5782, 'ESCOLA - Jardim União', 'tarde', '1012372325', 'DAVI TOKIMASA'),
(5783, 'ESCOLA - Jardim União', 'tarde', '1023007599', 'VICTOR HUGO NOVAK DE OLIVEIRA'),
(6020, 'ESCOLA - Malba Tahan', 'manha', '1022181455', 'ELIEZER AUGUSTO ASSIS SANTANA'),
(6021, 'ESCOLA - Malba Tahan', 'manha', '1035271453', 'MATEUS NASCIMENTO OLIVEIRA ALVES'),
(6022, 'ESCOLA - Malba Tahan', 'manha', '1029230788', 'DANIEL HIRO DE FREITAS SAKITA'),
(6023, 'ESCOLA - Malba Tahan', 'manha', '1019714949', 'DAVI LUIZ LIMA BEGE'),
(6024, 'ESCOLA - Malba Tahan', 'manha', '1024192373', 'DIOGO DE OLIVEIRA DE ARO'),
(6025, 'ESCOLA - Malba Tahan', 'manha', '1033759629', 'ENZO GABRIEL DO NASCIMENTO ROSA'),
(6026, 'ESCOLA - Malba Tahan', 'manha', '1027898668', 'JOSÉ MIGUEL FENATO DE CARVALHO'),
(6027, 'ESCOLA - Malba Tahan', 'manha', '1028217796', 'PIETRA BASSO AMARAL'),
(6028, 'ESCOLA - Malba Tahan', 'manha', '1026111699', 'ARTHUR BALAM FIDÉLIS GALDINO'),
(6219, 'ESCOLA - Malba Tahan', 'tarde', '1020108645', 'MARCUS DAVI GONÇALVES DE SOUZA'),
(6220, 'ESCOLA - Malba Tahan', 'tarde', '1021592974', 'MURILLO MANARIN MORAES DA SILVA'),
(6221, 'ESCOLA - Malba Tahan', 'tarde', '1020222936', 'MATEUS FERRARI PANAGIO'),
(6222, 'ESCOLA - Malba Tahan', 'tarde', '1012025501', 'JOÃO GABRIEL DE OLIVEIRA PEREIRA'),
(6223, 'ESCOLA - Malba Tahan', 'tarde', '1027664225', 'ARTHUR GAEL MESSIAS SIRENA'),
(6224, 'ESCOLA - Malba Tahan', 'tarde', '1026218671', 'LOUISE SIQUEIRA COSTA'),
(6225, 'ESCOLA - Malba Tahan', 'tarde', '1022185230', 'MAÍSA EVANGELISTA GUIMARÃES'),
(6226, 'ESCOLA - Malba Tahan', 'tarde', '1026892941', 'ARTHUR MARQUES REBELLO'),
(6227, 'ESCOLA - Malba Tahan', 'tarde', '1027517699', 'ISAAC ROCHA CARLOS PIOVEZAN'),
(6228, 'ESCOLA - Malba Tahan', 'tarde', '1029815913', 'PEDRO MIGUEL SANTIAGO DIAS'),
(6229, 'ESCOLA - Malba Tahan', 'tarde', '1022766666', 'JOSÉ OTÁVIO POLASTRO SCAPOLAN'),
(6230, 'ESCOLA - Malba Tahan', 'tarde', '1024214873', 'JOSE CARLOS MORAES ZANATO RAMPAZZO'),
(6231, 'ESCOLA - Malba Tahan', 'tarde', '1019757966', 'RAFAEL VENTURIN CAMPANA'),
(6232, 'ESCOLA - Malba Tahan', 'tarde', '1015607633', 'HEITOR HENRIQUE ROSA HERRERA'),
(6233, 'ESCOLA - Malba Tahan', 'tarde', '1018986732', 'LAURA DOS SANTOS DOS REIS'),
(6234, 'ESCOLA - Malba Tahan', 'tarde', '1010052102', 'MIGUEL PIROLLA VENTORINI'),
(6235, 'ESCOLA - Malba Tahan', 'tarde', '1029258372', 'BENJAMIN FRANCISCO PIRES DA SILVA'),
(6236, 'ESCOLA - Malba Tahan', 'tarde', '1023501666', 'ENZO RAFAEL DOS SANTOS ALESSIO'),
(6237, 'ESCOLA - Malba Tahan', 'tarde', '1026234324', 'ESTER SILVA DE JESUS'),
(6238, 'ESCOLA - Malba Tahan', 'tarde', '1025606155', 'MARIA EDUARDA SEVERIANA SANTIAGO'),
(6416, 'ESCOLA - Manuel Bandeira', 'manha', '1030930696', 'BENÍCIO SALOMÃO DE OLIVEIRA GUIÃO'),
(6417, 'ESCOLA - Manuel Bandeira', 'manha', '1014160236', 'ISAAC RODRIGUES DE OLIVEIRA'),
(6418, 'ESCOLA - Manuel Bandeira', 'manha', '1017754366', 'VINICIUS MIGUEL ALVES DA SILVA'),
(6419, 'ESCOLA - Manuel Bandeira', 'manha', '1028585337', 'RAFAELA PALHARES BARBOSA'),
(6420, 'ESCOLA - Manuel Bandeira', 'manha', '1020143939', 'ARIANA DE CARVALHO'),
(6421, 'ESCOLA - Manuel Bandeira', 'manha', '1018544705', 'DAVI DE OLIVEIRA JOLO'),
(6422, 'ESCOLA - Manuel Bandeira', 'manha', '1013934220', 'FELIPE BORRIERO PEREIRA'),
(6423, 'ESCOLA - Manuel Bandeira', 'manha', '1018546872', 'FELIPE DE OLIVEIRA JOLO'),
(6424, 'ESCOLA - Manuel Bandeira', 'manha', '1014924627', 'MIGUEL ARTHUR DE OLIVEIRA RIGOLE'),
(6425, 'ESCOLA - Manuel Bandeira', 'manha', '1029139225', 'KAIO FERNANDO DE JESUS OLIVEIRA'),
(6426, 'ESCOLA - Manuel Bandeira', 'manha', '1024685787', 'SAMUEL GALVÃO NERI'),
(6427, 'ESCOLA - Manuel Bandeira', 'manha', '1032215684', 'BENJAMIN EDUARDO FERREIRA DE ALMEIDA'),
(6428, 'ESCOLA - Manuel Bandeira', 'manha', '1019659042', 'ELOISA MICHELON DE SOUZA'),
(6429, 'ESCOLA - Manuel Bandeira', 'manha', '1018428330', 'RAFAELA HAUBRICHT CORREA'),
(6430, 'ESCOLA - Manuel Bandeira', 'manha', '1031508718', 'FERNANDO FORMICOLI DE MENDONÇA'),
(6431, 'ESCOLA - Manuel Bandeira', 'manha', '1020131825', 'ENZO GABRIEL VERON BENITEZ FRANCO'),
(6432, 'ESCOLA - Manuel Bandeira', 'manha', '1029106432', 'MATHEUS BENJAMIN VERON BENITEZ'),
(6589, 'ESCOLA - Manuel Bandeira', 'tarde', '1024316900', 'ANA BEATRIZ DA SILVA DE BARROS'),
(6590, 'ESCOLA - Manuel Bandeira', 'tarde', '1032284708', 'CALEBE OTAVIO DE AZEVEDO DONADONI'),
(6591, 'ESCOLA - Manuel Bandeira', 'tarde', '1028707920', 'JONAS GABRIEL GADELHA GUEDES DA'),
(6592, 'ESCOLA - Manuel Bandeira', 'tarde', '1027698316', 'MIGUEL SOUZA ARNS'),
(6593, 'ESCOLA - Manuel Bandeira', 'tarde', '1025444031', 'ADRIAN DE OLIVEIRA SOUSA'),
(6594, 'ESCOLA - Manuel Bandeira', 'tarde', '1033029469', 'BERNARDO GREGOR PAGANINI'),
(6595, 'ESCOLA - Manuel Bandeira', 'tarde', '1018546813', 'EMANUEL DE SOUZA LIVONI'),
(6596, 'ESCOLA - Manuel Bandeira', 'tarde', '1020213112', 'ENZO GABRIEL MAFORT TORRES'),
(6597, 'ESCOLA - Manuel Bandeira', 'tarde', '1028858287', 'ISABELA PASSOS ROMERO'),
(6598, 'ESCOLA - Manuel Bandeira', 'tarde', '1016086254', 'WENDRYO GABRIEL OLIVEIRA DE SOUZA'),
(6599, 'ESCOLA - Manuel Bandeira', 'tarde', '1028487840', 'HELENA CORRÊA QUINAGLIA'),
(6600, 'ESCOLA - Manuel Bandeira', 'tarde', '1022570982', 'REBECCA MARCHI ALLY DA SILVA'),
(6601, 'ESCOLA - Manuel Bandeira', 'tarde', '1030779017', 'ISAAC GABRIEL OLIVEIRA'),
(7167, 'ESCOLA - Ouro Branco', 'manha', '1025781690', 'ANTHONY MIGUEL DE OLIVEIRA GOMES'),
(7168, 'ESCOLA - Ouro Branco', 'manha', '1026502612', 'SAMUEL NOVAIS MAGALHAES'),
(7169, 'ESCOLA - Ouro Branco', 'manha', '1035276463', 'RAEL OLIVEIRA DE VASCONCELOS'),
(7170, 'ESCOLA - Ouro Branco', 'manha', '1026127285', 'ÍCARO DO NASCIMENTO DE OLIVEIRA'),
(7171, 'ESCOLA - Ouro Branco', 'manha', '1021380632', 'HEITOR GABRIEL DE FREITAS FERREIRA'),
(7172, 'ESCOLA - Ouro Branco', 'manha', '1024788454', 'SAMUEL BIAGGI DE ANDRADE'),
(7173, 'ESCOLA - Ouro Branco', 'manha', '1024669013', 'ARTHUR MIGUEL RIBEIRO CASTANHO SILVA'),
(7174, 'ESCOLA - Ouro Branco', 'manha', '1024714701', 'BRENDON DA SILVA DE PAULA'),
(7175, 'ESCOLA - Ouro Branco', 'manha', '1027486343', 'NATHAN PEREIRA DO CARMO'),
(7176, 'ESCOLA - Ouro Branco', 'manha', '1025808882', 'PEDRO HENRIQUE DO NASCIMENTO SAMPAIO'),
(7177, 'ESCOLA - Ouro Branco', 'manha', '1030811077', 'LORENZO BENEDETTO RODRIGUES DA'),
(7178, 'ESCOLA - Ouro Branco', 'manha', '1024501589', 'NATHAN FELIPE CANDIDO FERREIRA'),
(7179, 'ESCOLA - Ouro Branco', 'manha', '1013748019', 'ENZO GABRIEL DO NASCIMENTO'),
(7180, 'ESCOLA - Ouro Branco', 'manha', '1022381438', 'ANNA JULLIA DE LIMA DE ALMEIDA'),
(7398, 'ESCOLA - Ouro Branco', 'tarde', '1020121650', 'DAVI EMANUEL DE FREITAS'),
(7399, 'ESCOLA - Ouro Branco', 'tarde', '1026180763', 'LORENZO AURÉLIO DOS SANTOS GOMES'),
(7400, 'ESCOLA - Ouro Branco', 'tarde', '1025233243', 'ANTHONY DE SOUZA DE OLIVEIRA'),
(7401, 'ESCOLA - Ouro Branco', 'tarde', '1026149068', 'CARLOS DANIEL OLIVEIRA ALVES'),
(7402, 'ESCOLA - Ouro Branco', 'tarde', '1022525758', 'JULLYA CASSIANA LIMA'),
(7403, 'ESCOLA - Ouro Branco', 'tarde', '1019686589', 'PEDRO HENRIQUE VIEIRA DA SILVA'),
(7404, 'ESCOLA - Ouro Branco', 'tarde', '1024808706', 'ELLOÁ VITORIA DE SOUZA DE OLIVEIRA'),
(7405, 'ESCOLA - Ouro Branco', 'tarde', '1027525276', 'HELENA AYUMI DOS SANTOS SUZUKI'),
(7406, 'ESCOLA - Ouro Branco', 'tarde', '1023870548', 'LEONARDO NUNES PEREIRA'),
(7407, 'ESCOLA - Ouro Branco', 'tarde', '1019691477', 'GABRIEL JORGE LEITE'),
(7408, 'ESCOLA - Ouro Branco', 'tarde', '1032239680', 'KAUÊ LUCAS LEÃO CORREIA'),
(7409, 'ESCOLA - Ouro Branco', 'tarde', '1022366021', 'MIGUEL SILVA DE PAULA'),
(7410, 'ESCOLA - Ouro Branco', 'tarde', '1022348325', 'HAYLA CRISTINA NEVES DA CUNHA'),
(7582, 'ESCOLA - Padre José de Anchieta', 'tarde', '1017763250', 'VINICIUS ROCHA ALVES'),
(7583, 'ESCOLA - Padre José de Anchieta', 'tarde', '1029870817', 'CATARINA CAÑETE LIGANANI'),
(7584, 'ESCOLA - Padre José de Anchieta', 'tarde', '1023076965', 'FELIPE EMANOEL FERREIRA'),
(7585, 'ESCOLA - Padre José de Anchieta', 'tarde', '1027522986', 'ARTHUR EMANUEL DA CONCEIÇÃO'),
(7586, 'ESCOLA - Padre José de Anchieta', 'tarde', '1012696457', 'LORENZO DE OLIVEIRA ZOPELLARO'),
(7587, 'ESCOLA - Padre José de Anchieta', 'tarde', '1017816213', 'LORENZO PEREIRA RIBEIRO'),
(7588, 'ESCOLA - Padre José de Anchieta', 'tarde', '1022307750', 'GERALDO POLTRONIÉRI JÚNIOR'),
(7741, 'ESCOLA - Papa Pio XII', 'tarde', '1022698423', 'DANIEL LOPES COUTINHO'),
(7742, 'ESCOLA - Papa Pio XII', 'tarde', '1027519179', 'HEITOR DOS SANTOS PINHEIRO'),
(7743, 'ESCOLA - Papa Pio XII', 'tarde', '1026840542', 'MILENA DOS SANTOS TOMAZ'),
(7744, 'ESCOLA - Papa Pio XII', 'tarde', '1019093448', 'NAYENY VITÓRIA DOS SANTOS DE OLIVEIRA'),
(7745, 'ESCOLA - Papa Pio XII', 'tarde', '1018976338', 'EMANUELY SILVA NASCIMENTO DOS SANTOS'),
(7746, 'ESCOLA - Papa Pio XII', 'tarde', '1024899540', 'LEONARDO AMATI DOS SANTOS'),
(7747, 'ESCOLA - Papa Pio XII', 'tarde', '1022271845', 'HELOIZA DA SILVA PEREIRA DE MELO'),
(7748, 'ESCOLA - Papa Pio XII', 'tarde', '1028191568', 'RAFAEL HENRIQUE DA SILVA PIO'),
(7749, 'ESCOLA - Papa Pio XII', 'tarde', '1020671595', 'THEO DOS SANTOS GARCIA'),
(7750, 'ESCOLA - Papa Pio XII', 'tarde', '1011389259', 'EVERALDO APARECIDO TEODORO'),
(7751, 'ESCOLA - Papa Pio XII', 'tarde', '1011388716', 'REINALDO TEODORO'),
(8068, 'ESCOLA - Paulo Freire', 'manha', '1028551009', 'ALICE TAMURA BOVA'),
(8069, 'ESCOLA - Paulo Freire', 'manha', '1024197103', 'ENZO DA SILVA ANANIAS'),
(8070, 'ESCOLA - Paulo Freire', 'manha', '1028551092', 'HELENA TAMURA BOVA'),
(8071, 'ESCOLA - Paulo Freire', 'manha', '1028499597', 'BRYAN EMANUEL SOUZA DA SILVA'),
(8072, 'ESCOLA - Paulo Freire', 'manha', '1026828062', 'JHONATAN MIGUEL DOS SANTOS LIMA'),
(8073, 'ESCOLA - Paulo Freire', 'manha', '1021472456', 'SAMUEL OLIVEIRA ALVES MARQUES'),
(8074, 'ESCOLA - Paulo Freire', 'manha', '1020201190', 'LUCAS HENRIQUE MONTEIRO BONATTI'),
(8075, 'ESCOLA - Paulo Freire', 'manha', '1020325611', 'MARIA EDUARDA MOTA PEREIRA'),
(8076, 'ESCOLA - Paulo Freire', 'manha', '1024189429', 'PEDRO JORGE CAZAROTI FAZOLIN'),
(8077, 'ESCOLA - Paulo Freire', 'manha', '1015442758', 'MARIA VITÓRIA DE SOUZA DOS REIS'),
(8078, 'ESCOLA - Paulo Freire', 'manha', '1026870522', 'JOSE RODRIGO KLAIN PALMA'),
(8383, 'ESCOLA - Paulo Freire', 'tarde', '1019718197', 'EMANUELY DA SILVA SABINO'),
(8384, 'ESCOLA - Paulo Freire', 'tarde', '1019780402', 'HENRY GABRIEL MARTINS PINHEIRO'),
(8385, 'ESCOLA - Paulo Freire', 'tarde', '1022180602', 'LARIANE CALDEIRA DA SILVA'),
(8386, 'ESCOLA - Paulo Freire', 'tarde', '1020301747', 'MARIA RAFAELE GARBE NUNES'),
(8387, 'ESCOLA - Paulo Freire', 'tarde', '1028650260', 'PEDRO EDUARDO ROCHA DOS SANTOS'),
(8388, 'ESCOLA - Paulo Freire', 'tarde', '1019723697', 'ARTHUR FIUZA DE OLIVEIRA'),
(8389, 'ESCOLA - Paulo Freire', 'tarde', '1015640347', 'JOÃO LUCCA RODRIGUES DOLCI'),
(8390, 'ESCOLA - Paulo Freire', 'tarde', '1024190354', 'MILUFFER MAITÉ ORTIZ AYALA'),
(8391, 'ESCOLA - Paulo Freire', 'tarde', '1022473111', 'KAUÃ HENRIQUE DA SILVA FERREIRA'),
(8392, 'ESCOLA - Paulo Freire', 'tarde', '1024785196', 'ANA TERESA DE LIMA MORENO'),
(8393, 'ESCOLA - Paulo Freire', 'tarde', '1028297030', 'ARTHUR CIRQUEIRA DE FARIA'),
(8394, 'ESCOLA - Paulo Freire', 'tarde', '1024397161', 'DAVI LUCCA DE ARAUJO CUNHA'),
(8395, 'ESCOLA - Paulo Freire', 'tarde', '1028344410', 'PEDRO HENRIQUE ARAUJO DE OLIVEIRA'),
(8396, 'ESCOLA - Paulo Freire', 'tarde', '1027241812', 'JOSÉ MIGUEL RUIS FERNANDES'),
(8397, 'ESCOLA - Paulo Freire', 'tarde', '1027717817', 'LORENZO HENRIQUE RAMOS BARBOSA'),
(8398, 'ESCOLA - Paulo Freire', 'tarde', '1027269180', 'VALENTYNA GABRIELLY QUADRELI'),
(8675, 'ESCOLA - Rui Barbosa', 'manha', '1027223091', 'IZADORA GABRIELLY BARÃO CABRERA'),
(8676, 'ESCOLA - Rui Barbosa', 'manha', '1020123946', 'JOÃO PEDRO DA SILVA LIMA'),
(8677, 'ESCOLA - Rui Barbosa', 'manha', '1029342241', 'DAVI LUIZ DA SILVA SIMÃO'),
(8678, 'ESCOLA - Rui Barbosa', 'manha', '1032284309', 'LORENZZO THARLON CORTONEZI PEREIRA'),
(8679, 'ESCOLA - Rui Barbosa', 'manha', '1017790397', 'LOURY LUHAN LESCHOPHER'),
(8680, 'ESCOLA - Rui Barbosa', 'manha', '1017700800', 'PEDRO ASAPH FREITAS BAZANELA'),
(8681, 'ESCOLA - Rui Barbosa', 'manha', '1022389196', 'DANIEL FERREIRA LABORÃO'),
(8682, 'ESCOLA - Rui Barbosa', 'manha', '1022545767', 'ENZO EDUARDO SANTOS NASCIMENTO'),
(8683, 'ESCOLA - Rui Barbosa', 'manha', '1011277833', 'CAIO FELIPE DA SILVA'),
(8684, 'ESCOLA - Rui Barbosa', 'manha', '1019841975', 'ETTORE GIUSSEPP MOURA DELLATORRE'),
(8685, 'ESCOLA - Rui Barbosa', 'manha', '1019801566', 'JOÃO VICTOR RODRIGUES BEIRÃO'),
(8686, 'ESCOLA - Rui Barbosa', 'manha', '1024203197', 'SAMUEL BESERRA DE SOUZA'),
(8687, 'ESCOLA - Rui Barbosa', 'manha', '1024845636', 'MIGUEL ANTONIO ROQUE'),
(8688, 'ESCOLA - Rui Barbosa', 'manha', '1023193031', 'ARTHUR MIGUEL CELESTINO DALCOLE'),
(8933, 'ESCOLA - Rui Barbosa', 'tarde', '1021540095', 'ALEJANDRO GABRIEL DE SOUZA'),
(8934, 'ESCOLA - Rui Barbosa', 'tarde', '1010546695', 'DANIEL ROBERT GABINI FERREIRA DO'),
(8935, 'ESCOLA - Rui Barbosa', 'tarde', '1017696528', 'JOÃO GUILHERME GABINI FERREIRA DO'),
(8936, 'ESCOLA - Rui Barbosa', 'tarde', '1014690448', 'JOAO VICTOR DUTRA MONTEIRO'),
(8937, 'ESCOLA - Rui Barbosa', 'tarde', '1020189238', 'HELENA DE MELO RODRIGUES'),
(8938, 'ESCOLA - Rui Barbosa', 'tarde', '1021089946', 'LORRAYNE VITORIA DE SOUZA'),
(8939, 'ESCOLA - Rui Barbosa', 'tarde', '1024846373', 'CECÍLIA NEVES DE CARVALHO'),
(8940, 'ESCOLA - Rui Barbosa', 'tarde', '1027526566', 'DAVI ARTONI PAUKA DA SILVA'),
(8941, 'ESCOLA - Rui Barbosa', 'tarde', '1022581941', 'MARCIO ANTONIO FERREIRA SOUZA'),
(8942, 'ESCOLA - Rui Barbosa', 'tarde', '1026828240', 'JOSÉ FELIPE DE PAULA PEREIRA'),
(8943, 'ESCOLA - Rui Barbosa', 'tarde', '1024302659', 'DAVI DOS SANTOS CAVALCANTE'),
(8944, 'ESCOLA - Rui Barbosa', 'tarde', '1020220518', 'JOÃO PEDRO FELICIANO DA SILVA'),
(8945, 'ESCOLA - Rui Barbosa', 'tarde', '1026548507', 'NATHAN DOS SANTOS CARVALHO'),
(8946, 'ESCOLA - Rui Barbosa', 'tarde', '1022321109', 'PEDRO RODRIGUES GOMES'),
(8947, 'ESCOLA - Rui Barbosa', 'tarde', '1021713984', 'VITÓRIA ANGELOTTO ABRUCEIS'),
(8948, 'ESCOLA - Rui Barbosa', 'tarde', '1020191437', 'BENJAMIN THEODORO GOMES'),
(8949, 'ESCOLA - Rui Barbosa', 'tarde', '1025233723', 'BRIAN TAVARES FIORI'),
(8950, 'ESCOLA - Rui Barbosa', 'tarde', '1028000371', 'BRYAN DE SOUSA AFONSO SOBRINHO'),
(8951, 'ESCOLA - Rui Barbosa', 'tarde', '1025376672', 'GABRIEL PEDROSO DE SOUZA PAS'),
(8952, 'ESCOLA - Rui Barbosa', 'tarde', '1017969001', 'LARA DE VITA DOS SANTOS'),
(9113, 'ESCOLA - São Cristóvão', 'manha', '1025190307', 'MARIA ESTHER DOS SANTOS LOPES'),
(9114, 'ESCOLA - São Cristóvão', 'manha', '1026107527', 'BRYAN FELLIPE PEREIRA'),
(9115, 'ESCOLA - São Cristóvão', 'manha', '1023300920', 'CARLOS HENRIQUE DA SILVA MARTINS'),
(9116, 'ESCOLA - São Cristóvão', 'manha', '1036601473', 'GABRIEL BATISTA DA SILVA'),
(9117, 'ESCOLA - São Cristóvão', 'manha', '1028391818', 'HUGO BARRETO DE OLIVEIRA'),
(9118, 'ESCOLA - São Cristóvão', 'manha', '1030210847', 'SAMUEL ALVES CAMPOS'),
(9119, 'ESCOLA - São Cristóvão', 'manha', '1022562548', 'YASMIN VICTORIA DOMINGUES DOS SANTOS'),
(9120, 'ESCOLA - São Cristóvão', 'manha', '1033350623', 'SAMUEL GONÇALVES TELLEZ'),
(9121, 'ESCOLA - São Cristóvão', 'manha', '1020068120', 'MARIA EDUARDA DIAS DA SILVA'),
(9122, 'ESCOLA - São Cristóvão', 'manha', '1017915920', 'PEDRO HENRIQUE VIEIRA CHAGAS DO'),
(9123, 'ESCOLA - São Cristóvão', 'manha', '1022180440', 'ALAN BRAYAN MONTEIRO MORENO'),
(9276, 'ESCOLA - São Cristóvão', 'tarde', '1020476296', 'KAUÃ HENRIQUE RODRIGUES SANTOS'),
(9277, 'ESCOLA - São Cristóvão', 'tarde', '1024406349', 'SAMUEL HENRIQUE PINHEIRO RAMOS'),
(9278, 'ESCOLA - São Cristóvão', 'tarde', '1022324370', 'HEITOR TOLOMEOTI DELAPORTE'),
(9279, 'ESCOLA - São Cristóvão', 'tarde', '1028217346', 'JOÃO ÍCARO NASCIMENTO BEZERRA'),
(9280, 'ESCOLA - São Cristóvão', 'tarde', '1025405737', 'ANTONELLA DE AGUIAR DE AQUINO'),
(9281, 'ESCOLA - São Cristóvão', 'tarde', '1027969433', 'NALA HELENA CONTI DE OLIVEIRA'),
(9282, 'ESCOLA - São Cristóvão', 'tarde', '1021604522', 'EMANUELY CAMPOS TABORDA'),
(9496, 'ESCOLA - São Francisco de Assis', 'manha', '1024298112', 'HANNIEL SCANAVACA ZANGARI QUIROGA'),
(9497, 'ESCOLA - São Francisco de Assis', 'manha', '1020851020', 'SAMAEL ISAAC SCANAVACA ZANGARI'),
(9498, 'ESCOLA - São Francisco de Assis', 'manha', '1020107444', 'HELOÁ TEIXEIRA NALIN'),
(9499, 'ESCOLA - São Francisco de Assis', 'manha', '1028989349', 'BRYAN CARVALHO NEVES'),
(9500, 'ESCOLA - São Francisco de Assis', 'manha', '1030160017', 'HELENA DOS REIS VERNASQUI'),
(9501, 'ESCOLA - São Francisco de Assis', 'manha', '1031049250', 'JORGE MIGUEL DOS SANTOS DOPP'),
(9502, 'ESCOLA - São Francisco de Assis', 'manha', '1022429643', 'MIGUEL HENRIQUE DO VALLE HOLANDA'),
(9503, 'ESCOLA - São Francisco de Assis', 'manha', '1028948480', 'THÉO FAUZEL LEMES'),
(9504, 'ESCOLA - São Francisco de Assis', 'manha', '1031049098', 'JOAQUIM DE CARVALHO CARINI'),
(9505, 'ESCOLA - São Francisco de Assis', 'manha', '1024517329', 'HELOÍSA ELIAS DE SOUZA'),
(9712, 'ESCOLA - São Francisco de Assis', 'tarde', '1026570103', 'GABRIEL GUTIERREZ SEGATELI'),
(9713, 'ESCOLA - São Francisco de Assis', 'tarde', '1022592692', 'WESLEY MIGUEL DA SILVA'),
(9714, 'ESCOLA - São Francisco de Assis', 'tarde', '1022381306', 'JOÃO MIGUEL DA ROCHA SOARES'),
(9715, 'ESCOLA - São Francisco de Assis', 'tarde', '1027176638', 'DAVI KHALED DOS SANTOS BRETAS'),
(9716, 'ESCOLA - São Francisco de Assis', 'tarde', '1013947070', 'MANUELA VASSI DOS SANTOS'),
(9717, 'ESCOLA - São Francisco de Assis', 'tarde', '1026152930', 'BENJAMIN MACEDO FERRO'),
(9718, 'ESCOLA - São Francisco de Assis', 'tarde', '1027555353', 'THÉO CAZARIN'),
(9719, 'ESCOLA - São Francisco de Assis', 'tarde', '1023203703', 'HEITOR MULLER DE OLIVEIRA'),
(9720, 'ESCOLA - São Francisco de Assis', 'tarde', '1026393414', 'ARTHUR MANOEL ANDRADE SILVA'),
(9945, 'ESCOLA - Sebastião de Mattos', 'manha', '1013933886', 'PEDRO AVACI LUCENA FERREIRA'),
(9946, 'ESCOLA - Sebastião de Mattos', 'manha', '1024753570', 'VALENTINA YASMIN PEREIRA'),
(9947, 'ESCOLA - Sebastião de Mattos', 'manha', '1025334449', 'VICTOR GABRIEL VOLL DANTAS'),
(9948, 'ESCOLA - Sebastião de Mattos', 'manha', '1032360854', 'ARTHUR DE ANDRADE MACHADO'),
(9949, 'ESCOLA - Sebastião de Mattos', 'manha', '1021103256', 'BRENNO DE OLIVEIRA SABINO'),
(9950, 'ESCOLA - Sebastião de Mattos', 'manha', '1024340038', 'ENDRIO FERNANDO CAVICHIOLI RODRIGUES'),
(9951, 'ESCOLA - Sebastião de Mattos', 'manha', '1026153308', 'MIGUEL HENRIQUE DA SILVA FERREIRA'),
(9952, 'ESCOLA - Sebastião de Mattos', 'manha', '1024205440', 'EMANUEL BARBOSA DE OLIVEIRA'),
(9953, 'ESCOLA - Sebastião de Mattos', 'manha', '1025237516', 'UILLIANS CLÁUDIO RIBAS GIMENES JÚNIOR'),
(9954, 'ESCOLA - Sebastião de Mattos', 'manha', '1027522714', 'SAMUEL WILLIAN MOREIRA JAMBERSI'),
(10176, 'ESCOLA - Sebastião de Mattos', 'tarde', '1026152700', 'BRUNO MICHELLI MACHADO'),
(10177, 'ESCOLA - Sebastião de Mattos', 'tarde', '1027524466', 'JOÃO MIGUEL BENITEZ ALVES'),
(10178, 'ESCOLA - Sebastião de Mattos', 'tarde', '1026816684', 'MATEUS VEDOVETO TULLER'),
(10179, 'ESCOLA - Sebastião de Mattos', 'tarde', '1019667320', 'AUGUSTO DA COSTA ROCHA'),
(10180, 'ESCOLA - Sebastião de Mattos', 'tarde', '1023520083', 'LÍVIA AVIGO ALVES'),
(10181, 'ESCOLA - Sebastião de Mattos', 'tarde', '1024211459', 'LORENA NOGUEIRA VIEIRA'),
(10182, 'ESCOLA - Sebastião de Mattos', 'tarde', '1020777431', 'GABRIEL FRANCISCO BRUNO'),
(10183, 'ESCOLA - Sebastião de Mattos', 'tarde', '1023764772', 'ANA SOPHIA VILELA DA SILVA'),
(10184, 'ESCOLA - Sebastião de Mattos', 'tarde', '1020608273', 'BRYAN HENRIQUE DOS SANTOS DUARTE'),
(10185, 'ESCOLA - Sebastião de Mattos', 'tarde', '1020237690', 'GUSTAVO GOMES DE AZEVEDO MOTA'),
(10541, 'ESCOLA - Senador Souza Naves', 'manha', '1023196928', 'JOÃO PEDRO PASCHOALETO DE OLIVEIRA'),
(10542, 'ESCOLA - Senador Souza Naves', 'manha', '1028701779', 'MURILO CORDEIRO ALEXANDRE'),
(10543, 'ESCOLA - Senador Souza Naves', 'manha', '1022345172', 'MARIA EDUARDA FERNANDES DA SILVA'),
(10544, 'ESCOLA - Senador Souza Naves', 'manha', '1028533000', 'ELOÁ SILVA JERONIMO'),
(10545, 'ESCOLA - Senador Souza Naves', 'manha', '1022306614', 'ISAACK GABRIEL DE SOUZA NEVES'),
(10546, 'ESCOLA - Senador Souza Naves', 'manha', '1026122291', 'MIGUEL FRANCISCO RODRIGUES SANTOS'),
(10547, 'ESCOLA - Senador Souza Naves', 'manha', '1013963360', 'NICOLAS FERNANDES BARBOSA'),
(10548, 'ESCOLA - Senador Souza Naves', 'manha', '1023021508', 'ANTONELLA MARCONI COLUCCI LEMES'),
(10549, 'ESCOLA - Senador Souza Naves', 'manha', '1024291738', 'JOSÉ MIGUEL ROCHA JUSTINO'),
(10550, 'ESCOLA - Senador Souza Naves', 'manha', '1024197383', 'LUIZ ANTONIO MARCATO DOS SANTOS'),
(10551, 'ESCOLA - Senador Souza Naves', 'manha', '1024330571', 'MARIA FERNANDA VIALE GOMES'),
(10552, 'ESCOLA - Senador Souza Naves', 'manha', '1024917521', 'ANNELISE MIYUKI MANDUCA RIBEIRO'),
(10553, 'ESCOLA - Senador Souza Naves', 'manha', '1022335509', 'BENJAMIM RODRIGUES CASSAN DE FREITAS'),
(10554, 'ESCOLA - Senador Souza Naves', 'manha', '1018206249', 'ISABELLA FERREIRA DE MOURA'),
(10555, 'ESCOLA - Senador Souza Naves', 'manha', '1014244359', 'LAURA GIMENES DE AQUINO'),
(10788, 'ESCOLA - Senador Souza Naves', 'tarde', '1016380640', 'LAURA DINIZ DE SOUZA'),
(10789, 'ESCOLA - Senador Souza Naves', 'tarde', '1026122062', 'LUIZ MIGUEL SANTOS DE AQUINO'),
(10790, 'ESCOLA - Senador Souza Naves', 'tarde', '1019790173', 'BENÍCIO DE SOUZA BRIGNOLI'),
(10791, 'ESCOLA - Senador Souza Naves', 'tarde', '1024199599', 'FILIPE HANIEL BARROS DE ALMEIDA'),
(10792, 'ESCOLA - Senador Souza Naves', 'tarde', '1025770320', 'MIGUEL BARBOSA DE LIMA'),
(10793, 'ESCOLA - Senador Souza Naves', 'tarde', '1022581143', 'MIGUEL PEREIRA FERNANDES'),
(10794, 'ESCOLA - Senador Souza Naves', 'tarde', '1024382172', 'MIGUEL SOUZA LIMA'),
(10795, 'ESCOLA - Senador Souza Naves', 'tarde', '1025056872', 'ARTHUR HENRIQUE DA SILVA'),
(10796, 'ESCOLA - Senador Souza Naves', 'tarde', '1011878390', 'BEATRIZ DOS SANTOS FERREIRA'),
(10797, 'ESCOLA - Senador Souza Naves', 'tarde', '1024326256', 'BERNARDO SALVADOR COMAR'),
(10798, 'ESCOLA - Senador Souza Naves', 'tarde', '1022106631', 'MARCOS VINÍCIUS TAVARES JERONIMO'),
(10799, 'ESCOLA - Senador Souza Naves', 'tarde', '1020298630', 'PEDRO MIGUEL DE FREITAS BORGES'),
(10800, 'ESCOLA - Senador Souza Naves', 'tarde', '1022184861', 'MIGUEL DE PAULA VITURINO'),
(10801, 'ESCOLA - Senador Souza Naves', 'tarde', '1019719282', 'SARA HELENA VIDAL BRITO'),
(10802, 'ESCOLA - Senador Souza Naves', 'tarde', '1017569275', 'YASMIN PEREIRA DE SOUZA AQUINO'),
(10929, 'ESCOLA - Serra dos Dourados', 'manha', '1017692891', 'LUCAS RODRIGUES RUIZ'),
(10930, 'ESCOLA - Serra dos Dourados', 'manha', '1021258012', 'ENZO PAGANARDI ANTONELLI'),
(10931, 'ESCOLA - Serra dos Dourados', 'manha', '1019735776', 'DAVI HENRIQUE MARINHO'),
(10932, 'ESCOLA - Serra dos Dourados', 'manha', '1024237920', 'LARA PAULA VIANA FERRARI'),
(10933, 'ESCOLA - Serra dos Dourados', 'manha', '1022284610', 'EMANUEL DE SOUZA RODRIGUES'),
(10934, 'ESCOLA - Serra dos Dourados', 'manha', '1028511546', 'GABRIEL PANDINI DA SILVA'),
(11057, 'ESCOLA - Serra dos Dourados', 'tarde', '1020212205', 'JOÃO LUCAS TELES DOS SANTOS'),
(11058, 'ESCOLA - Serra dos Dourados', 'tarde', '1022281980', 'LUANA DO CARMO FERREIRA'),
(11059, 'ESCOLA - Serra dos Dourados', 'tarde', '1024290243', 'FERNANDO NAZO ALCANTARA DA SILVA'),
(11060, 'ESCOLA - Serra dos Dourados', 'tarde', '1015742310', 'TAIRON HENRIQUE DOMINGOS DE OLIVEIRA'),
(11061, 'ESCOLA - Serra dos Dourados', 'tarde', '1020605819', 'PHIETRA VALENTHINA ANTONELLI DE SOUZA'),
(11062, 'ESCOLA - Serra dos Dourados', 'tarde', '1021355638', 'SAMUEL HENRIQUE DA SILVA DE ALMEIDA'),
(11063, 'ESCOLA - Serra dos Dourados', 'tarde', '1028487653', 'LUIZ RENATO FRANCISCO BRAGA'),
(11064, 'ESCOLA - Serra dos Dourados', 'tarde', '1014685002', 'BRUNO GABRIEL BRITES BACCI'),
(11065, 'ESCOLA - Serra dos Dourados', 'tarde', '1021439556', 'RAFAEL MARCOS GONÇALVES'),
(11502, 'ESCOLA - Vinicius de Morais', 'manha', '1024783738', 'LIVIA VALENTINA BARROS ANDRADE'),
(11503, 'ESCOLA - Vinicius de Morais', 'manha', '1019612593', 'NICOLLAS FELIPE MARQUES'),
(11504, 'ESCOLA - Vinicius de Morais', 'manha', '1020203486', 'ARTUR EZEQUIEL DOS SANTOS'),
(11505, 'ESCOLA - Vinicius de Morais', 'manha', '1025979539', 'ERICK GABRIEL DE OLIVEIRA VIEIRA'),
(11640, 'ESCOLA - Vinicius de Morais', 'tarde', '1020209336', 'DAVI LUCAS DOS SANTOS NASCIMENTO'),
(11641, 'ESCOLA - Vinicius de Morais', 'tarde', '1019710161', 'DAVI LUIZ FRANCISCO LIMA'),
(11642, 'ESCOLA - Vinicius de Morais', 'tarde', '1016112760', 'ADRYAN ZARAN DE SALES'),
(11643, 'ESCOLA - Vinicius de Morais', 'tarde', '1025805514', 'DAVI LUCCA PASSOS DE SOUZA'),
(11644, 'ESCOLA - Vinicius de Morais', 'tarde', '1017845663', 'NICOLAS RAFAEL PANIZA EVANGELISTA'),
(11645, 'ESCOLA - Vinicius de Morais', 'tarde', '1024182203', 'PIETRO DO CARMO BERNARDO'),
(11646, 'ESCOLA - Vinicius de Morais', 'tarde', '1025732037', 'ANANDA GOMES DE SA SZEZERBATZ'),
(11647, 'ESCOLA - Vinicius de Morais', 'tarde', '1017679259', 'ISADORA MOURA GUILHERME'),
(11648, 'ESCOLA - Vinicius de Morais', 'tarde', '1022196061', 'LIVIA DOS SANTOS CANEDO NICOLETTE'),
(11649, 'ESCOLA - Vinicius de Morais', 'tarde', '1017740675', 'LORENZO GABRIEL PEIXOTO FONSECA'),
(11650, 'ESCOLA - Vinicius de Morais', 'tarde', '1024214270', 'MARIA ELISA DE SOUZA SANTOS'),
(11651, 'ESCOLA - Vinicius de Morais', 'tarde', '1017679828', 'MIGUEL DOS SANTOS');

SET @srm_serie_matches := (
    SELECT COUNT(*)
    FROM series
    WHERE codigo = 'srm_serie'
       OR nome = 'Sala de Recursos Multifuncionais'
);

SET @srm_serie_id := (
    SELECT id
    FROM series
    WHERE codigo = 'srm_serie'
       OR nome = 'Sala de Recursos Multifuncionais'
    ORDER BY (codigo = 'srm_serie') DESC, id
    LIMIT 1
);

DROP TEMPORARY TABLE IF EXISTS tmp_srm_triagem;
CREATE TEMPORARY TABLE tmp_srm_triagem (
    linha_planilha int unsigned NOT NULL,
    escola_planilha varchar(255) NOT NULL,
    turno_srm varchar(16) NOT NULL,
    cgm varchar(255) NOT NULL,
    nome_planilha varchar(255) NOT NULL,
    escola_matches int unsigned NOT NULL,
    escola_id bigint unsigned NULL,
    principal_matches int unsigned NOT NULL,
    principal_id bigint unsigned NULL,
    principal_nome varchar(255) NULL,
    principal_turma_id bigint unsigned NULL,
    principal_escola_id bigint unsigned NULL,
    principal_escola varchar(255) NULL,
    principal_serie varchar(255) NULL,
    principal_turma varchar(255) NULL,
    principal_turno varchar(16) NULL,
    srm_turma_matches int unsigned NOT NULL,
    srm_turma_id bigint unsigned NULL,
    contra_matches int unsigned NOT NULL,
    contra_id bigint unsigned NULL,
    contra_turma_id bigint unsigned NULL,
    situacao varchar(64) NOT NULL,
    motivo varchar(255) NOT NULL,
    PRIMARY KEY (escola_planilha, turno_srm, cgm),
    KEY idx_tmp_srm_triagem_situacao (situacao)
) ENGINE=InnoDB;

INSERT INTO tmp_srm_triagem
SELECT
    base.linha_planilha,
    base.escola_planilha,
    base.turno_srm,
    base.cgm,
    base.nome_planilha,
    base.escola_matches,
    base.escola_id,
    base.principal_matches,
    base.principal_id,
    principal.nome,
    principal.id_turma,
    turma_principal.id_escola,
    escola_principal.nome,
    serie_principal.nome,
    turma_principal.nome,
    turma_principal.turno,
    COALESCE(srm.srm_turma_matches, 0),
    srm.srm_turma_id,
    COALESCE(contra.contra_matches, 0),
    contra.contra_id,
    contra.contra_turma_id,
    CASE
        WHEN @srm_serie_matches <> 1 THEN 'estrutura_invalida'
        WHEN base.escola_matches <> 1 THEN 'escola_nao_encontrada'
        WHEN base.principal_matches = 0 THEN 'aluno_nao_encontrado'
        WHEN base.principal_matches > 1 THEN 'principal_duplicado'
        WHEN turma_principal.id_escola <> base.escola_id THEN 'escola_principal_divergente'
        WHEN BINARY turma_principal.turno = BINARY base.turno_srm THEN 'mesmo_turno'
        WHEN turma_principal.turno NOT IN ('manha', 'tarde') THEN 'turno_principal_invalido'
        WHEN NOT (
            (turma_principal.turno = 'manha' AND base.turno_srm = 'tarde')
            OR (turma_principal.turno = 'tarde' AND base.turno_srm = 'manha')
        ) THEN 'turnos_nao_opostos'
        WHEN COALESCE(srm.srm_turma_matches, 0) = 0 THEN 'turma_srm_ausente'
        WHEN COALESCE(srm.srm_turma_matches, 0) > 1 THEN 'turma_srm_duplicada'
        WHEN COALESCE(contra.contra_matches, 0) > 1 THEN 'contra_turno_duplicado'
        WHEN COALESCE(contra.contra_matches, 0) = 1
         AND contra.contra_turma_id <> srm.srm_turma_id THEN 'contra_turno_em_outra_turma'
        WHEN COALESCE(contra.contra_matches, 0) = 1
         AND contra.contra_turma_id = srm.srm_turma_id THEN 'ja_cadastrado'
        ELSE 'apto'
    END,
    CASE
        WHEN @srm_serie_matches <> 1 THEN 'A serie SRM nao foi encontrada de forma unica.'
        WHEN base.escola_matches = 0 THEN 'A escola da planilha nao existe no sistema.'
        WHEN base.escola_matches > 1 THEN 'O nome da escola corresponde a mais de um cadastro.'
        WHEN base.principal_matches = 0 THEN 'Nao existe matricula principal ativa para este CGM.'
        WHEN base.principal_matches > 1 THEN 'Existe mais de uma matricula principal ativa para este CGM.'
        WHEN turma_principal.id_escola <> base.escola_id THEN 'A matricula principal esta em outra escola.'
        WHEN BINARY turma_principal.turno = BINARY base.turno_srm THEN 'A turma principal esta no mesmo turno da SRM.'
        WHEN turma_principal.turno NOT IN ('manha', 'tarde') THEN 'A turma principal nao esta em turno manha ou tarde.'
        WHEN NOT (
            (turma_principal.turno = 'manha' AND base.turno_srm = 'tarde')
            OR (turma_principal.turno = 'tarde' AND base.turno_srm = 'manha')
        ) THEN 'Os turnos principal e SRM nao sao opostos.'
        WHEN COALESCE(srm.srm_turma_matches, 0) = 0 THEN 'Nao existe a turma SRM esperada. Rode primeiro o script 01.'
        WHEN COALESCE(srm.srm_turma_matches, 0) > 1 THEN 'Existe mais de uma turma SRM para escola e turno.'
        WHEN COALESCE(contra.contra_matches, 0) > 1 THEN 'Existe mais de um contra-turno ativo para o CGM.'
        WHEN COALESCE(contra.contra_matches, 0) = 1
         AND contra.contra_turma_id <> srm.srm_turma_id THEN 'O aluno ja possui contra-turno ativo em outra turma.'
        WHEN COALESCE(contra.contra_matches, 0) = 1
         AND contra.contra_turma_id = srm.srm_turma_id THEN 'O aluno ja estava corretamente matriculado na SRM.'
        ELSE 'Aluno apto para matricula SRM em contra-turno.'
    END
FROM (
    SELECT
        p.linha_planilha,
        p.escola_nome AS escola_planilha,
        p.turno_srm,
        p.cgm,
        p.nome_planilha,
        COUNT(DISTINCT e.id) AS escola_matches,
        MIN(e.id) AS escola_id,
        COALESCE(pm.principal_matches, 0) AS principal_matches,
        pm.principal_id
    FROM tmp_srm_planilha p
    LEFT JOIN escolas e
      ON BINARY e.nome = BINARY p.escola_nome
    LEFT JOIN (
        SELECT cgm, COUNT(*) AS principal_matches, MIN(id) AS principal_id
        FROM alunos
        WHERE tipo_vinculo = 'principal'
          AND status = 'matriculado'
        GROUP BY cgm
    ) pm ON BINARY pm.cgm = BINARY p.cgm
    GROUP BY
        p.linha_planilha,
        p.escola_nome,
        p.turno_srm,
        p.cgm,
        p.nome_planilha,
        pm.principal_matches,
        pm.principal_id
) base
LEFT JOIN alunos principal ON principal.id = base.principal_id
LEFT JOIN turmas turma_principal ON turma_principal.id = principal.id_turma
LEFT JOIN escolas escola_principal ON escola_principal.id = turma_principal.id_escola
LEFT JOIN series serie_principal ON serie_principal.id = turma_principal.id_serie
LEFT JOIN (
    SELECT
        id_escola,
        turno,
        COUNT(*) AS srm_turma_matches,
        MIN(id) AS srm_turma_id
    FROM turmas
    WHERE id_serie = @srm_serie_id
      AND turno IN ('manha', 'tarde')
    GROUP BY id_escola, turno
) srm
  ON srm.id_escola = base.escola_id
 AND BINARY srm.turno = BINARY base.turno_srm
LEFT JOIN (
    SELECT
        cgm,
        COUNT(*) AS contra_matches,
        MIN(id) AS contra_id,
        MIN(id_turma) AS contra_turma_id
    FROM alunos
    WHERE tipo_vinculo = 'contra_turno'
      AND status = 'matriculado'
    GROUP BY cgm
) contra ON BINARY contra.cgm = BINARY base.cgm;

START TRANSACTION;

INSERT INTO alunos (
    id_turma, tipo_vinculo, permite_contra_turno, status,
    status_alterado_em, status_motivo, aluno_origem_id, turma_origem_id,
    movimentacao_origem, nome, cgm, cgm_matricula_ativa,
    cgm_contra_turno_ativo, cgm_unidade_matricula_ativa, sexo,
    data_matricula, data_nascimento, frequenta_srm, created_at, updated_at
)
SELECT
    triagem.srm_turma_id,
    'contra_turno',
    0,
    'matriculado',
    NOW(),
    'Matricula SRM em contra-turno importada da aba Matriculados em 12/08/2026.',
    principal.id,
    principal.id_turma,
    'contra_turno',
    principal.nome,
    principal.cgm,
    NULL,
    principal.cgm,
    NULL,
    principal.sexo,
    principal.data_matricula,
    principal.data_nascimento,
    1,
    NOW(),
    NOW()
FROM tmp_srm_triagem triagem
JOIN alunos principal ON principal.id = triagem.principal_id
WHERE triagem.situacao = 'apto'
  AND NOT EXISTS (
      SELECT 1
      FROM alunos existente
      WHERE BINARY existente.cgm = BINARY triagem.cgm
        AND existente.tipo_vinculo = 'contra_turno'
        AND existente.status = 'matriculado'
  );

UPDATE alunos principal
JOIN tmp_srm_triagem triagem ON triagem.principal_id = principal.id
SET principal.permite_contra_turno = 1,
    principal.frequenta_srm = 1,
    principal.updated_at = NOW()
WHERE triagem.situacao IN ('apto', 'ja_cadastrado');

COMMIT;

-- Tabela 1: CGMs sem matricula principal ativa.
SELECT
    escola_planilha AS `Escola`,
    cgm AS `CGM`,
    nome_planilha AS `Nome do Aluno`,
    CASE turno_srm WHEN 'manha' THEN 'A' ELSE 'B' END AS `SRM Turma`,
    CASE turno_srm WHEN 'manha' THEN 'Manha' ELSE 'Tarde' END AS `Turno SRM`,
    motivo AS `Motivo`
FROM tmp_srm_triagem
WHERE situacao = 'aluno_nao_encontrado'
ORDER BY escola_planilha, nome_planilha;

-- Tabela 2: conflitos que impedem a matricula automatica.
SELECT
    escola_planilha AS `Escola`,
    cgm AS `CGM`,
    COALESCE(principal_nome, nome_planilha) AS `Nome do Aluno`,
    CASE turno_srm WHEN 'manha' THEN 'A' ELSE 'B' END AS `SRM Turma`,
    CASE turno_srm WHEN 'manha' THEN 'Manha' ELSE 'Tarde' END AS `Turno SRM`,
    CASE WHEN principal_serie IS NULL THEN NULL
         ELSE CONCAT(principal_serie, ' - Turma ', principal_turma) END
        AS `Serie + Turma Padrao`,
    CASE principal_turno
        WHEN 'manha' THEN 'Manha'
        WHEN 'tarde' THEN 'Tarde'
        WHEN 'integral' THEN 'Integral'
        WHEN 'noite' THEN 'Noite'
        ELSE principal_turno
    END AS `Turno da Turma Padrao`,
    motivo AS `Motivo`
FROM tmp_srm_triagem
WHERE situacao NOT IN ('apto', 'ja_cadastrado', 'aluno_nao_encontrado')
ORDER BY escola_planilha, nome_planilha;

-- Tabela 3: alunos corretamente vinculados a SRM apos a execucao.
SELECT
    triagem.escola_planilha AS `Escola`,
    triagem.cgm AS `CGM`,
    principal.nome AS `Nome do Aluno`,
    CASE triagem.turno_srm WHEN 'manha' THEN 'A' ELSE 'B' END AS `SRM Turma`,
    CASE triagem.turno_srm WHEN 'manha' THEN 'Manha' ELSE 'Tarde' END AS `Turno SRM`,
    CONCAT(triagem.principal_serie, ' - Turma ', triagem.principal_turma)
        AS `Serie + Turma Padrao`,
    CASE triagem.principal_turno
        WHEN 'manha' THEN 'Manha'
        WHEN 'tarde' THEN 'Tarde'
        WHEN 'integral' THEN 'Integral'
        WHEN 'noite' THEN 'Noite'
        ELSE triagem.principal_turno
    END AS `Turno da Turma Padrao`
FROM tmp_srm_triagem triagem
JOIN alunos principal ON principal.id = triagem.principal_id
JOIN alunos contra
  ON BINARY contra.cgm = BINARY triagem.cgm
 AND contra.tipo_vinculo = 'contra_turno'
 AND contra.status = 'matriculado'
 AND contra.id_turma = triagem.srm_turma_id
WHERE triagem.situacao IN ('apto', 'ja_cadastrado')
ORDER BY triagem.escola_planilha, principal.nome;

DROP TEMPORARY TABLE IF EXISTS tmp_srm_triagem;
DROP TEMPORARY TABLE IF EXISTS tmp_srm_planilha;
