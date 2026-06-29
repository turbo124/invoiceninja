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
use Illuminate\Support\Str;
use App\Models\MessageDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\Services\MessageDelivery\MessageDeliveryRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MessageDeliveryRecorderTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private MessageDeliveryRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->recorder = new MessageDeliveryRecorder();
    }

    private function queue(): string
    {
        $thread = (string) Str::ulid();

        $this->recorder->recordQueued($thread, [
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'channel' => 'email',
        ]);

        return $thread;
    }

    public function testRecordQueuedCreatesRow(): void
    {
        $thread = $this->queue();

        $row = MessageDelivery::where('thread_id', $thread)->first();

        $this->assertNotNull($row);
        $this->assertSame('queued', $row->status);
        $this->assertCount(1, $row->events);
    }

    public function testHappyPathAdvancesThroughLattice(): void
    {
        $thread = $this->queue();

        $this->recorder->recordSent($thread, '<msg-abc>');
        $this->recorder->recordInbound('msg-abc', 'delivered');
        $this->recorder->recordInbound('msg-abc', 'opened');

        $row = MessageDelivery::where('thread_id', $thread)->first();

        $this->assertSame('opened', $row->status);
        $this->assertSame('msg-abc', $row->provider_message_id, 'angle brackets must be stripped to match inbound lookup');
        $this->assertCount(4, $row->events, 'every transition is appended to the timeline');
    }

    public function testLateDeliveredDoesNotRegressOpened(): void
    {
        $thread = $this->queue();
        $this->recorder->recordSent($thread, 'msg-xyz');
        $this->recorder->recordInbound('msg-xyz', 'opened');

        // a delivered webhook arriving after the open must not lower the status
        $this->recorder->recordInbound('msg-xyz', 'delivered');

        $row = MessageDelivery::where('thread_id', $thread)->first();

        $this->assertSame('opened', $row->status);
        $this->assertCount(4, $row->events, 'the late event is still recorded in the timeline');
    }

    public function testTerminalNegativeWinsAndSetsRetryable(): void
    {
        $thread = $this->queue();
        $this->recorder->recordSent($thread, 'msg-bounce');
        $this->recorder->recordInbound('msg-bounce', 'bounced', 'hard_bounce', 'mailbox does not exist');

        $row = MessageDelivery::where('thread_id', $thread)->first();

        $this->assertSame('bounced', $row->status);
        $this->assertSame('hard_bounce', $row->reason_code);

        // a subsequent progress event must NOT overwrite a terminal-negative state
        $this->recorder->recordInbound('msg-bounce', 'delivered');
        $row->refresh();
        $this->assertSame('bounced', $row->status);
    }

    public function testPermanentFailureIsTerminal(): void
    {
        $thread = $this->queue();

        $this->recorder->recordFailed(['thread_id' => $thread], 'suppressed_recipient', 'code 406', false);

        $row = MessageDelivery::where('thread_id', $thread)->first();

        $this->assertSame('failed', $row->status);
        $this->assertFalse($row->retryable);
        $this->assertSame('suppressed_recipient', $row->reason_code);
    }

    public function testRetryableFailureIsNotTerminalAndRecovers(): void
    {
        $thread = $this->queue();

        // Transient failure (e.g. Gmail 429) — job will be released & retried.
        $this->recorder->recordFailed(['thread_id' => $thread], 'rate_limited', 'google 429', true);

        $row = MessageDelivery::where('thread_id', $thread)->first();
        $this->assertSame('deferred', $row->status, 'a retryable failure must NOT be terminal');
        $this->assertTrue($row->retryable);

        // The retry succeeds — status must be able to move forward again.
        $this->recorder->recordSent($thread, 'msg-recovered');
        $this->recorder->recordInbound('msg-recovered', 'delivered');

        $row->refresh();
        $this->assertSame('delivered', $row->status, 'a successful retry must supersede the deferred state');
        $this->assertFalse($row->retryable);
        $this->assertNull($row->reason_code, 'the transient failure reason is cleared once it recovers');
    }

    public function testInboundWithoutMatchingRowIsNoop(): void
    {
        $this->assertNull($this->recorder->recordInbound('does-not-exist', 'delivered'));
    }

    private function recipientAttrs(int $target_id): array
    {
        return [
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'channel' => 'email',
            'subject_type' => \App\Models\Invoice::class,
            'subject_id' => $this->invoice->id,
            'target_type' => \App\Models\InvoiceInvitation::class,
            'target_id' => $target_id,
        ];
    }

    public function testResendToSameRecipientPurgesPriorRow(): void
    {
        $attrs = $this->recipientAttrs(555);

        $first = (string) Str::ulid();
        $this->recorder->recordQueued($first, $attrs);
        $this->recorder->recordSent($first, 'm1');
        $this->recorder->recordInbound('m1', 'delivered');

        // A resend to the SAME recipient supersedes the prior delivery row.
        $second = (string) Str::ulid();
        $this->recorder->recordQueued($second, $attrs);

        $rows = MessageDelivery::where('subject_id', $this->invoice->id)->where('target_id', 555)->get();

        $this->assertCount(1, $rows, 'a resend to the same recipient purges the prior row (purge on upsert)');
        $this->assertSame($second, $rows->first()->thread_id);
    }

    public function testDistinctRecipientsForSameSubjectCoexist(): void
    {
        // An invoice emailed to two contacts (two invitations) must keep BOTH rows.
        $this->recorder->recordQueued((string) Str::ulid(), $this->recipientAttrs(101));
        $this->recorder->recordQueued((string) Str::ulid(), $this->recipientAttrs(202));

        $rows = MessageDelivery::where('subject_id', $this->invoice->id)->get();

        $this->assertCount(2, $rows, 'purge must not collapse distinct recipients of the same subject');
    }

    public function testRepeatedOpensDoNotGrowTimelineUnbounded(): void
    {
        $thread = $this->queue();
        $this->recorder->recordSent($thread, 'msg-open');
        $this->recorder->recordInbound('msg-open', 'delivered');

        // 5 repeated open callbacks (newsletters get opened many times)
        for ($i = 0; $i < 5; $i++) {
            $this->recorder->recordInbound('msg-open', 'opened');
        }

        $row = MessageDelivery::where('thread_id', $thread)->first();

        $this->assertSame('opened', $row->status);
        // queued, sent, delivered, opened — repeated opens collapse to one entry
        $this->assertCount(4, $row->events, 'consecutive duplicate-status events must collapse');
    }

    public function testPreflightSuppressionFoldsToTerminalState(): void
    {
        $thread = $this->queue();

        $this->recorder->fold(['thread_id' => $thread], 'suppressed', [
            'reason_code' => 'preflight_blocked',
            'retryable' => false,
        ]);

        $row = MessageDelivery::where('thread_id', $thread)->first();

        $this->assertSame('suppressed', $row->status, 'a blocked send must reach a terminal state, not stay queued');
        $this->assertSame('preflight_blocked', $row->reason_code);
    }

    public function testRecordQueuedIsIdempotentOnRetry(): void
    {
        $thread = (string) Str::ulid();

        $attrs = [
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'channel' => 'email',
        ];

        // Simulate a job retry re-running recordQueued with the same (stable) thread_id.
        $this->recorder->recordQueued($thread, $attrs);
        $this->recorder->recordSent($thread, 'msg-idem');
        $this->recorder->recordQueued($thread, $attrs);

        $rows = MessageDelivery::where('thread_id', $thread)->get();

        $this->assertCount(1, $rows, 'retry must not create a duplicate delivery row');
        // The second recordQueued must not reset progress already made.
        $this->assertSame('sent', $rows->first()->status);
    }
}
