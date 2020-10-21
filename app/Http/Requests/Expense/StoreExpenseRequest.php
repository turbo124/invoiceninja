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

namespace App\Http\Requests\Expense;

use App\DataMapper\ExpenseSettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Expense\UniqueExpenseNumberRule;
use App\Http\ValidationRules\User\RelatedUserRule;
use App\Http\ValidationRules\ValidExpenseGroupSettingsRule;
use App\Models\Expense;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', Expense::class);
    }

    public function rules()
    {
info(print_r($this->all(),1));

        $rules['id_number'] = 'unique:expenses,id_number,'.$this->id.',id,company_id,'.$this->company_id;

        $rules['contacts.*.email'] = 'nullable|distinct';

        $rules['number'] = new UniqueExpenseNumberRule($this->all());

        $rules['client_id'] = 'bail|sometimes|exists:clients,id,company_id,'.auth()->user()->company()->id;
        $rules['vendor_id'] = 'bail|sometimes|exists:vendors,id,company_id,'.auth()->user()->company()->id;
        $rules['assigned_user_id'] = [
            'bail' , 
            'sometimes', 
            'nullable',
                new RelatedUserRule($this->all())
            ];
            //,id,company_id,'.auth()->user()->company()->id;

        $rules['invoice_id'] = 'bail|nullable|sometimes|exists:invoices,id,company_id,'.auth()->user()->company()->id.',client_id,'.$this['client_id'];

        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (array_key_exists('user_id', $input) && is_string($input['user_id'])) {
            $input['user_id'] = $this->decodePrimaryKey($input['user_id']);
        }

        if (array_key_exists('vendor_id', $input) && is_string($input['vendor_id'])) {
            $input['vendor_id'] = $this->decodePrimaryKey($input['vendor_id']);
        }        

        if (array_key_exists('client_id', $input) && is_string($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }   

        if (array_key_exists('invoice_id', $input) && is_string($input['invoice_id'])) {
            $input['invoice_id'] = $this->decodePrimaryKey($input['invoice_id']);
        }  

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'unique' => ctrans('validation.unique', ['attribute' => 'email']),
            //'required' => trans('validation.required', ['attribute' => 'email']),
            'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
        ];
    }
}
