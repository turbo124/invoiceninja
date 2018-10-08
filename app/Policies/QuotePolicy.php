<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class QuotePolicy.
 */
class QuotePolicy extends EntityPolicy
{
    /**
     * @param User $user
     *
     * @return bool
     */
    public function create(User $user)
    {
        if (! $this->createPermission($user, ENTITY_QUOTE)) {
            return false;
        }

        return $user->hasFeature(FEATURE_QUOTES);
    }
}
