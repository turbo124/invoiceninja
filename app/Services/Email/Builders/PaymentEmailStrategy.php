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

namespace App\Services\Email\Builders;

use App\Utils\Ninja;
use App\Models\Design;
use App\Models\Account;
use App\Models\Company;
use App\Models\Payment;
use Illuminate\Support\Str;
use App\Utils\PaymentHtmlEngine;
use App\Services\Pdf\Markdown;
use App\Jobs\Entity\CreateRawPdf;
use App\Utils\Traits\MakesHash;
use App\Services\Email\EmailObject;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
use App\Services\Template\TemplateAction;
use App\DataMapper\EmailTemplateDefaults;

/**
 * Payments are the one entity the generic EmailDefaults pipeline cannot
 * assemble: partial/full template selection, is_refund design swap, and the
 * receipt/refund-or-invoice-bundle attachments. This strategy owns that
 * assembly directly (variables come from the single PaymentHtmlEngine
 * provider) and flags the EmailObject prebuilt so EmailDefaults skips
 * re-assembly.
 */
class PaymentEmailStrategy implements RendersEmailEntity
{
    use MakesHash;

    private int $max_attachment_size = 2800000;

    public function build(EmailObject $email_object, Company $company): void
    {
        /** @var Payment $payment */
        $payment = $email_object->entity;
        $contact = $email_object->contact;
        $client = $payment->client;

        /* Borrow an invoice invitation so the x-invitation header (open tracking) is preserved. */
        if ($payment->invoices->count() >= 1) {
            /** @var \App\Models\InvoiceInvitation|null $invitation */
            $invitation = $payment->invoices->first()->invitations()->where('client_contact_id', $contact?->id)->first()
                ?: $payment->invoices->first()->invitations()->first();

            if ($invitation) {
                $email_object->invitation = $invitation;
                $email_object->invitation_key = $invitation->key;
            }
        }

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($client->locale());
        $t->replace(Ninja::transformTranslations($client->getMergedSettings()));

        $variables = (new PaymentHtmlEngine($payment, $contact))->makeValues();

        /* Partial vs full payment template. */
        $partial = $payment->invoices->contains(fn ($invoice) => $invoice->balance > 0);
        $body_key = $partial ? 'email_template_payment_partial' : 'email_template_payment';
        $subject_key = $partial ? 'email_subject_payment_partial' : 'email_subject_payment';

        $body_template = $this->resolveTemplate($email_object->template_data, 'body', $client, $body_key);
        $subject_template = $this->resolveTemplate($email_object->template_data, 'subject', $client, $subject_key);

        $subject = $this->substitute($subject_template, $variables, false);
        $body = $this->substitute($body_template, $variables, true);
        $text_body = $this->substitute($this->textPlaceholders($body_template), $variables, true);

        $links = [];
        $attachments = $this->buildAttachments($payment, $client, $company, $email_object->is_refund, $links);

        if ($client->getSetting('email_style') === 'custom') {
            $body = str_replace('$body', $body . $this->customLinks($links), $client->getSetting('email_style_custom'));
        } else {
            $body = Markdown::parse($body);
        }

        $email_object->subject = str_replace('<br>', '', $subject);
        $email_object->body = $body;
        $email_object->text_body = $text_body;
        $email_object->variables = $variables;
        $email_object->attachments = $attachments;
        $email_object->links = $links;
        $email_object->prebuilt = true;
    }

    private function resolveTemplate(?array $template_data, string $key, $client, string $setting_key): string
    {
        if (is_array($template_data) && array_key_exists($key, $template_data) && strlen($template_data[$key]) > 0) {
            return $template_data[$key];
        }

        if (strlen($client->getSetting($setting_key)) > 0) {
            return $client->getSetting($setting_key);
        }

        return EmailTemplateDefaults::getDefaultTemplate($setting_key, $client->locale());
    }

    /** Mirrors BaseEmailEngine: subject substituted once, body twice. */
    private function substitute(string $template, array $variables, bool $twice): string
    {
        if (empty($variables)) {
            return $template;
        }

        $template = str_replace(array_keys($variables), array_values($variables), $template);

        if ($twice) {
            $template = str_replace(array_keys($variables), array_values($variables), $template);
        }

        return $template;
    }

    /** Mirrors BaseEmailEngine::setTextBody — view buttons collapse to the plain view url. */
    private function textPlaceholders(string $template): string
    {
        return str_replace(['$paymentLink', '$viewButton', '$view_button', '$viewLink', '$view_link'], "\r\n\r\n" . '$view_url' . "\r\n", $template);
    }

    private function buildAttachments(Payment $payment, $client, Company $company, bool $is_refund, array &$links): array
    {
        $attachments = [];

        if ($client->getSetting('pdf_email_attachment') === false || ! $company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {
            return $attachments;
        }

        $template_in_use = false;

        if ($is_refund && Design::where('id', $this->decodePrimaryKey($client->getSetting('payment_refund_design_id')))->where('is_template', true)->exists()) {
            $pdf = (new TemplateAction([$payment->hashed_id], $client->getSetting('payment_refund_design_id'), Payment::class, $payment->user_id, $payment->company, $payment->company->db, 'nohash', false))->handle();
            $attachments[] = ['file' => base64_encode($pdf), 'name' => str_replace(' ', '_', ctrans('texts.payment_refund_receipt', ['number' => $payment->number]) . '.pdf')];
            $template_in_use = true;
        } elseif (! $is_refund && Design::where('id', $this->decodePrimaryKey($client->getSetting('payment_receipt_design_id')))->where('is_template', true)->exists()) {
            $pdf = (new TemplateAction([$payment->hashed_id], $client->getSetting('payment_receipt_design_id'), Payment::class, $payment->user_id, $payment->company, $payment->company->db, 'nohash', false))->handle();
            $attachments[] = ['file' => base64_encode($pdf), 'name' => str_replace(' ', '_', ctrans('texts.payment_receipt', ['number' => $payment->number]) . '.pdf')];
            $template_in_use = true;
        }

        $payment->invoices->each(function ($invoice) use (&$attachments, &$links, $template_in_use, $client, $payment) {
            /** @var \App\Models\Invoice $invoice */
            if (! $template_in_use) {
                $pdf = (new CreateRawPdf($invoice->invitations->first()))->handle();
                $attachments[] = ['file' => base64_encode($pdf), 'name' => $invoice->numberFormatter() . '.pdf'];
            }

            if ($client->getSetting('document_email_attachment') !== false) {
                $invoice->documents()->where('is_public', true)->cursor()->each(function ($document) use (&$attachments, &$links, $payment) {
                    /** @var \App\Models\Document $document */
                    if ($document->size > $this->max_attachment_size) {
                        $hash = Str::random(64);
                        Cache::put($hash, ['db' => $payment->company->db, 'doc_hash' => $document->hash], now()->addDays(7));
                        $links[] = "<a class='doc_links' href='" . URL::signedRoute('documents.hashed_download', ['hash' => $hash]) . "'>" . $document->name . "</a>";
                    } else {
                        $attachments[] = ['path' => $document->filePath(), 'name' => $document->name, 'mime' => null];
                    }
                });
            }
        });

        return $attachments;
    }

    private function customLinks(array $links): string
    {
        if (count($links) === 0) {
            return '';
        }

        $out = '<ul><li>' . ctrans('texts.download_files') . '</li>';

        foreach ($links as $link) {
            $out .= "<li>{$link}</li>";
        }

        return $out . '</ul>';
    }
}
