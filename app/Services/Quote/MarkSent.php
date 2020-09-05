<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Quote;

use App\Events\Quote\QuoteWasMarkedSent;
use App\Models\Quote;
use App\Utils\Ninja;

class MarkSent
{
    private $client;

    private $quote;

    public function __construct($client, $quote)
    {
        $this->client = $client;
        $this->quote = $quote;
    }

    public function run()
    {

        /* Return immediately if status is not draft */
        if ($this->quote->status_id != Quote::STATUS_DRAFT) {
            return $this->quote;
        }

        $this->quote->markInvitationsSent();

        event(new QuoteWasMarkedSent($this->quote, $this->quote->company, Ninja::eventVars()));

        $this->quote
             ->service()
             ->setStatus(Quote::STATUS_SENT)
             ->applyNumber()
             ->save();

        return $this->quote;
    }
}
