<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Avaliacao;
use App\Models\DominioEmail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('mobile.home', [
            'user' => $user,
            'accessCards' => $this->getAccessCards(),
            'reportCards' => $this->getReportCards(),
        ]);
    }

    protected function getAccessCards(): array
    {
        return array_values(array_filter([
            Gate::allows('viewAny', User::class) ? $this->makeCard(
                title: 'Usuários',
                description: 'Liberacoes, vinculos e níveis em uma tela leve para o celular.',
                url: route('mobile.users.index'),
                badge: 'US',
                tone: 'slate',
            ) : null,
            Gate::allows('viewAny', DominioEmail::class) ? $this->makeCard(
                title: 'Domínios de e-mail',
                description: 'Controle rápido dos domínios autorizados para entrar no sistema.',
                url: route('mobile.domains.index'),
                badge: 'DM',
                tone: 'amber',
            ) : null,
            Gate::allows('viewAny', Role::class) ? $this->makeCard(
                title: 'Níveis de acesso',
                description: 'Visualize perfis, quantidade de permissões e estrutura de acesso.',
                url: route('mobile.roles.index'),
                badge: 'NA',
                tone: 'sky',
            ) : null,
        ]));
    }

    protected function getReportCards(): array
    {
        return array_values(array_filter([
            Gate::allows('viewReportsDashboard', Avaliacao::class) ? $this->makeCard(
                title: 'Central de relatórios',
                description: 'Resumo geral e entrada para as consultas operacionais.',
                url: route('mobile.reports.dashboard'),
                badge: 'RD',
                tone: 'emerald',
            ) : null,
            Gate::allows('viewProfessorComponentTurmaReport', Avaliacao::class) ? $this->makeCard(
                title: 'Professor por turma',
                description: 'Escola, série, turno, componente e professor em cards mobile.',
                url: route('mobile.reports.professor-by-class'),
                badge: 'PT',
                tone: 'violet',
            ) : null,
            Gate::allows('viewMissingTeachersReport', Avaliacao::class) ? $this->makeCard(
                title: 'Componentes sem professor',
                description: 'Mapeie rapidamente as lacunas de cobertura por componente e turma.',
                url: route('mobile.reports.missing-teachers'),
                badge: 'CP',
                tone: 'rose',
            ) : null,
        ]));
    }

    protected function makeCard(
        string $title,
        string $description,
        string $url,
        string $badge,
        string $tone,
    ): array {
        return compact('title', 'description', 'url', 'badge', 'tone');
    }
}