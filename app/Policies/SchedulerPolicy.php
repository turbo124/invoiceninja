<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Policies;

use App\Models\User;

/**
 * Class SchedulerPolicy.
 */
class SchedulerPolicy extends EntityPolicy
{
    /**
     *  Checks if the user has create permissions.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
}
