<?php

namespace App\Http\Requests\ClientPortal\Credits;

use App\Http\ViewComposers\PortalComposer;
use Illuminate\Foundation\Http\FormRequest;

class ShowCreditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return ! $this->credit->is_deleted
            && auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_CREDITS
            && auth()->guard('contact')->user()->client_id === $this->credit->client_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
