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


class Contact
{

    public function __construct(
        public string $email,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $phone = null
    ){}
}
