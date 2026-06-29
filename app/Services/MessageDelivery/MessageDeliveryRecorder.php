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

namespace App\Services\MessageDelivery;

use App\Models\MessageDelivery;

/**
 * Folds the delivery lifecycle of a single send (email or webhook) into one
 * message_deliveries row. Owns the status lattice so every channel/provider
 * advances consistently: progress states only move forward; terminal-negative
 * states always win. Every raw event is appended to the events timeline
 * regardless of whether it advances the headline status.
 */
class MessageDeliveryRecorder
{
    /** Monotonic progress rank — a status only advances if its rank is higher. */
    private const RANK = [
        'queued' => 0,
        'deferred' => 1, // a retryable send failure — below sent, so a successful retry supersedes it
        'sending' => 1,
        'sent' => 2,
        'delivered' => 3,
        'opened' => 4,
    ];

    /** These always win and mark the row for triage. */
    private const TERMINAL_NEGATIVE = ['bounced', 'complained', 'failed', 'suppressed'];

    /** Upper bound on the timeline length so recurring callbacks can't grow the row unbounded. */
    private const MAX_EVENTS = 50;

    /**
     * Create the delivery row at enqueue time and return it.
     *
     * Idempotent on thread_id: a job retry (which re-runs handle()) reuses the
     * existing row rather than creating a duplicate, since thread_id is minted at
     * dispatch and serialized with the job.
     *
     * On a genuinely new send, prior superseded rows for the same subject are
     * purged — the projection persists current delivery state per subject, not
     * unbounded per-send history.
     *
     * @param array{company_id:int, client_id?:int|null, channel:string, subject_type?:string|null, subject_id?:int|null, target_type?:string|null, target_id?:int|null, payload_ref?:array|null} $attrs
     */
    public function recordQueued(string $thread_id, array $attrs): MessageDelivery
    {
        $row = MessageDelivery::firstOrCreate(
            ['thread_id' => $thread_id],
            array_merge([
                'status' => 'queued',
                'retryable' => false,
                'events' => [$this->event('queued')],
            ], $attrs)
        );

        if ($row->wasRecentlyCreated) {
            $this->purgeSuperseded($row);
        }

        return $row;
    }

    /**
     * Purge a prior delivery row once a new send to the SAME recipient supersedes
     * it, bounding the table to the latest delivery per (subject, target).
     *
     * Scoped by target as well as subject so distinct recipients are preserved:
     * an invoice emailed to two contacts (two invitations) keeps both rows, and a
     * webhook to two subscriptions keeps both — only a resend to the same target
     * supersedes. Requires both subject and target; otherwise we cannot identify a
     * unique recipient to supersede, so nothing is purged.
     */
    private function purgeSuperseded(MessageDelivery $row): void
    {
        if (! $row->subject_type || ! $row->subject_id || ! $row->target_type || ! $row->target_id) {
            return;
        }

        MessageDelivery::query()
            ->where('company_id', $row->company_id)
            ->where('subject_type', $row->subject_type)
            ->where('subject_id', $row->subject_id)
            ->where('target_type', $row->target_type)
            ->where('target_id', $row->target_id)
            ->where('id', '!=', $row->id)
            ->delete();
    }

    public function recordSent(string $thread_id, ?string $provider_message_id): ?MessageDelivery
    {
        return $this->fold(['thread_id' => $thread_id], 'sent', [
            'provider_message_id' => $this->normalizeId($provider_message_id),
        ]);
    }

    /** Inbound ESP callbacks correlate on the provider message id armed at sent. */
    public function recordInbound(string $provider_message_id, string $status, ?string $reason_code = null, ?string $reason_detail = null, array $raw = []): ?MessageDelivery
    {
        return $this->fold(['provider_message_id' => $this->normalizeId($provider_message_id)], $status, [
            'reason_code' => $reason_code,
            'reason_detail' => $reason_detail,
            'raw' => $raw,
        ]);
    }

    /**
     * Retryable failures fold to the NON-terminal `deferred` state so a later
     * successful retry can still advance to sent/delivered. Only exhausted or
     * permanent failures fold to terminal `failed`.
     *
     * @param array{thread_id?:string, provider_message_id?:string} $addressing
     */
    public function recordFailed(array $addressing, string $reason_code, string $reason_detail, bool $retryable): ?MessageDelivery
    {
        return $this->fold($addressing, $retryable ? 'deferred' : 'failed', [
            'reason_code' => $reason_code,
            'reason_detail' => $reason_detail,
            'retryable' => $retryable,
        ]);
    }

