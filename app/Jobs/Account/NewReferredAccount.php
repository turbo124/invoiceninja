<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Account;

use App\DataMapper\Referral\ReferralEarning;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class NewReferredAccount  implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private string $account_key)
    {
    }

    public function handle()
    {

        MultiDB::findAndSetDbByAccountKey($this->account_key);
        $account = Account::where('key', $this->account_key)->first();

        $user_referrer = $account->referral_code;

        $user = MultiDB::findUserByReferralCode($user_referrer);

        if($this->checkAccountExists($user))
            return;

        $this->addReferralEntry($user);
    }
    
    /**
     * checkAccountExists
     *
     * Check if the first entry for this account exists yet.
     * 
     * @return bool
     */
    private function checkAccountExists(User $user): bool
    {
        return collect($user->referral_earnings)
                    ->pluck('account_key')
                    ->contains($this->account_key);
    }

    private function addReferralEntry(User $user): void
    {
        $referral = new ReferralEarning([
            'account_key' => $this->account_key,
            'referral_start_date' => now()->format('Y-m-d'),
            'period_endinig' => now()->format('Y-m-d'),
            'payout_status' => 'pending',
            'gross_amount' => 0,
            'commission_amount' => 0
        ]);
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->account_key)];
    }
}