<?php
/**
 * Project Ninja (https://paymentninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Project Ninja LLC (https://paymentninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Project;

use App\Http\Requests\Request;

class InvoiceProjectRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->project);
    }

    public function rules()
    {
        return [];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);

    }
}