    /**
     * Apply one transition to the addressed row: append to the timeline, then
     * advance the headline status per the lattice.
     *
     * @param array{thread_id?:string, provider_message_id?:string} $addressing
     */
    public function fold(array $addressing, string $status, array $attrs = []): ?MessageDelivery
    {
        // Lock the row for the read-modify-write so concurrent transitions
        // (e.g. delivered + opened arriving together) cannot clobber the events
        // timeline or mis-fold the status.
        return MessageDelivery::query()->getConnection()->transaction(function () use ($addressing, $status, $attrs) {
            $row = $this->locate($addressing, true);

            if (! $row) {
                return null;
            }

            $events = $row->events ?? [];
            $code = $attrs['reason_code'] ?? null;

            // Collapse consecutive duplicate (status, reason) events so recurring
            // callbacks (repeated opens/clicks) cannot grow the timeline — but keyed
            // on reason too, so distinct deferral/bounce reasons are still recorded.
            $last = end($events) ?: null;
            $is_duplicate = $last && ($last['status'] ?? null) === $status && ($last['code'] ?? null) === $code;

            if (! $is_duplicate) {
                $events[] = $this->event($status, $code, $attrs['raw'] ?? null);

                // Hard cap so non-consecutive recurring events can't grow the JSON unbounded.
                if (count($events) > self::MAX_EVENTS) {
                    $events = array_slice($events, -self::MAX_EVENTS);
                }

                $row->events = $events;
            }

            if (array_key_exists('provider_message_id', $attrs) && $attrs['provider_message_id']) {
                $row->provider_message_id = $attrs['provider_message_id'];
            }

            if ($this->isTerminalNegative($status)) {
                $row->status = $status;
                $row->retryable = $attrs['retryable'] ?? false;
                $row->reason_code = $attrs['reason_code'] ?? $row->reason_code;
                $row->reason_detail = $attrs['reason_detail'] ?? $row->reason_detail;
            } elseif (! $this->isTerminalNegative($row->status) && $this->rank($status) > $this->rank($row->status)) {
                $row->status = $status;
                // Carry retryable/reason for a deferred attempt; clear them when
                // advancing to a clean progress state (sent/delivered/opened).
                $row->retryable = $attrs['retryable'] ?? false;
                $row->reason_code = $attrs['reason_code'] ?? null;
                $row->reason_detail = $attrs['reason_detail'] ?? null;
            }

            $row->save();

            return $row;
        });
    }

    private function locate(array $addressing, bool $lock = false): ?MessageDelivery
    {
        // thread_id is unique → lockForUpdate takes a single-row lock, safe.
        if (isset($addressing['thread_id'])) {
            $query = MessageDelivery::query()->where('thread_id', $addressing['thread_id']);

            return $lock ? $query->lockForUpdate()->first() : $query->first();
        }

        // provider_message_id is a NON-unique secondary index; locking it directly
        // would take InnoDB range/gap locks (contention/deadlock under ESP callback
        // bursts). Resolve the id unlocked, then lock the single row by primary key.
        if (isset($addressing['provider_message_id'])) {
            $id = MessageDelivery::query()
                ->where('provider_message_id', $addressing['provider_message_id'])
                ->orderByDesc('id')
                ->value('id');

            if (! $id) {
                return null;
            }

            $query = MessageDelivery::query()->whereKey($id);

            return $lock ? $query->lockForUpdate()->first() : $query->first();
        }

        return null;
    }

    private function rank(string $status): int
    {
        return self::RANK[$status] ?? -1;
    }

    private function isTerminalNegative(string $status): bool
    {
        return in_array($status, self::TERMINAL_NEGATIVE, true);
    }

    private function event(string $status, ?string $code = null, $raw = null): array
    {
        return array_filter([
            'status' => $status,
            'code' => $code,
            'raw' => $raw,
            'at' => now()->toDateTimeString(),
        ], fn ($v) => $v !== null);
    }

    /** ESP message ids are stored stripped of angle brackets (matches MailSentListener). */
    private function normalizeId(?string $id): ?string
    {
        return $id ? str_replace(['<', '>'], '', $id) : null;
    }
}
