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

namespace App\Services\Email;

use App\Models\User;
use App\Models\Client;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Payment;
use App\Models\ClientContact;
use App\Models\VendorContact;
use App\Services\Email\Builders\PaymentEmailStrategy;
use App\Services\Email\Builders\RendersEmailEntity;
use App\Services\Email\Builders\ReceivableEmailStrategy;

/**
 * The single service responsible for turning a lightweight EmailObject
 * descriptor into a fully-hydrated EmailObject, ready to be rendered by
 * EmailMailable. Email.php consumes the result and is responsible only for
 * transport (mailer selection, quota, send, retry).
 *
 * Flow: hydrate models -> per-entity strategy (the only entity switch) ->
 * EmailDefaults generic chrome.
 */
class EmailObjectBuilder
{
    public function build(EmailObject $email_object, Company $company): EmailObject
    {
        $this->hydrateModels($email_object, $company);

        $this->strategyFor($email_object)->build($email_object, $company);

        (new EmailDefaults($email_object, $company))->run();

        return $email_object;
    }

    private function strategyFor(EmailObject $email_object): RendersEmailEntity
    {
        return match (true) {
            $email_object->entity instanceof Payment => new PaymentEmailStrategy(),
            default => new ReceivableEmailStrategy(),
        };
    }

    /**
     * Loads the entity graph and derived settings onto the EmailObject.
     * (Previously Email::initModels().)
     */
    private function hydrateModels(EmailObject $email_object, Company $company): void
    {
        $email_object->entity_id ? $email_object->entity = $email_object->entity_class::withTrashed()->find($email_object->entity_id) : $email_object->entity = null;

        $email_object->invitation_id ? $email_object->invitation = $email_object->entity->invitations()->where('id', $email_object->invitation_id)->first() : $email_object->invitation = null; //@phpstan-ignore-line

        $email_object->invitation_id ? $email_object->contact = $email_object->invitation->contact : $email_object->contact = null;

        $email_object->client_id ? $email_object->client = Client::withTrashed()->find($email_object->client_id) : $email_object->client = null;

        $email_object->vendor_id ? $email_object->vendor = Vendor::withTrashed()->find($email_object->vendor_id) : $email_object->vendor = null;

        if (! $email_object->contact) {
            $email_object->vendor_contact_id ? $email_object->contact = VendorContact::withTrashed()->find($email_object->vendor_contact_id) : null;

            $email_object->client_contact_id ? $email_object->contact = ClientContact::withTrashed()->find($email_object->client_contact_id) : null;
        }

        $email_object->user_id ? $email_object->user = User::withTrashed()->find($email_object->user_id) : $email_object->user = $company->owner();

        $email_object->company_key = $company->company_key;

        $email_object->company = $company;

        if ($email_object->client_id) {
            $email_object->settings = $email_object->client->getMergedSettings();
        } elseif ($email_object->vendor_id) {
            $email_object->settings = $email_object->vendor->getMergedSettings();
        } else {
            $email_object->settings = $company->settings;
        }

        $email_object->whitelabel = $company->account->isPaid() ? true : false;

        $email_object->logo = $email_object->settings->company_logo;

        $email_object->signature = $email_object->settings->email_signature;

        $email_object->invitation_key = $email_object->invitation ? $email_object->invitation->key : null;
    }
}
