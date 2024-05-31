<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Payments;

use App\Http\Requests\Request;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Utils\Traits\MakesHash;

class PaymentWebhookRequest extends Request
{
    use MakesHash;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            //
        ];
    }

    /**
     * Resolve company gateway.
     *
     * @return null|\App\Models\CompanyGateway
     */
    public function getCompanyGateway()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        return CompanyGateway::withTrashed()->find($this->decodePrimaryKey($this->company_gateway_id));
    }

    /**
     * Resolve payment hash.
     *
     * @return null|\App\Models\PaymentHash
     */
    public function getPaymentHash()
    {
        if ($this->query('hash')) {
            MultiDB::findAndSetDbByCompanyKey($this->company_key);

            return PaymentHash::where('hash', $this->query('hash'))->firstOrFail();
        }

        return false;
    }

    /**
     * Resolve company from company_key parameter.
     */
    public function getCompany(): ?Company
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        return Company::where('company_key', $this->company_key)->firstOrFail();
    }
}
