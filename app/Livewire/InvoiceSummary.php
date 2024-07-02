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

namespace App\Livewire;

use App\Utils\Traits\WithSecureContext;
use Livewire\Attributes\On;
use Livewire\Component;

class InvoiceSummary extends Component
{
    use WithSecureContext;

    public $invoice;

    public function mount()
    {
        //@TODO for a single invoice - show all details, for multi-invoices, only show the summaries
        $this->invoice = $this->getContext()['invitation']->invoice; // $this->context['invitation']->invoice;
    }

    #[On(self::CONTEXT_UPDATE)]
    public function onContextUpdate(): void
    {
        // refactor logic for updating the price for eg if it changes with under/over pay

        $this->invoice = $this->getContext()['invitation']->invoice;
    }

    public function render()
    {
        return render('components.livewire.invoice-summary', [
            'invoice' => $this->invoice
        ]);
    }
}
