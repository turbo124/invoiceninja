<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Vendor;

use App\Http\Requests\Request;
use App\Http\ValidationRules\IsDeletedRule;
use App\Http\ValidationRules\ValidVendorGroupSettingsRule;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends Request
{
    use MakesHash;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->vendor);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/

        $rules['country_id'] = 'integer|nullable';
        //$rules['id_number'] = 'unique:clients,id_number,,id,company_id,' . auth()->user()->company()->id;
        $rules['id_number'] = 'unique:clients,id_number,'.$this->id.',id,company_id,'.$this->company_id;
        $rules['contacts.*.email'] = 'nullable|distinct';

        $contacts = request('contacts');

        if (is_array($contacts)) {
            // for ($i = 0; $i < count($contacts); $i++) {
                // //    $rules['contacts.' . $i . '.email'] = 'nullable|email|unique:client_contacts,email,' . isset($contacts[$i]['id'].',company_id,'.$this->company_id);
                //     //$rules['contacts.' . $i . '.email'] = 'nullable|email';
                // }
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'unique' => ctrans('validation.unique', ['attribute' => 'email']),
            'email' => ctrans('validation.email', ['attribute' => 'email']),
            'name.required' => ctrans('validation.required', ['attribute' => 'name']),
            'required' => ctrans('validation.required', ['attribute' => 'email']),
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);
    }
}
