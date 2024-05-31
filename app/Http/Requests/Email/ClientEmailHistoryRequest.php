<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Email;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class ClientEmailHistoryRequest extends Request
{
    use MakesHash;

    private string $error_message = '';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('view', $this->client);

    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);
    }
}
