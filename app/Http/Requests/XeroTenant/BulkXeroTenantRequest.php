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

class BulkXeroTenantRequest extends FormRequest
{
    use MakesHash;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'ids' => ['required','bail','array',Rule::exists('xero_tenants','id')->where('account_id', auth()->user()->account_id)],
            'action' => 'required|bail|in:archive,restore,delete'
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if(isset($input['ids']))
            $input['ids'] = $this->transformKeys($input['ids']);

        $this->replace($input);
    }

}
