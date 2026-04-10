Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression.FileSystem

$docsRoot = $PSScriptRoot
$repoRoot = Split-Path -Parent $docsRoot
$outputDir = Join-Path $docsRoot 'documentation'

New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

function Escape-DocxText {
    param(
        [AllowNull()]
        [string]$Text
    )

    if ($null -eq $Text) {
        return ''
    }

    return [System.Security.SecurityElement]::Escape($Text)
}

function New-ParagraphXml {
    param(
        [AllowEmptyString()]
        [string]$Text,
        [ValidateSet('title', 'h1', 'h2', 'h3', 'p', 'bullet')]
        [string]$Type = 'p'
    )

    if ([string]::IsNullOrWhiteSpace($Text)) {
        return '<w:p/>'
    }

    $safeText = Escape-DocxText $Text

    switch ($Type) {
        'title' {
            $pPr = '<w:pPr><w:jc w:val="center"/><w:spacing w:after="260"/></w:pPr>'
            $rPr = '<w:rPr><w:b/><w:color w:val="17365D"/><w:sz w:val="34"/><w:szCs w:val="34"/></w:rPr>'
        }
        'h1' {
            $pPr = '<w:pPr><w:spacing w:before="240" w:after="120"/></w:pPr>'
            $rPr = '<w:rPr><w:b/><w:color w:val="1F497D"/><w:sz w:val="28"/><w:szCs w:val="28"/></w:rPr>'
        }
        'h2' {
            $pPr = '<w:pPr><w:spacing w:before="180" w:after="100"/></w:pPr>'
            $rPr = '<w:rPr><w:b/><w:color w:val="2F5597"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr>'
        }
        'h3' {
            $pPr = '<w:pPr><w:spacing w:before="140" w:after="80"/></w:pPr>'
            $rPr = '<w:rPr><w:b/><w:color w:val="44546A"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>'
        }
        'bullet' {
            $pPr = '<w:pPr><w:ind w:left="720" w:hanging="360"/><w:spacing w:after="80"/></w:pPr>'
            $rPr = '<w:rPr><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>'
            $safeText = Escape-DocxText ("- " + $Text)
        }
        default {
            $pPr = '<w:pPr><w:spacing w:after="90" w:line="300" w:lineRule="auto"/></w:pPr>'
            $rPr = '<w:rPr><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>'
        }
    }

    return "<w:p>$pPr<w:r>$rPr<w:t xml:space=`"preserve`">$safeText</w:t></w:r></w:p>"
}

function Convert-ContentToWordXml {
    param(
        [string]$Content
    )

    $lines = $Content -split "`r?`n"
    $xml = New-Object System.Collections.Generic.List[string]

    foreach ($line in $lines) {
        $trimmed = $line.TrimEnd()

        if ([string]::IsNullOrWhiteSpace($trimmed)) {
            $xml.Add((New-ParagraphXml -Text '' -Type 'p'))
            continue
        }

        if ($trimmed.StartsWith('### ')) {
            $xml.Add((New-ParagraphXml -Text $trimmed.Substring(4) -Type 'h3'))
            continue
        }

        if ($trimmed.StartsWith('## ')) {
            $xml.Add((New-ParagraphXml -Text $trimmed.Substring(3) -Type 'h2'))
            continue
        }

        if ($trimmed.StartsWith('# ')) {
            $xml.Add((New-ParagraphXml -Text $trimmed.Substring(2) -Type 'h1'))
            continue
        }

        if ($trimmed.StartsWith('- ')) {
            $xml.Add((New-ParagraphXml -Text $trimmed.Substring(2) -Type 'bullet'))
            continue
        }

        $xml.Add((New-ParagraphXml -Text $trimmed -Type 'p'))
    }

    return ($xml -join '')
}

function New-DocxPackage {
    param(
        [string]$Path,
        [string]$Title,
        [string]$Subject,
        [string]$BodyXml
    )

    $nowUtc = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
    $creator = 'Codex'
    $escapedTitle = Escape-DocxText $Title
    $escapedSubject = Escape-DocxText $Subject
    $escapedCreator = Escape-DocxText $creator

    $contentTypes = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
"@

    $rootRels = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
"@

    $coreXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>$escapedTitle</dc:title>
  <dc:subject>$escapedSubject</dc:subject>
  <dc:creator>$escapedCreator</dc:creator>
  <cp:lastModifiedBy>$escapedCreator</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">$nowUtc</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">$nowUtc</dcterms:modified>
</cp:coreProperties>
"@

    $appXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Microsoft Office Word</Application>
  <DocSecurity>0</DocSecurity>
  <ScaleCrop>false</ScaleCrop>
  <HeadingPairs>
    <vt:vector size="2" baseType="variant">
      <vt:variant><vt:lpstr>Title</vt:lpstr></vt:variant>
      <vt:variant><vt:i4>1</vt:i4></vt:variant>
    </vt:vector>
  </HeadingPairs>
  <TitlesOfParts>
    <vt:vector size="1" baseType="lpstr">
      <vt:lpstr>$escapedTitle</vt:lpstr>
    </vt:vector>
  </TitlesOfParts>
  <Company></Company>
  <LinksUpToDate>false</LinksUpToDate>
  <SharedDoc>false</SharedDoc>
  <HyperlinksChanged>false</HyperlinksChanged>
  <AppVersion>16.0000</AppVersion>
</Properties>
"@

    $documentXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    $BodyXml
    <w:sectPr>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/>
    </w:sectPr>
  </w:body>
</w:document>
"@

    $tempDir = Join-Path ([System.IO.Path]::GetTempPath()) ("gestao-edu-docx-" + [System.Guid]::NewGuid().ToString('N'))
    $zipPath = [System.IO.Path]::ChangeExtension($Path, '.zip')

    try {
        New-Item -ItemType Directory -Force -Path $tempDir | Out-Null
        New-Item -ItemType Directory -Force -Path (Join-Path $tempDir '_rels') | Out-Null
        New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'docProps') | Out-Null
        New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'word') | Out-Null

        Set-Content -LiteralPath (Join-Path $tempDir '[Content_Types].xml') -Value $contentTypes -Encoding UTF8
        Set-Content -LiteralPath (Join-Path $tempDir '_rels\.rels') -Value $rootRels -Encoding UTF8
        Set-Content -LiteralPath (Join-Path $tempDir 'docProps\core.xml') -Value $coreXml -Encoding UTF8
        Set-Content -LiteralPath (Join-Path $tempDir 'docProps\app.xml') -Value $appXml -Encoding UTF8
        Set-Content -LiteralPath (Join-Path $tempDir 'word\document.xml') -Value $documentXml -Encoding UTF8

        if (Test-Path -LiteralPath $zipPath) {
            Remove-Item -LiteralPath $zipPath -Force
        }

        if (Test-Path -LiteralPath $Path) {
            Remove-Item -LiteralPath $Path -Force
        }

        [System.IO.Compression.ZipFile]::CreateFromDirectory($tempDir, $zipPath)
        Move-Item -LiteralPath $zipPath -Destination $Path -Force
    }
    finally {
        if (Test-Path -LiteralPath $tempDir) {
            Remove-Item -LiteralPath $tempDir -Recurse -Force
        }
    }
}

$generatedAt = Get-Date -Format 'dd/MM/yyyy HH:mm'
$documents = @(
    @{
        FileName = '01-arquitetura-do-software.docx'
        Title = 'Arquitetura do Software'
        Subject = 'Arquitetura geral, camadas e responsabilidades do Gestao-Edu'
        Content = @'
# Objetivo do documento
Este documento descreve a arquitetura funcional e técnica do Gestao-Edu, explicando como o sistema está organizado e onde as regras de negócio realmente vivem.

# Visão geral do sistema
O Gestao-Edu é um monólito Laravel 12 com painel administrativo Filament 5.
A aplicação concentra vários domínios de negócio no mesmo painel: acesso e permissões, estrutura escolar, pedagógico, manutenção predial, alimentação escolar, estoque, inventário, relatórios e notificações.
O fluxo principal de uso acontece no painel /admin, com autenticação baseada no guard web e sessão padrão do Laravel.

# Stack utilizada
- Backend em PHP 8.4 com Laravel 12.
- Painel administrativo construído com Filament 5.
- Autenticação social com Laravel Socialite e integração Google.
- Controle de papéis e permissões com Spatie Laravel Permission.
- Exportações e relatórios com DomPDF e PhpSpreadsheet.
- Integrações Google apoiadas por google/apiclient.
- Ambiente local baseado em Docker, MySQL, phpMyAdmin e container dedicado ao scheduler.

# Estrutura principal do código
- bootstrap/app.php centraliza bootstrap da aplicação e agendamento.
- routes/web.php reúne rotas públicas, OAuth, downloads protegidos e exportações.
- app/Providers/Filament/AdminPanelProvider.php configura o painel admin, middleware, identidade visual e tela de login.
- app/Providers/AppServiceProvider.php registra policies, observers, gates e integrações transversais do painel.
- app/Filament/Admin concentra recursos, páginas, clusters, widgets, tabelas e schemas da interface administrativa.
- app/Services concentra casos de uso e regras operacionais relevantes.
- app/Policies protege recursos e downloads sensíveis.
- app/Models guarda relacionamentos, estados derivados e partes da regra.
- app/Observers executa efeitos colaterais, como notificações ligadas a mudanças de status.

# Onde a regra de negócio vive
No Gestao-Edu, a regra de negócio não está em uma única camada.
- Services concentram fluxos críticos, como autenticação Google, pedidos de manutenção, inventário escolar e controle operacional de estoque.
- Policies respondem pela capacidade ampla de acesso, como listar, criar, editar, excluir ou baixar arquivos.
- Models mantêm relacionamentos, estados calculados e gatilhos de consistência.
- Observers e notificações complementam eventos que precisam ocorrer depois de alterações importantes.
- Schemas, tables e actions do Filament também carregam regra de negócio, especialmente em validações, botões operacionais, transições de status e visibilidade de campos.

# Arquitetura de acesso e contexto
O acesso real ao sistema é composto por múltiplas camadas.
- O usuário precisa conseguir autenticar.
- Depois disso, ainda precisa passar pelo gate final do painel, que exige email aprovado.
- Em seguida, o sistema avalia papéis, permissões e policies.
- Por fim, vários módulos ainda aplicam escopo contextual por escola e por setor.
Isso significa que esconder um botão não é suficiente. O sistema precisa proteger interface, query, policy e rota quando o recurso sai do Filament.

# Modelo de contexto operacional
Os principais contextos usados pelo software são:
- Contexto global, normalmente associado a administradores ou gestores centrais sem escola vinculada.
- Contexto escolar, quando o usuário possui id_escola e só deve atuar sobre registros da própria unidade.
- Contexto setorial, usado principalmente na manutenção, quando o usuário possui setor_id e só gerencia pedidos do setor correspondente.
- Contexto de professor, usado em consultas pedagógicas que limitam turmas e alunos aos vínculos do docente.

# Persistência, arquivos e relatórios
O banco principal é MySQL.
Arquivos funcionais, como laudos e anexos de pedidos, são armazenados via storage da aplicação e expostos por rotas protegidas.
Relatórios e exportações são entregues por controllers e services dedicados, em especial PDF e XLSX.
Notificações são persistidas em banco e exibidas dentro do painel.

# Infraestrutura local e execução
- O serviço app executa a aplicação principal.
- O serviço db executa MySQL 8.
- O serviço phpmyadmin apoia a administração de banco em ambiente local.
- O serviço cron roda php artisan schedule:run em loop para processar tarefas agendadas.
Esse desenho reforça que notificações programadas e verificações periódicas não dependem exclusivamente de acesso humano ao painel.

# Regras arquiteturais importantes
- O sistema depende fortemente de nomes seedados para permissões, roles, setores e status.
- Alterar nomes de status ou permissões sem revisar código, seeds e UI pode quebrar comportamento.
- Em vários módulos, a regra real está dividida entre service, model, observer e action do Filament.
- Policies sozinhas não representam todo o escopo de visibilidade.
- Recursos com download fora do painel precisam de middleware explícito.

# O que pode e não pode ser assumido
- Pode ser assumido que o projeto segue o padrão de monólito administrativo orientado a painel.
- Pode ser assumido que boa parte das regras foi implementada próxima da interface.
- Não pode ser assumido que controller sozinho contém o fluxo do módulo.
- Não pode ser assumido que uma policy resolve todo o problema de escopo.
- Não pode ser assumido que um papel, por si só, descreve todas as permissões efetivas do usuário.

# Riscos arquiteturais observados
- Cobertura de testes ainda é baixa em fluxos sensíveis.
- Há dependência forte de strings literais.
- O projeto mistura responsabilidades técnicas e operacionais em diferentes pontos.
- Mudanças em módulos com saldo, status ou permissão pedem validação manual cuidadosa.

# Conclusão
A arquitetura do Gestao-Edu é adequada para um painel administrativo rico e com muitos domínios, mas exige manutenção consciente.
Sempre que uma funcionalidade for alterada, a análise correta precisa considerar ao mesmo tempo código de domínio, policy, filtros de query, rotas protegidas e ações do Filament.
'@
    },
    @{
        FileName = '02-login-usuarios-e-permissoes.docx'
        Title = 'Login, Usuários e Permissões'
        Subject = 'Autenticação, onboarding, gestão de usuários, papéis e permissões'
        Content = @'
# Objetivo do documento
Este documento descreve como o usuário entra no sistema, quando o acesso é liberado e como o controle de usuários, papéis e permissões funciona no Gestao-Edu.

# Escopo funcional
O domínio cobre:
- login por email e senha;
- login com Google;
- onboarding de novos usuários;
- aprovação de acesso;
- gestão de usuários;
- gestão de roles e permissões;
- filtros de escopo por escola e setor.

# Login por email e senha
O painel usa uma tela de login customizada no Filament.
O usuário informa email e senha válidos, mas isso não basta para entrar no painel.
A liberação final depende do método canAccessPanel do usuário.
Se o email ainda não estiver aprovado, o sistema encerra a sessão, mostra notificação de pendência e redireciona de volta para o login.

# Regra principal do acesso ao painel
O atributo email_approved é o gate final do acesso administrativo.
- Usuário autenticado e aprovado pode entrar.
- Usuário autenticado, mas não aprovado, não pode permanecer no painel.
- Aprovação de email também preenche email_verified_at quando necessário.
Na prática, isso separa autenticação de autorização operacional.

# Login com Google
O login social é feito com Laravel Socialite e callback dedicada.
O fluxo consulta o Google para identidade, email e perfil, além de escopos ligados a metadata do Drive e leitura de Sheets.
Depois do retorno do Google, o sistema tenta encontrar usuário por email principal ou por google_email já vinculado.

# Regras de onboarding com Google
- Se o usuário já existir e estiver aprovado, os tokens são atualizados e o acesso é liberado.
- Se o usuário autenticado no sistema ainda não tiver vínculo Google, a conta atual pode ser vinculada ao OAuth.
- Se o usuário não existir, o sistema valida primeiro o domínio do email.
- Apenas domínios ativos e autorizados podem gerar cadastro automático via Google.
- Mesmo quando o domínio é permitido, o usuário novo nasce com email_approved = false.
- O domínio permitido autoriza o cadastro automático, mas não substitui a aprovação administrativa.

# O que bloqueia o login com Google
- Email fora dos domínios autorizados.
- Usuário ainda sem aprovação de acesso.
- Qualquer tentativa de entrar no painel antes da aprovação final.
Isso evita que o OAuth vire uma porta de entrada irrestrita.

# Gestão de usuários
O sistema mantém usuários com vínculo opcional de escola e setor.
O cadastro também guarda código interno, dados de autenticação local e dados do Google OAuth.
Existe suporte a múltiplas roles por usuário e a permissões extras diretas.

# Regras de negócio na gestão de usuários
- O código do usuário é gerado automaticamente quando não informado.
- Usuário pode herdar permissões pelas roles e também receber permissões diretas adicionais.
- Permissões diretas não devem repetir permissões já herdadas da role.
- O vínculo com escola e setor define contexto operacional em vários módulos.

# Regras de visibilidade e ação na gestão de usuários
- Usuários não administradores não veem usuários com role Admin na listagem.
- Usuários não administradores não podem atribuir a role Admin.
- O campo de escola fica restrito ao contexto do usuário quando ele já pertence a uma escola.
- Em muitos cenários, usuário contextualizado por escola não consegue trocar livremente o vínculo escolar do cadastro.
- Não é permitido excluir o próprio usuário.
- Não é permitido excluir o usuário base protegido.
- O toggle de aprovação de email é reservado ao administrador e não deve ser usado no próprio registro do admin.

# Gestão de roles e permissões
O projeto usa Spatie Laravel Permission como base da autorização.
As permissões seguem um vocabulário por verbo e domínio, como Listar, Criar, Editar, Excluir, Exportar e Visualizar.
O comando CriarPermissoes mantém o catálogo central usado pelo sistema.

# Papéis principais identificados no sistema
- Admin
- Secretário
- Administrativo
- Gestão de Usuários e Acessos
- Gestão Pedagógica
- Gestão de Pedidos
- Gestão de Merenda
- Gestão de Inventário
- Gestão de Estoque
- Gestão de Cadastros Gerais
- Relatórios e Painel
Esses papéis funcionam como agrupamentos de permissões, mas a autorização final depende da combinação entre role, permissões diretas e contexto.

# Regras de proteção de roles
- A role Admin é tratada como papel sensível.
- Roles estruturais como Admin, Secretário e Administrativo possuem bloqueios de edição e exclusão em cenários críticos.
- Isso impede que perfis-base do sistema sejam alterados casualmente pela interface.

# Camadas de autorização
O controle de acesso não depende de um único mecanismo.
- Roles e permissões definem capacidade ampla.
- Policies protegem recursos específicos.
- Queries e services aplicam escopo real por escola ou setor.
- Rotas fora do Filament usam middleware can quando necessário.
Esse desenho evita vazamento de acesso em arquivos, downloads e páginas operacionais.

# O que cada contexto pode ou não pode ver
Administrador ou gestor global:
- pode enxergar o painel completo, inclusive gestão sensível de acesso;
- pode aprovar usuários pendentes;
- pode atuar sobre perfis e permissões com alcance amplo.

Usuário comum sem privilégio administrativo:
- pode acessar apenas o que suas permissões liberam;
- não pode usar a tela de usuários como se tivesse alcance global;
- não pode enxergar ou manipular perfis administrativos protegidos.

Usuário contextualizado por escola:
- atua dentro do próprio escopo escolar em vários módulos;
- não deve ganhar visibilidade ampla apenas por estar autenticado;
- pode ter campos travados para evitar troca indevida de escola.

Usuário contextualizado por setor:
- só gerencia operações do próprio setor quando o fluxo assim exigir;
- não deve agir sobre demandas de outros setores sem permissão ou contexto adequado.

# O que pode e não pode ser feito
- Pode autenticar por email e senha, desde que a conta exista e esteja válida.
- Pode autenticar por Google, desde que o domínio seja permitido e o acesso esteja aprovado.
- Pode cadastrar usuário via Google com domínio autorizado.
- Não pode entrar no painel sem aprovação administrativa.
- Não pode confiar apenas no papel para ignorar filtros de escola e setor.
- Não pode renomear ou excluir papéis protegidos sem respeitar as regras do sistema.

# Conclusão
No Gestao-Edu, autenticar não significa automaticamente ter acesso operacional.
O software separa com clareza identidade, aprovação e autorização contextual.
Qualquer evolução futura em login, usuários ou permissões deve preservar essa sequência: autenticar, aprovar, autorizar e filtrar pelo contexto correto.
'@
    },
    @{
        FileName = '03-modulo-pedagogico.docx'
        Title = 'Módulo Pedagógico'
        Subject = 'Estrutura escolar, professores, turmas, alunos, laudos e regras pedagógicas'
        Content = @'
# Objetivo do documento
Este documento consolida o domínio pedagógico do Gestao-Edu, com foco em estrutura escolar, turmas, professores, alunos, laudos, retenções e informações de acompanhamento.

# Escopo do módulo
O módulo pedagógico cobre principalmente:
- escolas;
- séries;
- turmas;
- componentes curriculares;
- professores;
- alunos;
- retenções;
- laudos e anexos;
- informações de CAEI e acompanhamento educacional;
- relatórios pedagógicos.

# Modelo funcional do domínio
O sistema organiza o pedagógico a partir da relação entre escola, série, turma, professor e aluno.
As turmas pertencem a uma escola e a uma série.
Os professores podem ser vinculados a componentes curriculares por turma.
Os alunos pertencem a uma turma e podem ter profissional de apoio, retenções, laudos e informações complementares.

# Regras de contexto e visibilidade
O módulo não é inteiramente aberto para todos os usuários.
- Administradores e perfis centrais conseguem operar com visão mais ampla da rede.
- Usuários com id_escola trabalham, em regra, sobre registros da própria escola.
- Usuários com perfil de professor podem ter visualização limitada às turmas e aos alunos ligados aos seus componentes.
- Filtros adicionais por escola, série e componente aparecem somente quando a permissão correspondente existe.

# Escolas
Escola é cadastro estrutural e impacta outros módulos.
As regras principais observadas são:
- o código da escola é protegido por permissão específica;
- ao editar uma escola existente, o sistema pode versionar o registro, desativando o atual e criando uma nova versão com o mesmo código;
- a escola não pode ser excluída quando possui vínculos com usuários, turmas ou professores.
Isso mostra que a escola é tratada como entidade histórica e de referência, não como dado descartável.

# Turmas
Turma depende diretamente de escola e série.
As regras principais são:
- a turma herda sua composição curricular a partir da série selecionada;
- a associação de professor por componente é feita dentro da própria turma;
- os professores elegíveis para um componente pertencem à mesma escola;
- professores com função administrativa ficam fora da seleção operacional por componente;
- uma turma com alunos vinculados não pode ser excluída;
- a exclusão em massa também deve respeitar a existência de alunos vinculados.

# Professores
Professor é cadastro pedagógico e operacional.
As regras observadas são:
- o professor pode ser forçado ao contexto escolar do usuário autenticado;
- a tela possui filtros por escola, série e componente, mas cada filtro depende de permissão específica;
- detalhes operacionais do professor, como turmas e componentes atendidos, dependem de permissão de visualização;
- excluir professor exige permissão correspondente;
- exclusão em massa exige permissão própria.

# Alunos
Aluno é o centro do acompanhamento pedagógico individual.
O formulário mostra várias regras de negócio relevantes:
- o preenchimento é guiado pelo CGM, que funciona como referência para habilitar o restante do cadastro;
- a escola do aluno segue o contexto permitido ao usuário;
- a lista de turmas depende da escola selecionada;
- o turno da turma é derivado da turma escolhida;
- o profissional de apoio só pode ser selecionado quando o aluno exige esse vínculo;
- o profissional de apoio precisa pertencer à mesma escola, atuar no turno da turma e estar marcado como profissional de apoio.

# Retenções
O sistema trata retenção como informação estruturada, não apenas como texto livre.
As regras principais são:
- o usuário precisa informar primeiro se o aluno já foi retido;
- se a resposta for Não, os dados de retenção não devem ser mantidos;
- se a resposta for Sim, os campos de retenção passam a ser obrigatórios;
- o cadastro registra quantidade de retenções, série, ano e motivos.

# Informações de CAEI
O bloco de CAEI também é condicional.
- O usuário informa se o estudante foi encaminhado ao CAEI.
- Se não houve encaminhamento, os campos específicos ficam ocultos e não devem ser persistidos.
- Se houve encaminhamento, status de atendimento por profissional e avanço na aprendizagem passam a ser obrigatórios.
Isso evita registro inconsistente de acompanhamento especializado.

# Laudos e informações médicas
Laudos são tratados como conteúdo sensível.
As regras principais são:
- a seção de laudos no formulário só aparece para quem pode anexar laudos;
- visualizar laudo exige permissão de visualização;
- baixar laudo exige permissão de exportação;
- anexar laudo exige permissão específica;
- excluir laudo exige permissão específica;
- usuários com contexto escolar só podem ver e baixar laudos de alunos da própria escola.
Esse bloco combina permissão funcional com restrição contextual de escola.

# O que pode e não pode ser visto
Administrador ou gestor global:
- pode navegar com visão ampla sobre escolas, turmas, professores e alunos, conforme suas permissões.

Usuário de escola:
- vê prioritariamente os registros da própria escola;
- não deve enxergar turmas, alunos, professores ou laudos de outra unidade quando o módulo aplica o escopo escolar.

Professor contextualizado:
- vê turmas e alunos ligados aos próprios vínculos pedagógicos;
- não deve receber visão completa da rede apenas por possuir acesso ao painel.

Usuário sem permissão de laudo:
- não deve ver a parte médica sensível;
- não deve baixar arquivos de laudo.

# O que pode e não pode ser feito
- Pode cadastrar e editar dados pedagógicos dentro do contexto liberado.
- Pode associar professor por componente dentro da turma, respeitando escola e elegibilidade.
- Pode registrar retenções e acompanhamento especializado quando os gatilhos do formulário forem atendidos.
- Não pode excluir turma com alunos vinculados.
- Não pode excluir escola com vínculos ativos em usuários, turmas ou professores.
- Não pode acessar ou exportar laudos sem a combinação correta de permissão e contexto.

# Relatórios pedagógicos
O sistema possui relatórios e dashboards pedagógicos protegidos por permissões específicas.
Além da permissão de listagem do relatório, exportações exigem permissão de exportar relatórios.
Isso mantém separado o direito de consultar o painel do direito de extrair informação consolidada.

# Conclusão
O módulo pedagógico do Gestao-Edu é fortemente contextual.
A principal regra de negócio não é apenas cadastrar dados educacionais, mas garantir que cada perfil veja e altere apenas o conjunto pedagógico que lhe pertence.
'@
    },
    @{
        FileName = '04-modulo-manutencao.docx'
        Title = 'Módulo de Manutenção'
        Subject = 'Pedidos de manutenção, ciclo de vida, histórico, anexos e avaliação'
        Content = @'
# Objetivo do documento
Este documento descreve o fluxo de manutenção predial do Gestao-Edu, com foco em pedidos, protocolos, histórico, anexos, avaliações, notificações e restrições por perfil.

# Escopo do módulo
O módulo de manutenção cobre:
- abertura de pedido;
- classificação do pedido;
- protocolo automático;
- histórico de mudanças;
- anexos do problema e da conclusão;
- mudança de status;
- gestão por setor;
- avaliação do serviço executado;
- reabertura e conclusão;
- relatórios e feedback.

# Modelo funcional do pedido
Pedido é a entidade central do módulo.
Cada pedido pertence a uma escola, possui um solicitante, um setor atual, um tipo de manutenção e um status operacional.
Além disso, o pedido mantém histórico, arquivos e feedbacks de avaliação.

# Regras de criação
Ao criar um pedido, o sistema aplica regras automáticas:
- gera número de protocolo com base no ano e no maior protocolo já existente;
- define status inicial como Em Aberto;
- define setor inicial como Educação;
- vincula o pedido à escola do solicitante;
- grava anexos iniciais como fotos do problema;
- registra histórico da criação.
Isso garante que o fluxo sempre comece de forma padronizada.

# Ciclo de vida do pedido
O fluxo operacional identificado usa status nomeados pelo negócio, como:
- Em Aberto;
- Em Análise;
- Encaminhado ao Setor;
- Em Manutenção;
- Concluído;
- Reaberto;
- Cancelado.
Esses nomes não são apenas decorativos. O sistema depende deles para ações, filtros, notificações e transições.

# Regras de gerenciamento
O botão operacional de gerenciar pedido possui restrições claras:
- exige permissão Editar Pedidos;
- não aparece para pedidos finalizados ou cancelados;
- se o usuário não tiver setor, pode gerenciar de forma ampla;
- se o usuário tiver setor_id, só pode gerenciar pedidos do mesmo setor.
Além disso, assumir o pedido altera o fluxo:
- pedido Em Aberto pode ir para Em Análise;
- pedido Encaminhado ao Setor só vai para Em Análise por essa ação quando o usuário pertence ao setor Obras.

# Regras de mudança de status
Mudanças de status não são neutras.
- toda alteração relevante registra histórico;
- o responsável pela alteração é associado ao pedido;
- status finalizador preenche data de entrega;
- algumas transições dependem de nomes exatos de status e de setor.
No fluxo de gestão, encaminhar ao setor depende do setor correto e não deve ser tratado como mudança genérica.

# Anexos e rastreabilidade
Arquivos fazem parte do processo funcional.
- fotos do problema são anexadas na abertura;
- fotos da conclusão podem ser anexadas na avaliação;
- download de arquivo exige autorização apropriada;
- alterações em anexos também deixam trilha de histórico.
No módulo de manutenção, histórico e arquivos não são acessórios, mas parte do próprio processo de comprovação.

# Avaliação do pedido
O encerramento funcional do pedido inclui avaliação.
As regras observadas são:
- a ação de avaliar aparece quando o pedido está em Em Manutenção e o usuário possui permissão de avaliação;
- a avaliação cria um registro de feedback;
- nota 1 reabre automaticamente o pedido;
- notas diferentes de 1 concluem o pedido;
- a avaliação pode incluir fotos de conclusão.
Isso transforma a avaliação em mecanismo real de aceite ou rejeição do serviço.

# Regras de visibilidade
O escopo de visualização depende do perfil do usuário.
- Admin e quem possui permissão de listar todos os pedidos podem enxergar a base ampla.
- Usuário com escola vinculada enxerga apenas pedidos da própria escola quando não possui alcance global.
- Usuário sem contexto compatível não deve receber listagem operacional.
- Alguns agrupamentos por status só aparecem para quem possui permissão de visualizar pedidos por status.
- A aba padrão do painel muda conforme o setor, o que reforça o caráter operacional do módulo.

# Regras de ação por contexto
Gestor central ou admin:
- pode acompanhar o fluxo geral;
- pode atuar sobre pedidos de múltiplas escolas, conforme permissões.

Usuário escolar:
- abre e acompanha pedidos da própria escola;
- não deve visualizar pedidos de outras unidades sem permissão global.

Usuário setorial:
- gerencia pedidos compatíveis com seu setor;
- não deve assumir demandas fora do setor quando o fluxo exige compatibilidade setorial.

Avaliador:
- só atua na fase correta do ciclo;
- não deve encerrar avaliação sem respeitar o impacto no status final do pedido.

# O que pode e não pode ser feito
- Pode abrir pedido com descrição e evidências do problema.
- Pode movimentar o pedido conforme permissões, status e setor.
- Pode anexar evidências de execução e conclusão.
- Pode avaliar o serviço quando o pedido estiver em etapa apropriada.
- Não pode gerenciar pedido cancelado ou finalizado.
- Não pode tratar qualquer usuário como gestor universal do fluxo setorial.
- Não pode ignorar histórico ao mudar status.
- Não pode considerar nota 1 como mera informação, porque ela reabre o pedido automaticamente.

# Notificações e efeitos colaterais
O módulo dispara notificações em situações relevantes.
- prioridade emergencial gera alerta;
- mudança de status gera comunicação ao solicitante;
- pedidos reabertos precisam ser tratados como retorno efetivo do fluxo.
Esses efeitos mostram que observer e fluxo operacional caminham juntos.

# Riscos de negócio do módulo
- Dependência forte de nomes seedados de status e setores.
- Regra distribuída entre service, observer, actions da UI e policies.
- Possibilidade de regressão se apenas um ponto for alterado.
- Necessidade de preservar coerência entre pedido, histórico, anexos e feedback.

# Conclusão
O módulo de manutenção foi desenhado para controlar demanda, execução, evidência e aceite.
A principal regra de negócio é garantir rastreabilidade ponta a ponta, preservando quem pediu, quem tratou, em que setor a demanda está e se o resultado foi realmente aceito pela escola.
'@
    },
    @{
        FileName = '05-alimentacao-escolar.docx'
        Title = 'Alimentação Escolar, Estoque e Inventário'
        Subject = 'Contratos, pedidos de merenda, estoque da matriz, inventário escolar e pedidos internos'
        Content = @'
# Objetivo do documento
Este documento descreve o domínio de alimentação escolar do Gestao-Edu, cobrindo contratos, pedidos de merenda, saldo contratual, estoque da matriz, balanços, inventários escolares e pedidos internos entre escola e matriz.

# Escopo do módulo
O domínio cobre:
- contratos e itens contratados;
- aditivos e reequilíbrios;
- gestão de margens;
- pedidos de merenda;
- entregas parciais e totais;
- estoque central da matriz;
- baixas operacionais;
- balanço de estoque;
- inventário escolar por unidade;
- pedidos internos da escola para a matriz;
- romaneio e conferência de recebimento.

# Contratos e itens contratados
O contrato é a base legal e quantitativa da alimentação escolar.
Cada item contratado mantém, no mínimo, três quantidades relevantes:
- quantidade total;
- quantidade utilizada;
- quantidade reservada.
O saldo disponível do item é calculado por uma regra central:
saldo disponível = quantidade total - quantidade utilizada - quantidade reservada.

# Regras de negócio dos contratos
- Um item não pode ser consumido além do saldo disponível.
- A reserva do item também consome capacidade operacional, mesmo antes da entrega física.
- Aditivo atua sobre item já existente no contrato.
- O preço do aditivo reaproveita o preço do item original de compra.
- A gestão de margens sinaliza criticidade quando a disponibilidade chega próxima do limite contratual.
Isso mostra que o sistema separa claramente contratar, reservar, utilizar e entregar.

# Pedidos de merenda
Pedido de merenda é o instrumento operacional de consumo do contrato.
Na criação do pedido:
- o usuário escolhe apenas itens ativos e com saldo contratual disponível;
- informa a quantidade por item;
- o sistema valida que o pedido não excede o saldo disponível do contrato;
- a confirmação gera o pedido e seus itens;
- a quantidade solicitada passa a reservar saldo do contrato.

# Regras de entrega do pedido de merenda
Cada item do pedido controla quantidade pedida e quantidade entregue.
As regras principais são:
- quantidade pedida não pode ser alterada livremente depois da criação;
- quantidade entregue não pode ultrapassar a quantidade pedida;
- entrega parcial é suportada como etapa funcional do fluxo;
- ao entregar, o sistema move saldo do contrato de reservado para utilizado;
- a entrega também gera entrada no estoque central da matriz.

# Cancelamento do pedido de merenda
O cancelamento não apaga o que já ocorreu.
- Apenas a parte ainda pendente retorna da reserva para a disponibilidade contratual.
- O que já foi entregue permanece consumido e refletido no estoque.
Isso impede que o cancelamento distorça histórico ou recomponha saldo de forma indevida.

# Estoque central da matriz
O estoque da matriz representa o saldo físico central por item.
Cada registro de estoque mantém quantidade total e quantidade reservada.
O saldo disponível do estoque central considera a diferença entre o físico existente e o que já está reservado para outra operação.

# Regras do estoque central
- entrada aumenta o estoque físico;
- saída reduz o estoque físico;
- baixa operacional exige validação contra saldo disponível;
- reserva bloqueia parte do saldo para evitar dupla utilização;
- confirmação de entrega reservada consome a reserva e efetiva a saída correspondente.
Hoje o estoque central funciona como base logística da rede e não está naturalmente segmentado por escola.

# Gestão de estoque e balanço
O módulo de gestão de estoque oferece monitoramento e ajustes operacionais.
As regras mais importantes são:
- itens podem ser classificados como críticos quando o saldo está abaixo do limite esperado;
- itens podem ficar bloqueados durante balanço ativo;
- o balanço cria fotografia dos itens, controla contagem e aplica ajuste ao concluir;
- ajustes finais entram como movimentação formal, preservando rastreabilidade e impacto financeiro.
Isso significa que o balanço não é uma consulta: ele altera o estoque real quando concluído.

# Contexto do inventário escolar
O sistema também possui inventário por escola.
Nesse domínio, existem dois grandes contextos:
- gestor geral, normalmente admin ou usuário sem escola vinculada;
- usuário escolar, vinculado a uma escola específica.
As regras principais são:
- gestor geral pode ver todos os inventários;
- usuário escolar vê apenas o inventário da própria escola;
- apenas o gestor geral cria inventários;
- existe um inventário ativo por escola.

# Pedidos internos da escola para a matriz
O pedido interno serve para abastecimento da escola a partir do estoque central.
As regras do fluxo são:
- o pedido é criado pela escola dentro do próprio inventário;
- o pedido nasce com status Pendente;
- a escola não pode abrir novo pedido se já existir pedido Em Andamento aguardando conferência;
- o pedido traz itens, quantidades solicitadas e observações da escola.

# Análise e aprovação do pedido interno
Somente o gestor geral pode analisar o pedido interno.
Na análise:
- cada item pode ser aprovado parcialmente ou recusado;
- a quantidade aprovada não pode exceder a quantidade solicitada;
- item com quantidade aprovada maior que zero passa a Aprovado;
- item com quantidade zero passa a Recusado;
- se nenhum item for aprovado, o pedido inteiro fica Recusado;
- se houver pelo menos um item aprovado, o pedido fica Aprovado.

# Romaneio
O romaneio é a etapa que transforma aprovação em separação logística.
As regras principais são:
- somente o gestor geral pode gerar romaneio;
- só entram pedidos aprovados;
- o romaneio soma quantidades aprovadas por item;
- o sistema valida se a matriz possui saldo disponível suficiente;
- ao gerar romaneio, o estoque central é reservado;
- os pedidos envolvidos mudam para Em Andamento.
Ou seja, a reserva do estoque da matriz acontece no romaneio, e não na simples solicitação da escola.

# Conferência de recebimento
A conferência fecha o ciclo entre matriz e escola.
As regras observadas são:
- somente pedido Em Andamento pode ser conferido;
- a conferência pode ser feita pelo gestor geral ou pela própria escola do pedido;
- para cada item aprovado, a escola informa a quantidade recebida;
- a matriz consome a reserva e registra a saída efetiva;
- o inventário escolar recebe entrada apenas do que foi efetivamente recebido;
- se houver divergência entre aprovado e recebido, a observação da conferência passa a ser obrigatória;
- ao final da conferência, o pedido vai para Entregue.

# O que pode e não pode ser visto
Gestor geral:
- pode enxergar contratos, margens, estoque central, inventários e pedidos internos da rede inteira, conforme permissões.

Usuário escolar:
- vê o próprio inventário e os próprios pedidos internos;
- não deve ver inventário de outra escola;
- não deve aprovar pedidos nem gerar romaneio, porque isso pertence ao contexto central.

Operação de estoque central:
- possui visão transversal da matriz;
- hoje não trabalha com escopo natural por escola no saldo da matriz.

# O que pode e não pode ser feito
- Pode reservar saldo contratual ao criar pedido de merenda.
- Pode entregar parcialmente um pedido de merenda.
- Pode cancelar somente a parte ainda não consumida do pedido.
- Pode registrar entrada, saída e baixa respeitando saldo disponível.
- Pode aprovar parcialmente pedido interno de escola.
- Pode gerar romaneio apenas quando houver saldo suficiente na matriz.
- Pode conferir recebimento com ajuste para divergência justificada.
- Não pode solicitar ou aprovar quantidades acima do permitido.
- Não pode criar novo pedido interno escolar enquanto houver pedido Em Andamento da mesma escola.
- Não pode consumir estoque central reservado como se fosse saldo livre.
- Não pode misturar baixa escolar com saída direta do estoque da matriz.

# Riscos de negócio do módulo
- Qualquer erro em reserva, utilização ou confirmação de entrega desbalanceia contrato e estoque.
- O domínio depende de validações distribuídas entre model, service e actions do painel.
- Como estoque da matriz e inventário escolar se conectam, mudanças parciais podem gerar inconsistência em cadeia.
- O módulo exige atenção especial em cancelamento, romaneio, conferência e balanço.

# Conclusão
O domínio de alimentação escolar do Gestao-Edu foi construído para preservar coerência entre contrato, pedido, entrega, estoque central e inventário da escola.
A principal regra de negócio é impedir que o sistema perca o controle entre o que foi contratado, o que foi reservado, o que foi entregue, o que ainda está disponível e o que cada escola realmente recebeu.
'@
    }
)

foreach ($document in $documents) {
    $bodyParts = New-Object System.Collections.Generic.List[string]
    $bodyParts.Add((New-ParagraphXml -Text $document.Title -Type 'title'))
    $bodyParts.Add((New-ParagraphXml -Text 'Sistema Gestao-Edu' -Type 'h3'))
    $bodyParts.Add((New-ParagraphXml -Text "Documento gerado em $generatedAt a partir do código-fonte e da documentação interna do projeto." -Type 'p'))
    $bodyParts.Add((New-ParagraphXml -Text '' -Type 'p'))
    $bodyParts.Add((Convert-ContentToWordXml -Content $document.Content))

    $docPath = Join-Path $outputDir $document.FileName
    New-DocxPackage -Path $docPath -Title $document.Title -Subject $document.Subject -BodyXml ($bodyParts -join '')
}

Write-Output "Arquivos .docx gerados em: $outputDir"
