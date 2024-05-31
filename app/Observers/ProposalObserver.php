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

use App\Models\Proposal;

class ProposalObserver
{
    /**
     * Handle the proposal "created" event.
     */
    public function created(Proposal $proposal): void
    {
        //
    }

    /**
     * Handle the proposal "updated" event.
     */
    public function updated(Proposal $proposal): void
    {
        //
    }

    /**
     * Handle the proposal "deleted" event.
     */
    public function deleted(Proposal $proposal): void
    {
        //
    }

    /**
     * Handle the proposal "restored" event.
     */
    public function restored(Proposal $proposal): void
    {
        //
    }

    /**
     * Handle the proposal "force deleted" event.
     */
    public function forceDeleted(Proposal $proposal): void
    {
        //
    }
}
