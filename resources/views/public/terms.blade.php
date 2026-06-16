@extends('public.layout')

@section('title', 'Termos de Serviço | Gestão Edu')
@section('description', 'Termos de Serviço públicos do sistema Gestão Edu.')

@section('content')
    <section class="document-shell">
        <div class="wrap document-layout">
            <article class="document-card">
                <p class="eyebrow">Uso institucional</p>
                <h1>Termos de Serviço</h1>
                <p>
                    Estes Termos de Serviço orientam o uso do Gestão Edu, sistema institucional da Secretaria
                    Municipal de Educação de Umuarama. A plataforma deve ser utilizada de forma responsável,
                    segura e compatível com as atribuições de cada usuário.
                </p>

                <h2>Finalidade do sistema</h2>
                <p>
                    O Gestão Edu apoia atividades educacionais, administrativas e operacionais relacionadas à
                    rede municipal de ensino. Suas funcionalidades abrangem rotinas de escolas, turmas,
                    professores, avaliações, relatórios, manutenção, alimentação escolar, estoque, documentos,
                    permissões e demais processos internos autorizados.
                </p>

                <h2>Acesso autorizado</h2>
                <p>
                    O acesso é restrito a usuários autorizados pela Secretaria Municipal de Educação ou por seus
                    responsáveis designados. Cada usuário deve utilizar sua própria conta, manter suas credenciais
                    em segurança e consultar apenas informações necessárias ao desempenho de suas atribuições
                    institucionais.
                </p>

                <h2>Responsabilidades do usuário</h2>
                <ul>
                    <li>registrar informações corretas nos fluxos sob sua responsabilidade;</li>
                    <li>não compartilhar credenciais, senhas ou meios de autenticação;</li>
                    <li>respeitar o sigilo de dados escolares, administrativos, operacionais e pessoais;</li>
                    <li>utilizar o sistema apenas para finalidades institucionais autorizadas;</li>
                    <li>comunicar falhas, inconsistências, acessos indevidos ou suspeitas de uso irregular.</li>
                </ul>

                <h2>Disponibilidade e suporte</h2>
                <p>
                    A Secretaria Municipal de Educação e a equipe técnica responsável podem realizar manutenções,
                    atualizações, correções e ajustes para preservar a segurança, a disponibilidade e o bom
                    funcionamento da plataforma.
                </p>

                <h2>Privacidade e proteção de dados</h2>
                <p>
                    O tratamento de dados pessoais e informações operacionais segue a
                    <a href="{{ route('public.privacy', [], false) }}">Política de Privacidade</a> do Gestão Edu.
                    O usuário deve observar as regras de sigilo, finalidade e necessidade de acesso durante a
                    utilização do sistema.
                </p>

                <h2>Contato</h2>
                <p>
                    Dúvidas sobre estes termos ou sobre o uso da plataforma podem ser encaminhadas para
                    <a href="mailto:automacao@edu.umuarama.pr.gov.br">automacao@edu.umuarama.pr.gov.br</a>.
                </p>
            </article>

            <aside class="document-side" aria-label="Informações dos termos">
                <span class="status-pill">
                    <span class="status-dot" aria-hidden="true"></span>
                    Uso autorizado
                </span>
                <p>
                    O Gestão Edu atende processos oficiais da rede municipal de ensino e deve ser utilizado
                    conforme o perfil de acesso de cada usuário.
                </p>
                <p>
                    Para entrar no ambiente autenticado, acesse
                    <a href="/admin/login">Acessar sistema</a>.
                </p>
            </aside>
        </div>
    </section>
@endsection
