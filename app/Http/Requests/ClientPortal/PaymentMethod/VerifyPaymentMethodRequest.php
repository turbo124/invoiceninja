<?php

namespace App\Http\Requests\ClientPortal\PaymentMethod;

use App\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentMethodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'transactions.*' => 'integer',
        ];
    }
}
