<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\PurchaseCredits;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Credits', [
            'balance' => (string) $request->user()->credit_balance,
            'balance_display' => Money::formatDisplay((string) $request->user()->credit_balance),
            'pack' => (string) config('billing.credit_pack'),
            'pack_display' => Money::formatDisplay((string) config('billing.credit_pack')),
        ]);
    }

    public function store(Request $request, PurchaseCredits $purchaseCredits): RedirectResponse
    {
        $purchaseCredits->handle($request->user());

        return redirect()->route('credits.index');
    }
}
