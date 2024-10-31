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

namespace App\Services\EDocument\Gateway\Storecove\Models;

class Routing
{
    /** @var EIdentifiers[] */
    public array $eIdentifiers;
    /** @var string[] */
    public array $emails;

    /**
     * @param EIdentifiers[] $eIdentifiers
     * @param string[] $emails
     */
    public function __construct(array $eIdentifiers, array $emails)
    {
        $this->eIdentifiers = $eIdentifiers;
        $this->emails = $emails;
    }
}
