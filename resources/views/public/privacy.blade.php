@extends('public.layout')

@section('title', 'Politica de Privacidade | Gestao Edu')
@section('description', 'Politica de Privacidade publica do sistema Gestao Edu.')

@section('content')
    <section class="document">
        <div class="wrap">
            <article class="document-card">
                <p class="eyebrow">Conformidade</p>
                <h1>Politica de Privacidade</h1>
                <p>
                    Esta Politica de Privacidade descreve como o Gestao Edu trata dados pessoais e operacionais
                    usados pela Secretaria Municipal de Educacao de Umuarama no contexto da gestao educacional.
                </p>

                <h2>Dados coletados</h2>
                <p>O sistema pode coletar e armazenar dados necessarios ao seu funcionamento, incluindo:</p>
                <ul>
                    <li>nome, e-mail institucional, identificadores de usuario e informacoes de perfil;</li>
                    <li>foto ou dados basicos de perfil Google, quando o login Google for utilizado;</li>
                    <li>vinculos com escola, setor, funcao, perfis de acesso, papeis e permissoes;</li>
                    <li>registros operacionais inseridos nos modulos do sistema;</li>
                    <li>arquivos, documentos e anexos necessarios aos fluxos autorizados;</li>
                    <li>logs de acesso, auditoria, seguranca e historico de alteracoes.</li>
                </ul>

                <h2>Por que os dados sao coletados</h2>
                <p>Os dados sao utilizados para:</p>
                <ul>
                    <li>autenticar usuarios e controlar o acesso ao sistema;</li>
                    <li>permitir a execucao de processos educacionais e administrativos;</li>
                    <li>organizar informacoes por escola, setor, perfil e responsabilidade;</li>
                    <li>gerar relatorios, documentos, indicadores e registros de acompanhamento;</li>
                    <li>manter rastreabilidade, auditoria, suporte tecnico e seguranca da plataforma.</li>
                </ul>

                <h2>Quem utiliza as informacoes</h2>
                <p>
                    As informacoes sao acessadas apenas por usuarios autorizados da Secretaria Municipal de Educacao,
                    unidades escolares, setores vinculados e equipe tecnica responsavel pela manutencao do sistema,
                    sempre conforme perfil de acesso e necessidade institucional.
                </p>

                <h2>Como os dados sao protegidos</h2>
                <p>
                    O Gestao Edu utiliza medidas tecnicas e administrativas para proteger as informacoes, incluindo
                    acesso por usuario autenticado, controle de permissoes, conexao segura por HTTPS no ambiente
                    publico, registros de auditoria, restricao de acesso por perfil e boas praticas de armazenamento.
                </p>

                <h2>Contato responsavel</h2>
                <p>
                    Para assuntos relacionados a privacidade, seguranca ou tratamento de dados do Gestao Edu,
                    entre em contato pelo e-mail
                    <a href="mailto:automacao@edu.umuarama.pr.gov.br">automacao@edu.umuarama.pr.gov.br</a>.
                </p>
            </article>
        </div>
    </section>
@endsection
