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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Utils\Ninja;
use App\Models\Company;

class StorecoveProxy
{
    public Company $company;

    public function __construct(public Storecove $storecove)
    {
    }
    
    public function setCompany(Company $company): self
    {
        $this->company = $company;
        return $this;
    }

    public function getLegalEntity(int $legal_entity_id): array
    {
        if(Ninja::isHosted()){
            $response = $this->storecove->getLegalEntity($legal_entity_id);

            if(is_array($response))
                return $response;

            return $this->handleResponseError($response);
        }

        
    $headers['X-EInvoice-Token'] = $company->account->e_invoicing_token;

    }

    private function handleResponseError($response): array
    {

    }
}