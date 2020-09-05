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

namespace App\Events\Quote;

use App\Models\Company;
use App\Models\Quote;
use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteWasEmailed.
 */
class QuoteWasEmailed
{
    use SerializesModels;

    public $quote;

    public $company;

    public $notes;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param $quote
     */
    public function __construct(Quote $quote, string $notes, Company $company, array $event_vars)
    {
        $this->quote = $quote;
        $this->notes = $notes;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
