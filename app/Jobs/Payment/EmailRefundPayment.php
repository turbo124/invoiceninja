<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Payment;

use App\Utils\Ninja;
use App\Models\Payment;
use App\Models\Company;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Services\Email\Email;
use App\Services\Email\EmailObject;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\Payment\PaymentWasEmailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class EmailRefundPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $settings;

    public function __construct(public Payment $payment, private Company $company, private ?ClientContact $contact)
    {
        $this->settings = $payment->client->getMergedSettings();
    }

    public function handle()
    {
        if ($this->company->is_disabled) {
            return true;
        }

        if (! $this->contact->email) {
            return;
        }

        MultiDB::setDb($this->company->db);

        $this->payment->load('invoices');
        $this->contact->load('client');

        /* Resolve refund copy in the recipient's locale before it is rendered. */
        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($this->contact->preferredLocale());
        $t->replace(Ninja::transformTranslations($this->settings));

        $template_data = [
            'body' => ctrans('texts.refunded_payment') . ' $payment.refunded <br><br>$invoices',
            'subject' => ctrans('texts.refunded_payment'),
        ];

        $mo = new EmailObject();
        $mo->entity_id = $this->payment->id;
        $mo->entity_class = Payment::class;
        $mo->client_id = $this->payment->client_id;
        $mo->client_contact_id = $this->contact->id;
        $mo->is_refund = true;
        $mo->template_data = $template_data;

        /* Synchronous so the PaymentWasEmailed event fires after the actual send attempt (matches the legacy ->handle() ordering). */
        Email::dispatchSync($mo, $this->company);

        event(new PaymentWasEmailed($this->payment, $this->payment->company, $this->contact, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
    }
}
