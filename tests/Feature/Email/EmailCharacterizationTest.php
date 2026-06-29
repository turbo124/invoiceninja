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

namespace Tests\Feature\Email;

use Tests\TestCase;
use App\Models\Invoice;
use App\Models\Payment;
use Tests\MockAccountData;
use App\Services\Email\Email;
use App\Services\Email\EmailObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Characterization + smoke suite for the unified email-build pipeline.
 *
 * Renders client-directed entities through the live EmailObjectBuilder +
 * EmailMailable path (the legacy engine + TemplateEmail path has been deleted)
 * and asserts the key invariants: the canonical render is non-empty and
 * contains the client identity, the payment path renders, the x-thread header
 * propagates, and EmailMailable handles both path- and file-based attachments.
 *
 * @see \App\Services\Email\EmailObjectBuilder
 */
class EmailCharacterizationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        config(['ninja.testvars.travis' => true]);
    }

    /**
     * Strips genuinely non-deterministic tokens so a render is comparable to
     * itself across invocations (doc-link hashes, signed-route signatures).
     * Invitation keys / numbers / names are NOT stripped — both sides of a
     * same-run comparison share the same fixture instance.
     */
    private function normalize(string $html): string
    {
        $html = preg_replace('/signature=[a-f0-9]{32,}/', 'signature={SIG}', $html);
        $html = preg_replace('#documents/[A-Za-z0-9]{32,}#', 'documents/{HASH}', $html);
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }

    private function disablePdfAttachments(): void
    {
        $settings = $this->company->settings;
        $settings->pdf_email_attachment = false;
        $settings->document_email_attachment = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();

        $cs = $this->client->settings;
        $cs->pdf_email_attachment = false;
        $cs->document_email_attachment = false;
        $this->client->settings = $cs;
        $this->client->saveQuietly();
    }

    private function setStyle(string $style): void
    {
        $settings = $this->company->settings;
        $settings->email_style = $style;
        $this->company->settings = $settings;
        $this->company->saveQuietly();

        $cs = $this->client->settings;
        $cs->email_style = $style;
        $this->client->settings = $cs;
        $this->client->saveQuietly();
    }

    /**
     * Render the canonical (manual Email -> EmailDefaults -> EmailMailable)
     * path WITHOUT sending. This is the EmailDefaults-canonical target the
     * refactored automated path must match.
     */
    private function renderViaManual(EmailObject $mo): string
    {
        $built = (new \App\Services\Email\EmailObjectBuilder())->build($mo, $this->company->fresh());

        $mailable = new \App\Services\Email\EmailMailable($built);

        return $this->normalize($mailable->render());
    }

    private function invoiceEmailObject($invitation): EmailObject
    {
        $mo = new EmailObject();
        $mo->entity_id = $invitation->invoice_id;
        $mo->entity_class = Invoice::class;
        $mo->invitation_id = $invitation->id;
        $mo->client_id = $this->client->id;
        $mo->template = 'email_template_invoice';
        $mo->email_template_body = 'email_template_invoice';
        $mo->email_template_subject = 'email_subject_invoice';

        return $mo;
    }

    public function testInvoiceManualCanonicalRenders(): void
    {
        $this->disablePdfAttachments();
        $this->setStyle('dark');

        $invitation = $this->invoice->invitations->first();

        $html = $this->renderViaManual($this->invoiceEmailObject($invitation));


        $this->assertStringContainsString($this->client->present()->name(), $html);
        $this->assertNotEmpty($html);
    }

    public function testQuoteManualCanonicalRenders(): void
    {
        $this->disablePdfAttachments();
        $this->setStyle('dark');

        $invitation = $this->quote->invitations->first();

        $mo = new EmailObject();
        $mo->entity_id = $invitation->quote_id;
        $mo->entity_class = \App\Models\Quote::class;
        $mo->invitation_id = $invitation->id;
        $mo->client_id = $this->client->id;
        $mo->template = 'email_template_quote';
        $mo->email_template_body = 'email_template_quote';
        $mo->email_template_subject = 'email_subject_quote';

        $html = $this->renderViaManual($mo);

        $this->assertNotEmpty($html);
    }

    public function testCreditManualCanonicalRenders(): void
    {
        $this->disablePdfAttachments();
        $this->setStyle('dark');

        $invitation = $this->credit->invitations->first();

        $mo = new EmailObject();
        $mo->entity_id = $invitation->credit_id;
        $mo->entity_class = \App\Models\Credit::class;
        $mo->invitation_id = $invitation->id;
        $mo->client_id = $this->client->id;
        $mo->template = 'email_template_credit';
        $mo->email_template_body = 'email_template_credit';
        $mo->email_template_subject = 'email_subject_credit';

        $html = $this->renderViaManual($mo);

        $this->assertNotEmpty($html);
    }

    public function testEmailEntityInvoiceDispatchesUnifiedEmail(): void
    {
        \Illuminate\Support\Facades\Bus::fake([Email::class]);

        $invitation = $this->invoice->invitations->first();

        (new \App\Jobs\Entity\EmailEntity($invitation, $invitation->company->db, 'invoice'))->handle();

        \Illuminate\Support\Facades\Bus::assertDispatched(Email::class, function ($job) {
            return $job->email_object->entity_class === Invoice::class
                && $job->email_object->email_template_body === 'email_template_invoice'
                && $job->email_object->invitation_id === $this->invoice->invitations->first()->id;
        });
    }

    public function testEmailPaymentDispatchesUnifiedEmail(): void
    {
        \Illuminate\Support\Facades\Bus::fake([Email::class]);

        (new \App\Jobs\Payment\EmailPayment($this->payment, $this->payment->company, $this->client->contacts()->first()))->handle();

        \Illuminate\Support\Facades\Bus::assertDispatched(Email::class, function ($job) {
            return $job->email_object->entity_class === Payment::class
                && $job->email_object->is_refund === false
                && $job->email_object->entity_id === $this->payment->id;
        });
    }

    public function testEmailMailableHandlesPathAndFileAttachments(): void
    {
        $mo = new EmailObject();
        $mo->documents = [];
        $mo->attachments = [
            ['path' => '/tmp/does-not-need-to-exist.pdf', 'name' => 'doc.pdf', 'mime' => null], // path-based (payment docs)
            ['file' => base64_encode('hello'), 'name' => 'inline.txt'],                          // base64 inline
        ];

        // Must not crash on the path entry (regression: base64_decode on a missing 'file' key).
        $attachments = (new \App\Services\Email\EmailMailable($mo))->attachments();

        $this->assertCount(2, $attachments);
        $this->assertInstanceOf(\Illuminate\Mail\Attachment::class, $attachments[0]);
    }

    public function testThreadIdPropagatesToMessageHeaders(): void
    {
        $this->disablePdfAttachments();
        $this->setStyle('dark');

        $invitation = $this->invoice->invitations->first();

        $mo = $this->invoiceEmailObject($invitation);
        $mo->thread_id = 'thread-abc-123';

        $built = (new \App\Services\Email\EmailObjectBuilder())->build($mo, $this->company->fresh());

        // The x-thread header is what MailSentListener reads to fold the sent transition.
        $this->assertSame('thread-abc-123', $built->headers['x-thread'] ?? null);
        $this->assertArrayHasKey('x-invitation', $built->headers, 'x-invitation must still be present alongside x-thread');
    }

    public function testPaymentReceiptBuilderRenders(): void
    {
        $this->disablePdfAttachments();
        $this->setStyle('dark');

        $contact = $this->client->contacts()->first();

        $mo = new EmailObject();
        $mo->entity_id = $this->payment->id;
        $mo->entity_class = Payment::class;
        $mo->client_id = $this->payment->client_id;
        $mo->client_contact_id = $contact->id;

        $builderHtml = $this->renderViaManual($mo);


        $this->assertNotEmpty($builderHtml);
        $this->assertStringContainsString($this->client->present()->name(), $builderHtml);
    }
}
