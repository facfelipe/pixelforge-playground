<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'credit_balance' => (string) $user->credit_balance,
                    'credit_balance_display' => Money::formatDisplay((string) $user->credit_balance, 2),
                    'is_admin' => $user->isAdmin(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'appName' => config('app.name'),
        ];
    }
}
