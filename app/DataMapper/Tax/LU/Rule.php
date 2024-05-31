<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Tax\LU;

use App\DataMapper\Tax\DE\Rule as DERule;

class Rule extends DERule
{
    public string $seller_region = 'EU';

    public bool $consumer_tax_exempt = false;

    public bool $business_tax_exempt = false;

    public bool $eu_business_tax_exempt = true;

    public bool $foreign_business_tax_exempt = false;

    public bool $foreign_consumer_tax_exempt = false;

    public float $tax_rate = 0;

    public float $reduced_tax_rate = 0;

    public string $tax_name1 = 'TVA';
}
