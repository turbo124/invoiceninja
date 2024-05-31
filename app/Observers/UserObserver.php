<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Jobs\User\VerifyPhone;
use App\Models\User;
use App\Utils\Ninja;

class UserObserver
{
    /**
     * Handle the app models user "created" event.
     */
    public function created(User $user): void
    {
        if (Ninja::isHosted() && isset($user->phone)) {
            VerifyPhone::dispatch($user);
        }
    }

    /**
     * Handle the app models user "updated" event.
     */
    public function updated(User $user): void
    {
        if (Ninja::isHosted() && $user->isDirty('email') && $user->company_users()->where('is_owner', true)->exists()) {
            //ensure they are owner user and update email on file.
            if (class_exists(\Modules\Admin\Jobs\Account\UpdateOwnerUser::class)) {
                \Modules\Admin\Jobs\Account\UpdateOwnerUser::dispatch($user->account->key, $user, $user->getOriginal('email'));
            }
        }
    }

    /**
     * Handle the app models user "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the app models user "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the app models user "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
