<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class ProposalCategoryPolicy.
 */
class ProposalCategoryPolicy extends EntityPolicy
{
    /**
     * @param User $user
     *
     * @return mixed
     */
    public function create(User $user)
    {
        return $user->is_admin;
    }
}
