<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\DominioEmail;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class AccessController extends Controller
{
    public function index(Request $request): View
    {
        return view('mobile.access.index', [
            'cards' => $this->getCards($request->user()),
        ]);
    }

    public function users(Request $request, UserService $userService): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->string('search'));

        $scopedQuery = $userService->listarUsuariosQuery(
            User::query(),
            $request->user(),
        );

        $statsQuery = clone $scopedQuery;

        $users = (clone $scopedQuery)
            ->with(['roles:id,name', 'escola:id,nome', 'setor:id,nome'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('mobile.access.users', [
            'search' => $search,
            'users' => $users,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'approved' => (clone $statsQuery)->where('email_approved', true)->count(),
                'multiRole' => (clone $statsQuery)->has('roles', '>', 1)->count(),
                'directPermissions' => (clone $statsQuery)->has('permissions')->count(),
            ],
        ]);
    }

    public function domains(Request $request): View
    {
        Gate::authorize('viewAny', DominioEmail::class);

        $search = trim((string) $request->string('search'));

        $baseQuery = DominioEmail::query();

        $domains = (clone $baseQuery)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('dominio_email', 'like', "%{$search}%")
                        ->orWhere('setor', 'like', "%{$search}%");
                });
            })
            ->orderBy('dominio_email')
            ->paginate(12)
            ->withQueryString();

        return view('mobile.access.domains', [
            'search' => $search,
            'domains' => $domains,
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'active' => (clone $baseQuery)->where('status', true)->count(),
                'withSector' => (clone $baseQuery)->whereNotNull('setor')->where('setor', '!=', '')->count(),
            ],
        ]);
    }

    public function roles(Request $request): View
    {
        Gate::authorize('viewAny', Role::class);

        $search = trim((string) $request->string('search'));

        $baseQuery = Role::query();

        $roles = (clone $baseQuery)
            ->withCount(['permissions', 'users'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('mobile.access.roles', [
            'search' => $search,
            'roles' => $roles,
            'stats' => [
                'roles' => (clone $baseQuery)->count(),
                'withPermissions' => (clone $baseQuery)->has('permissions')->count(),
                'permissions' => Permission::query()->count(),
                'updatedToday' => (clone $baseQuery)->whereDate('updated_at', today())->count(),
            ],
        ]);
    }

    protected function getCards(?User $user): array
    {
        return array_values(array_filter([
            Gate::allows('viewAny', User::class) ? $this->makeCard(
                title: 'Usuarios',
                description: 'Lista mobile com status de acesso, escola e niveis ativos.',
                url: route('mobile.users.index'),
                badge: 'US',
                tone: 'slate',
            ) : null,
            Gate::allows('viewAny', DominioEmail::class) ? $this->makeCard(
                title: 'Dominios de e-mail',
                description: 'Consulte quais dominios estao ativos e por qual setor foram organizados.',
                url: route('mobile.domains.index'),
                badge: 'DM',
                tone: 'amber',
            ) : null,
            Gate::allows('viewAny', Role::class) ? $this->makeCard(
                title: 'Niveis de acesso',
                description: 'Veja o catalogo de perfis e a distribuicao de permissoes.',
                url: route('mobile.roles.index'),
                badge: 'NA',
                tone: 'sky',
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
