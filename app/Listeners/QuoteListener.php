<?php

namespace App\Listeners;

use App\Events\QuoteWasEmailed;
use App\Events\QuoteInvitationWasViewed;

/**
 * Class QuoteListener.
 */
class QuoteListener
{
    /**
     * @param QuoteInvitationWasViewed $event
     */
    public function viewedQuote(QuoteInvitationWasViewed $event)
    {
        $invitation = $event->invitation;
        $invitation->markViewed();
    }

    /**
     * @param InvoiceWasEmailed $event
     */
    public function emailedQuote(QuoteWasEmailed $event)
    {
        $quote = $event->quote;
        $quote->last_sent_date = date('Y-m-d');
        $quote->save();
    }
}
