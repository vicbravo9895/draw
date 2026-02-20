<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shared = [
            ...parent::share($request),
            'name' => config('app.name'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];

        // Share portal auth if on portal routes
        if (auth('portal')->check()) {
            $viewer = auth('portal')->user();
            $shared['auth'] = [
                'viewer' => [
                    'id' => $viewer->id,
                    'email' => $viewer->email,
                    'name' => $viewer->name,
                    'company_id' => $viewer->company_id,
                    'company' => $viewer->company ? [
                        'id' => $viewer->company->id,
                        'name' => $viewer->company->name,
                        'public_code' => $viewer->company->public_code,
                    ] : null,
                ],
            ];
        } else {
            $user = $request->user();
            $shared['auth'] = [
                'user' => $user,
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->toArray() : [],
                'roles' => $user ? $user->getRoleNames()->toArray() : [],
            ];
        }

        // Flash messages
        $shared['flash'] = [
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
        ];

        return $shared;
    }
}
