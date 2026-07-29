<?php

namespace App\Services;

use App\Forms\SystemFunction;
use App\Helpers\MenuHelper;
use App\Models\Form;
use App\Models\User;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Builds the role-filtered page index the in-app assistant grounds its
 * answers in: every page a given user can actually reach, plus hand-authored
 * workflow prose (resources/assistant/workflows.md). Nothing here is a
 * secret — it's the same navigation surface {@see MenuHelper} already
 * renders — but restricting it to what the user's role can reach means the
 * model can never recommend (or link) a page that would 403.
 *
 * @see \App\Http\Controllers\AssistantController where the index doubles as
 *      the allow-list for `[[route:…]]` deep-link tokens.
 */
class AssistantKnowledgeBase
{
    /**
     * @return array{pages: array<int,array{name:string,path:string,route:?string,group:string,description:string,keywords:string}>, workflows: string}
     */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return ['pages' => [], 'workflows' => ''];
        }

        $userType = (int) $user->user_type;
        $isOfficerOrPresident = $userType === 3
            && $user->officers()->whereIn('role', ['officer', 'president'])->exists();

        // Type-3 accounts split into two very different menus (officer/
        // president vs. plain member) — bucket the cache key accordingly so
        // a member never gets served a president's cached, richer index.
        $cacheKey = $userType === 3
            ? 'assistant.kb.3.'.($isOfficerOrPresident ? 'officer' : 'member')
            : "assistant.kb.{$userType}";

        return Cache::remember($cacheKey, 3600, function () use ($user, $userType, $isOfficerOrPresident) {
            // Sidebar-derived entries win on path collisions — they carry a
            // human group label and (for dynamic per-form pages) real IDs.
            $pages = $this->menuPages($user) + $this->supplementalPages($userType, $isOfficerOrPresident);

            return [
                'pages' => array_values($this->attachDescriptions($pages)),
                'workflows' => $this->workflows(),
            ];
        });
    }

    /**
     * Walks {@see MenuHelper::getMenuGroups()} — already role-filtered and
     * already resolving the dynamic per-form request/organization-forms
     * queues to concrete paths with real IDs.
     *
     * MenuHelper reads `auth()->user()` rather than a parameter, so this
     * only reflects $user correctly when called within $user's own
     * authenticated request — true for every real call site
     * (AssistantController) and for tests using `actingAs()`.
     *
     * @return array<string,array{name:string,path:string,route:?string,group:string,description:string,keywords:string}>
     */
    private function menuPages(User $user): array
    {
        $pages = [];

        foreach (MenuHelper::getMenuGroups() as $group) {
            foreach ($group['items'] as $item) {
                $path = $item['path'];
                $pages[$path] = [
                    'name' => $item['name'],
                    'path' => $path,
                    'route' => $this->routeNameForPath($path),
                    'group' => $group['title'],
                    'description' => '',
                    'keywords' => mb_strtolower($item['name'].' '.$group['title']),
                ];
            }
        }

        return $pages;
    }

    /**
     * Reachable pages the sidebar doesn't surface (e.g. the field-reference
     * help page), found by walking the route table directly. Restricted to
     * GET routes with zero required parameters so every entry is a concrete,
     * safely-linkable path — a detail page like a specific request's review
     * screen only exists once you already have an ID, so it isn't offered as
     * a standalone index entry.
     *
     * @return array<string,array{name:string,path:string,route:?string,group:string,description:string,keywords:string}>
     */
    private function supplementalPages(int $userType, bool $isOfficerOrPresident): array
    {
        $pages = [];

        foreach (Route::getRoutes()->get('GET') as $route) {
            $name = $route->getName();
            if (! $name || $name === 'assistant.chat') {
                continue;
            }

            $uri = $route->uri();
            if (str_starts_with($uri, 'api/')) {
                continue; // JSON endpoints, not navigable pages
            }

            if ($route->parameterNames() !== []) {
                continue;
            }

            $middleware = $route->middleware();
            if (! in_array('auth', $middleware, true) || in_array('guest', $middleware, true)) {
                continue;
            }

            if (! $this->satisfiesRoleMiddleware($middleware, $userType, $isOfficerOrPresident)) {
                continue;
            }

            $path = '/'.ltrim($uri, '/');
            if (isset($pages[$path])) {
                continue;
            }

            $pages[$path] = [
                'name' => $this->titleFromRouteName($name),
                'path' => $path,
                'route' => $name,
                'group' => match (true) {
                    str_starts_with($name, 'superadmin.') => 'Superadmin',
                    str_starts_with($name, 'admin.') => 'Admin',
                    default => 'More',
                },
                'description' => '',
                'keywords' => mb_strtolower(str_replace(['.', '-', '_'], ' ', $name)),
            ];
        }

        return $pages;
    }

    /**
     * Mirrors the route-name → user_type checks the app's own middleware
     * aliases perform (bootstrap/app.php), for routes gated by exactly one
     * of them. Organization-scoped role gates (role.admin/president/officer)
     * need a specific org in context — never true for a zero-param route —
     * so they're excluded rather than guessed at.
     *
     * @param  array<int,string>  $middleware
     */
    private function satisfiesRoleMiddleware(array $middleware, int $userType, bool $isOfficerOrPresident): bool
    {
        if (in_array('superadmin', $middleware, true)) {
            return $userType === 1;
        }
        if (in_array('admin', $middleware, true)) {
            return $userType === 2;
        }
        if (in_array('admin.or.superadmin', $middleware, true)) {
            return in_array($userType, [1, 2], true);
        }
        if (in_array('officer.or.admin', $middleware, true) || in_array('president.or.admin', $middleware, true)) {
            return $userType === 2 || $isOfficerOrPresident;
        }
        if (array_intersect(['role.admin', 'role.president', 'role.officer'], $middleware) !== []) {
            return false;
        }

        return true; // plain `auth`: any signed-in user_type may reach it.
    }

    /**
     * Enriches page entries with the best prose in the codebase: a form
     * page's own description, and — for the handful of pages that are a
     * system function's dedicated entry point — {@see SystemFunction}'s
     * one-sentence purpose blurb.
     *
     * @param  array<string,array{name:string,path:string,route:?string,group:string,description:string,keywords:string}>  $pages
     * @return array<string,array{name:string,path:string,route:?string,group:string,description:string,keywords:string}>
     */
    private function attachDescriptions(array $pages): array
    {
        if ($pages === []) {
            return $pages;
        }

        $forms = Form::query()->get(['id', 'description_text', 'route_name']);
        $descriptionByFormId = $forms->pluck('description_text', 'id');
        $descriptionByRouteName = $forms->pluck('description_text', 'route_name');

        // Fixed dedicated admin pages for the two system functions that are
        // deliberately excluded from the generic per-form request queue.
        $staticFunctionPages = [
            '/admin/activity-requests' => SystemFunction::NEW_EVENT,
            '/admin/workplan-requests' => SystemFunction::NEW_WORKPLAN,
        ];

        foreach ($pages as $path => &$page) {
            if (isset($staticFunctionPages[$path])) {
                $page['description'] = SystemFunction::catalog()[$staticFunctionPages[$path]]['description'];

                continue;
            }

            if (preg_match('#^/admin/form-requests/(\d+)$#', $path, $m) === 1) {
                $page['description'] = trim((string) ($descriptionByFormId[(int) $m[1]] ?? ''));

                continue;
            }

            if ($path !== '/forms' && preg_match('#^/forms/(.+)$#', $path, $m) === 1) {
                $description = trim((string) ($descriptionByRouteName[$m[1]] ?? ''));
                $systemFunction = $this->systemFunctionForRouteName($m[1]);
                if ($systemFunction !== null) {
                    $description = trim($description.' '.SystemFunction::catalog()[$systemFunction]['description']);
                }
                $page['description'] = $description;
            }
        }

        return $pages;
    }

    private function systemFunctionForRouteName(string $routeName): ?string
    {
        foreach (SystemFunction::keys() as $key) {
            if (SystemFunction::form($key)?->route_name === $routeName) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Finds the named route whose URI pattern matches a concrete path
     * (e.g. `/admin/form-requests/12` → `admin.form-requests.index`), purely
     * for the model's/maintainer's reference — deep-link resolution never
     * recomputes a path from this name (see AssistantController), so a
     * parameterized match here is safe even though `route($name)` alone
     * couldn't reproduce the path without the id.
     */
    private function routeNameForPath(string $path): ?string
    {
        $request = HttpRequest::create($path, 'GET');

        foreach (Route::getRoutes()->get('GET') as $route) {
            if ($route->matches($request, false)) {
                return $route->getName();
            }
        }

        return null;
    }

    private function titleFromRouteName(string $name): string
    {
        $trimmed = (string) preg_replace('/\.(index|show|create)$/', '', $name);

        return ucwords(str_replace(['.', '-', '_'], ' ', $trimmed));
    }

    private function workflows(): string
    {
        $path = resource_path('assistant/workflows.md');

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
