<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\XeroTenant;

use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkXeroTenantRequest extends FormRequest
{
    use MakesHash;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin() && auth()->user()->account_id == $this->xero_tenant->account_id;
    }

    public function rules(): array
    {
        return [];
    }

}
