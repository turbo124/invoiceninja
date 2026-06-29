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

namespace App\Jobs\Entity;

use App\Libraries\MultiDB;
use App\Services\Email\Email;
use App\Models\QuoteInvitation;
use App\Services\Email\EmailObject;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\RecurringInvoiceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/*Multi Mailer implemented*/

class EmailEntity implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $invitation; //The entity invitation

    public $company; //The company

    public $settings; //The settings object

    public $entity_string; //The entity string ie. invoice, quote, credit

    public $reminder_template; //The base template we are using

    public $entity; //The entity object

    public $template_data; //The data to be merged into the template

    public $tries = 1;

    public string $db;

    /**
     * EmailEntity constructor.
     *
     * @param mixed   $invitation
     * @param string  $db
     * @param ?string $reminder_template
     * @param ?array  $template_data
     */
    public function __construct($invitation, string $db, ?string $reminder_template = null, $template_data = null)
    {
        $this->db = $db;

        $this->invitation = $invitation;

        $this->entity_string = $this->resolveEntityString();

        $this->entity = $invitation->{$this->entity_string};

        $this->settings = $invitation->contact->client->getMergedSettings();

        $this->reminder_template = $reminder_template ?: $this->entity->calculateTemplate($this->entity_string);

        $this->template_data = $template_data;
    }

    /**
     * Execute the job. Builds the EmailObject descriptor and hands it to the
     * unified Email pipeline (EmailObjectBuilder + EmailDefaults). Rendering,
     * attachments, CC-only contacts and transport are all owned downstream.
     */
    public function handle(): void
    {
        MultiDB::setDB($this->db);

        /* Don't fire emails if the company is disabled */
        if ($this->invitation->company->is_disabled) {
            return;
        }

        /* Mark entity sent */
        $this->entity->service()->markSent()->save();

        $reminder = $this->reminder_template === 'endless_reminder' ? 'reminder_endless' : $this->reminder_template;

        $body_key = 'email_template_' . $reminder;
        $subject_key = 'email_subject_' . $reminder;

        /* Preserve the quote-specific reminder1 template keys */
        if ($this->entity_string === 'quote' && $this->reminder_template === 'reminder1') {
            $body_key = 'email_quote_template_reminder1';
            $subject_key = 'email_quote_subject_reminder1';
        }

        $mo = new EmailObject();
        $mo->entity_id = $this->entity->id;
        $mo->entity_class = get_class($this->entity);
        $mo->invitation_id = $this->invitation->id;
        $mo->client_id = $this->invitation->contact->client_id ?? null;
        $mo->vendor_id = $this->invitation->contact->vendor_id ?? null;
        $mo->reminder_template = $this->reminder_template;
        $mo->template = $body_key;
        $mo->email_template_body = $body_key;
        $mo->email_template_subject = $subject_key;
        $mo->template_data = $this->template_data;

        Email::dispatch($mo, $this->invitation->company);

        $this->invitation = null;
        $this->company = null;
        $this->entity_string = null;
        $this->entity = null;
        $this->settings = null;
        $this->reminder_template = null;
        $this->template_data = null;
    }

    private function resolveEntityString(): string
    {
        if ($this->invitation instanceof InvoiceInvitation) {
            return 'invoice';
        } elseif ($this->invitation instanceof QuoteInvitation) {
            return 'quote';
        } elseif ($this->invitation instanceof CreditInvitation) {
            return 'credit';
        } elseif ($this->invitation instanceof RecurringInvoiceInvitation) {
            return 'recurring_invoice';
        }

        return '';
    }

    public function failed($e)
    {
        nlog("EmailEntity");
        nlog($e->getMessage());
    }
}
