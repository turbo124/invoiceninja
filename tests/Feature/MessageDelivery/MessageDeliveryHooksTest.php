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

namespace Tests\Feature\MessageDelivery;

use Tests\TestCase;
use Tests\MockAccountData;
use Illuminate\Database\Eloquent\Model;
use App\Jobs\Mailgun\ProcessMailgunWebhook;
use Illuminate\Support\Facades\Session;
use App\Services\MessageDelivery\MessageDeliveryRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Wiring tests for the projection hooks. The recorder is resolved via the
 * container (app(MessageDeliveryRecorder::class)) so it can be spied — this
 * sidesteps the MultiDB + DatabaseTransactions limitation that prevents reading
 * back rows written inside a MultiDB-switched job.
 */
class MessageDeliveryHooksTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Session::start();
        Model::reguard();
        $this->makeTestData();
    }

    public function testEmailSendRecordsQueued(): void
    {
        $spy = $this->spy(MessageDeliveryRecorder::class);

        $invitation = $this->invoice->invitations->first();

        $mo = new \App\Services\Email\EmailObject();
        $mo->entity_id = $this->invoice->id;
        $mo->entity_class = \App\Models\Invoice::class;
        $mo->invitation_id = $invitation->id;
        $mo->client_id = $this->client->id;
        $mo->email_template_body = 'email_template_invoice';
        $mo->email_template_subject = 'email_subject_invoice';

        (new \App\Services\Email\Email($mo, $this->company))->handle();

        $spy->shouldHaveReceived('recordQueued')
            ->withArgs(fn ($thread_id, $attrs) => is_string($thread_id) && ($attrs['channel'] ?? null) === 'email');
    }

    public function testMailgunDeliveredRecordsInbound(): void
    {
        $spy = $this->spy(MessageDeliveryRecorder::class);

        $payload = [
            'event-data' => [
                'event' => 'delivered',
                'tags' => [$this->company->company_key],
                'message' => ['headers' => ['message-id' => 'mg-test-123']],
                'recipient' => 'a@example.com',
                'timestamp' => time(),
            ],
        ];

        (new ProcessMailgunWebhook($payload))->handle();

        $spy->shouldHaveReceived('recordInbound')
            ->withArgs(fn ($id, $status) => $id === 'mg-test-123' && $status === 'delivered');
    }
}
