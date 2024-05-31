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
     *
     * @return void
     */
    public function created(Proposal $proposal)
    {
        //
    }

    /**
     * Handle the proposal "updated" event.
     *
     * @return void
     */
    public function updated(Proposal $proposal)
    {
        //
    }

    /**
     * Handle the proposal "deleted" event.
     *
     * @return void
     */
    public function deleted(Proposal $proposal)
    {
        //
    }

    /**
     * Handle the proposal "restored" event.
     *
     * @return void
     */
    public function restored(Proposal $proposal)
    {
        //
    }

    /**
     * Handle the proposal "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Proposal $proposal)
    {
        //
    }
}
