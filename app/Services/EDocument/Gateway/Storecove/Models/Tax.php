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

class Tax
{
    public int $percentage;
    public string $country;
    public string $category;

    public function __construct(
        int $percentage,
        string $country,
        string $category
    ) {
        $this->percentage = $percentage;
        $this->country = $country;
        $this->category = $category;
    }
}
