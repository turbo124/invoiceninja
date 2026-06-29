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
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\Payment\PaymentWasEmailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class EmailPayment implements ShouldQueue
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
        MultiDB::setDb($this->company->db);

        $this->payment->load('invoices');

        if (! $this->contact) {
            $this->contact = $this->payment->client->contacts()->orderBy('is_primary', 'desc')->orderBy('send_email', 'desc')->first();
        }

        if (! $this->contact) {
            return;
        }

        if ($this->company->is_disabled) {
            nlog("company disabled");
            return;
        }

        $this->contact->load('client');

        if ($this->payment->client->getSetting('payment_email_all_contacts') && $this->payment->invoices->count() >= 1) {
            $this->emailAllContacts();
            return;
        }

        $cc = $this->payment->client->cc_contacts();

        $this->dispatchPaymentEmail($this->contact, $cc);

        event(new PaymentWasEmailed($this->payment, $this->payment->company, $this->contact, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
    }

    private function emailAllContacts(): void
    {
        $invoice = $this->payment->invoices->first();

        $validInvitations = $invoice->invitations->filter(function ($invite) {
            return $invite->contact->send_email && filter_var($invite->contact->email, FILTER_VALIDATE_EMAIL) !== false;
        });

        if ($validInvitations->isEmpty()) {
            return;
        }

        $primaryInvite = $validInvitations->first();

        /** Contacts who have an invite and need a copy of the receipt */
        $ccEmails = $validInvitations->slice(1)->map(function ($invite) {
            return $invite->contact->email;
        })->values()->all();

        /** Merge in the CC only contacts who DON'T have an invite */
        $ccOnlyEmails = collect($this->payment->client->cc_contacts())
            ->map(fn ($address) => $address->address)
            ->toArray();

        $ccEmails = array_unique(array_merge($ccEmails, $ccOnlyEmails));

        $cc = array_map(fn ($email) => new Address($email), $ccEmails);

        $this->dispatchPaymentEmail($primaryInvite->contact, $cc);

        event(new PaymentWasEmailed($this->payment, $this->payment->company, $primaryInvite->contact, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
    }

    /**
     * @param ClientContact $contact
     * @param array<Address> $cc
     */
    private function dispatchPaymentEmail($contact, array $cc): void
    {
        $mo = new EmailObject();
        $mo->entity_id = $this->payment->id;
        $mo->entity_class = Payment::class;
        $mo->client_id = $this->payment->client_id;
        $mo->client_contact_id = $contact->id;
        $mo->cc = $cc;

        /* Synchronous so the PaymentWasEmailed event fires after the actual send attempt (matches the legacy ->handle() ordering). */
        Email::dispatchSync($mo, $this->company);
    }
}
