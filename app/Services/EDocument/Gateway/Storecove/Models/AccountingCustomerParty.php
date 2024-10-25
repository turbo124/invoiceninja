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


class AccountingCustomerParty
{
    /** @var PublicIdentifiers[] */
    public array $publicIdentifiers;
    public Party $party;

    /**
     * @param PublicIdentifiers[] $publicIdentifiers
     */
    public function __construct(array $publicIdentifiers, Party $party)
    {
        $this->publicIdentifiers = $publicIdentifiers;
        $this->party = $party;
    }
}
