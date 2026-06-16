@extends('public.layout')

@section('title', 'Política de Privacidade | Gestão Edu')
@section('description', 'Política de Privacidade pública do sistema Gestão Edu.')

@section('content')
    <section class="document-shell">
        <div class="wrap document-layout">
            <article class="document-card">
                <p class="eyebrow">Privacidade e segurança</p>
                <h1>Política de Privacidade</h1>
                <p>
                    Está Política de Privacidade apresenta, de forma objetiva, como o Gestão Edu trata dados
                    pessoais e informações operacionais utilizados pela Secretaria Municipal de Educação de
                    Umuarama no contexto da gestão educacional.
                </p>
                <p>
                    O sistema é de uso institucional e deve ser acessado apenas por usuários autorizados,
                    conforme a função exercida, o vínculo com escola ou setor e as permissões concedidas.
                </p>

                <h2>Dados e informações tratados</h2>
                <p>Para cumprir suas finalidades, o sistema pode registrar e armazenar informações como:</p>
                <ul>
                    <li>nome, e-mail institucional, identificadores de usuário e dados básicos de perfil;</li>
                    <li>foto ou dados básicos de perfil do Google, quando o login Google for utilizado;</li>
                    <li>vínculos com escola, setor, função, papéis, perfis de acesso e permissões;</li>
                    <li>registros educacionais, administrativos e operacionais inseridos nos módulos do sistema;</li>
                    <li>arquivos, documentos, anexos e evidências necessários aos fluxos autorizados;</li>
                    <li>logs de acesso, auditoria, segurança e histórico de alterações.</li>
                </ul>

                <h2>Finalidade do tratamento</h2>
                <p>As informações são utilizadas para:</p>
                <ul>
                    <li>autenticar usuários e controlar o acesso à plataforma;</li>
                    <li>executar processos educacionais, administrativos e operacionais da rede municipal;</li>
                    <li>organizar dados por escola, setor, perfil, função e responsabilidade institucional;</li>
                    <li>gerar relatórios, documentos, indicadores e registros de acompanhamento;</li>
                    <li>preservar rastreabilidade, auditoria, suporte técnico e segurança do sistema.</li>
                </ul>

                <h2>Acesso às informações</h2>
                <p>
                    As informações são acessadas somente por usuários autorizados da Secretaria Municipal de
                    Educação, por unidades escolares, por setores vinculados e pela equipe técnica responsável
                    pela manutenção da plataforma. O acesso deve observar o perfil de permissão e a necessidade
                    institucional de cada atividade.
                </p>

                <h2>Proteção e segurança</h2>
                <p>
                    O Gestão Edu adota medidas técnicas e administrativas para proteger as informações, incluindo
                    autenticação de usuário, controle de permissões, conexão segura por HTTPS no ambiente público,
                    registros de auditoria, restrição de acesso por perfil e boas práticas de armazenamento.
                </p>

                <h2>Contato responsável</h2>
                <p>
                    Para assuntos relacionados à privacidade, à segurança ou ao tratamento de dados no Gestão Edu,
                    entre em contato pelo e-mail
                    <a href="mailto:automacao@edu.umuarama.pr.gov.br">automacao@edu.umuarama.pr.gov.br</a>.
                </p>
            </article>

            <aside class="document-side" aria-label="Informações da política">
                <span class="status-pill">
                    <span class="status-dot" aria-hidden="true"></span>
                    Documento público
                </span>
                <p>
                    Está página orienta usuários e responsáveis sobre o tratamento de informações no contexto
                    institucional do Gestão Edu.
                </p>
                <p>
                    O uso da plataforma também deve observar os
                    <a href="{{ route('public.terms', [], false) }}">Termos de Serviço</a>.
                </p>
            </aside>
        </div>
    </section>
@endsection
