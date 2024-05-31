<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

/**
 * Class UserSessionAttributes.
 */
trait UserSessionAttributes
{
    public function setCurrentCompanyId($value): void
    {
        session(['current_company_id' => $value]);
    }

    public function getCurrentCompanyId(): int
    {
        return session('current_company_id');
    }
}
