<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_entity', 50);
            $table->string('trigger_event', 50);
            $table->text('trigger_conditions')->nullable();
            $table->text('steps');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_template')->default(false);
            $table->unsignedInteger('runs_count')->default(0);
            $table->timestamp('last_run_at', 6)->nullable();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'trigger_entity', 'trigger_event', 'is_active'], 'idx_workflows_trigger');
            $table->index(['company_id', 'deleted_at'], 'idx_workflows_deleted');
        });

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('workflow_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->string('entity_type', 100);
            $table->unsignedInteger('entity_id');
            $table->string('current_step_id', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('waiting_for', 100)->nullable();
            $table->timestamp('waiting_since', 6)->nullable();
            $table->timestamp('wait_until', 6)->nullable();
            $table->text('context')->nullable();
            $table->text('step_history')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
            $table->index(['company_id', 'status'], 'idx_runs_status');
            $table->index(['entity_type', 'entity_id'], 'idx_runs_entity');
            $table->index(['status', 'waiting_for'], 'idx_runs_waiting');
            $table->index(['status', 'wait_until'], 'idx_runs_timer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflows');
    }
};
