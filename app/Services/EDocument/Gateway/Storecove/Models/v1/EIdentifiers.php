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


class EIdentifiers
{
    public string $scheme;
    public string $id;

    public function __construct(string $scheme, string $id)
    {
        $this->scheme = $scheme;
        $this->id = $id;
    }
}
