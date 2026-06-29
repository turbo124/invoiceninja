<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('thread_id')->unique();              // per-send addressing key, carried via x-thread header

            $table->unsignedInteger('company_id');
            $table->unsignedInteger('client_id')->nullable();

            $table->string('channel', 16);                    // email | webhook

            // What it's about (Invoice|Quote|Credit|PurchaseOrder|Payment) and where it went (Invitation|Webhook)
            $table->nullableMorphs('subject');
            $table->nullableMorphs('target');

            // Folded current state (status lattice owned by the recorder)
            $table->string('status', 30);                     // queued|sending|sent|delivered|opened|deferred|bounced|complained|failed|suppressed
            $table->string('reason_code', 50)->nullable();
            $table->text('reason_detail')->nullable();

            // Inbound correlation (ESP MessageID) — the join key armed at "sent"
            $table->string('provider_message_id')->nullable();

            $table->boolean('retryable')->default(false);

            // Replay descriptor + folded timeline
            $table->json('payload_ref')->nullable();
            $table->json('events');

            $table->timestamps();

            $table->index(['company_id', 'channel', 'status']);
            $table->index(['company_id', 'subject_type', 'subject_id']);
            $table->index(['company_id', 'client_id']);
            $table->index('provider_message_id');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_deliveries');
    }
};
