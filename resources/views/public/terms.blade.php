@extends('public.layout')

@section('title', 'Termos de Servico | Gestao Edu')
@section('description', 'Termos de Servico publicos do sistema Gestao Edu.')

@section('content')
    <section class="document">
        <div class="wrap">
            <article class="document-card">
                <p class="eyebrow">Uso institucional</p>
                <h1>Termos de Servico</h1>
                <p>
                    Estes Termos de Servico orientam o uso do Gestao Edu, sistema institucional da Secretaria
                    Municipal de Educacao de Umuarama.
                </p>

                <h2>Finalidade do sistema</h2>
                <p>
                    O Gestao Edu deve ser usado para atividades educacionais, administrativas e operacionais
                    relacionadas a rede municipal de ensino, conforme autorizacoes concedidas pela Secretaria.
                </p>

                <h2>Acesso autorizado</h2>
                <p>
                    O acesso e restrito a usuarios autorizados. Cada usuario deve utilizar sua propria conta,
                    manter suas credenciais em seguranca e acessar apenas informacoes necessarias ao desempenho
                    de suas atribuicoes institucionais.
                </p>

                <h2>Responsabilidades do usuario</h2>
                <ul>
                    <li>informar dados corretos nos fluxos sob sua responsabilidade;</li>
                    <li>nao compartilhar credenciais de acesso;</li>
                    <li>respeitar o sigilo de dados escolares, administrativos e pessoais;</li>
                    <li>usar o sistema apenas para finalidades institucionais autorizadas;</li>
                    <li>comunicar falhas, acessos indevidos ou inconsistencias identificadas.</li>
                </ul>

                <h2>Disponibilidade e suporte</h2>
                <p>
                    A Secretaria Municipal de Educacao e a equipe tecnica responsavel podem realizar manutencoes,
                    atualizacoes e ajustes para melhorar seguranca, disponibilidade e funcionamento do sistema.
                </p>

                <h2>Privacidade</h2>
                <p>
                    O tratamento de dados pessoais e operacionais segue a
                    <a href="{{ route('public.privacy', [], false) }}">Politica de Privacidade</a> do Gestao Edu.
                </p>

                <h2>Contato</h2>
                <p>
                    Duvidas sobre estes termos ou sobre o uso da plataforma podem ser encaminhadas para
                    <a href="mailto:automacao@edu.umuarama.pr.gov.br">automacao@edu.umuarama.pr.gov.br</a>.
                </p>
            </article>
        </div>
    </section>
@endsection
