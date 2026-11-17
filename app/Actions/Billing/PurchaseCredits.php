<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class PurchaseCredits
{
    public function handle(User $user, ?string $amount = null): User
    {
        $pack = $amount ?? (string) config('billing.credit_pack');

        return DB::transaction(function () use ($user, $pack): User {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $locked->credit_balance = Money::add((string) $locked->credit_balance, $pack);
            $locked->save();

            return $locked->refresh();
        });
    }
}
