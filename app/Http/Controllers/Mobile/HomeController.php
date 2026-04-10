<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
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
            'reportCards' => $this->getReportCards($user),
        ]);
    }

    protected function getAccessCards(): array
    {
        return array_values(array_filter([
            Gate::allows('viewAny', User::class) ? $this->makeCard(
                title: 'Usuarios',
                description: 'Liberacoes, vinculos e niveis em uma tela leve para o celular.',
                url: route('mobile.users.index'),
                badge: 'US',
                tone: 'slate',
            ) : null,
            Gate::allows('viewAny', DominioEmail::class) ? $this->makeCard(
                title: 'Dominios de e-mail',
                description: 'Controle rapido dos dominios autorizados para entrar no sistema.',
                url: route('mobile.domains.index'),
                badge: 'DM',
                tone: 'amber',
            ) : null,
            Gate::allows('viewAny', Role::class) ? $this->makeCard(
                title: 'Niveis de acesso',
                description: 'Visualize perfis, quantidade de permissoes e estrutura de acesso.',
                url: route('mobile.roles.index'),
                badge: 'NA',
                tone: 'sky',
            ) : null,
        ]));
    }

    protected function getReportCards(?User $user): array
    {
        return array_values(array_filter([
            $this->userHasAnyPermission($user, [
                'Listar Relatórios: Dashboard',
                'Listar RelatÃ³rios: Dashboard',
            ]) ? $this->makeCard(
                title: 'Central de relatorios',
                description: 'Resumo geral e entrada para as consultas operacionais.',
                url: route('mobile.reports.dashboard'),
                badge: 'RD',
                tone: 'emerald',
            ) : null,
            $this->userHasAnyPermission($user, [
                'Listar Relatórios: Professor por Componente e Turma',
                'Listar RelatÃ³rios: Professor por Componente e Turma',
            ]) ? $this->makeCard(
                title: 'Professor por turma',
                description: 'Escola, serie, turno, componente e professor em cards mobile.',
                url: route('mobile.reports.professor-by-class'),
                badge: 'PT',
                tone: 'violet',
            ) : null,
            $this->userHasAnyPermission($user, [
                'Listar Relatórios: Componentes com Professores Faltando',
                'Listar RelatÃ³rios: Componentes com Professores Faltando',
            ]) ? $this->makeCard(
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

    protected function userHasAnyPermission(?User $user, array $permissions): bool
    {
        if (! $user) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }
}
