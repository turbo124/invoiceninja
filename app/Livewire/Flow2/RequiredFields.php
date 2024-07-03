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

namespace App\Livewire\Flow2;

use App\Utils\Traits\WithSecureContext;
use Livewire\Component;

class RequiredFields extends Component
{
    use WithSecureContext;

    public function render()
    {
        return render('flow2.required-fields', ['contact' => $this->getContext()['contact'], 'fields' => $this->getContext()['fields']]);
    }
}
