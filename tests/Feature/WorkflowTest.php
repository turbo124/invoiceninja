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

namespace Tests\Feature;

use App\Factory\WorkflowRunFactory;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Services\Workflow\ContextResolver;
use App\Services\Workflow\OperationFailureType;
use App\Services\Workflow\OperationRegistry;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\WorkflowOperationException;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        Model::reguard();
    }

    // ==========================================
    // API CRUD Tests
    // ==========================================

    public function testWorkflowStore()
    {
        $data = [
            'name' => 'Test Workflow',
            'trigger_entity' => 'invoice',
            'trigger_event' => 'created',
            'steps' => [
                ['id' => 'step_1', 'name' => 'End', 'type' => 'end', 'end_status' => 'completed', 'position' => ['x' => 0, 'y' => 0]],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/workflows', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals('Test Workflow', $arr['data']['name']);
        $this->assertEquals('invoice', $arr['data']['trigger_entity']);
        $this->assertFalse($arr['data']['is_deleted']);
    }

    public function testWorkflowUpdate()
    {
        $workflow = $this->createTestWorkflow();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/workflows/{$workflow->hashed_id}", [
            'name' => 'Updated Workflow',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Workflow', $response->json()['data']['name']);
    }

    public function testWorkflowIndex()
    {
        $this->createTestWorkflow();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/workflows');

        $response->assertStatus(200);
    }

    public function testWorkflowShow()
    {
        $workflow = $this->createTestWorkflow();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson("/api/v1/workflows/{$workflow->hashed_id}");

        $response->assertStatus(200);
    }

    public function testWorkflowDelete()
    {
        $workflow = $this->createTestWorkflow();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->deleteJson("/api/v1/workflows/{$workflow->hashed_id}");

        $response->assertStatus(200);
    }

    public function testWorkflowBulkArchiveRestore()
    {
        $workflow = $this->createTestWorkflow();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/workflows/bulk', [
            'action' => 'archive',
            'ids' => [$workflow->hashed_id],
        ]);

        $response->assertStatus(200);
        $this->assertTrue($workflow->fresh()->trashed());
    }

    // ==========================================
    // Metadata Endpoints
    // ==========================================

    public function testMetadataTriggersEndpoint()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/workflows/metadata/triggers');

        $response->assertStatus(200);
        $data = $response->json()['data'];

        $entityNames = array_column($data, 'entity');
        $this->assertContains('invoice', $entityNames);
        $this->assertContains('quote', $entityNames);
        $this->assertContains('credit', $entityNames);
        $this->assertContains('recurring_invoice', $entityNames);
        $this->assertContains('purchase_order', $entityNames);
        $this->assertContains('vendor', $entityNames);
    }

    public function testMetadataActionsEndpoint()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/workflows/metadata/actions');

        $response->assertStatus(200);
        $data = $response->json()['data'];

        $types = array_column($data, 'type');
        $this->assertContains('entity_operation', $types);
        $this->assertContains('lifecycle_operation', $types);
        $this->assertContains('send_email', $types);
        $this->assertContains('create_task', $types);
        $this->assertNotContains('apply_payment', $types);
        $this->assertNotContains('clone_to', $types);
        $this->assertNotContains('update_field', $types);
    }

    public function testMetadataOperationsEndpoint()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/workflows/metadata/operations');

        $response->assertStatus(200);
        $data = $response->json()['data'];

        $this->assertArrayHasKey('invoice', $data);
        $this->assertArrayHasKey('status', $data['invoice']);
        $this->assertArrayHasKey('lifecycle', $data['invoice']);
    }

    public function testMetadataFieldsEndpoint()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/workflows/metadata/fields');

        $response->assertStatus(200);
    }

    // ==========================================
    // Operation Registry Tests
    // ==========================================

    public function testOperationRegistryGet()
    {
        $op = OperationRegistry::get('invoice', 'mark_sent');
        $this->assertNotNull($op);
        $this->assertEquals('Mark Sent', $op['label']);
        $this->assertEquals('markSent', $op['method']);
        $this->assertNotNull($op['guard']);
    }

    public function testOperationRegistryGetUnknown()
    {
        $op = OperationRegistry::get('invoice', 'nonexistent');
        $this->assertNull($op);
    }

    public function testOperationRegistryLifecycle()
    {
        $op = OperationRegistry::getLifecycle('archive');
        $this->assertNotNull($op);
        $this->assertEquals('Archive', $op['label']);
    }

    public function testOperationRegistryForEntity()
    {
        $grouped = OperationRegistry::forEntity('invoice');
        $this->assertArrayHasKey('status', $grouped);
        $this->assertArrayHasKey('billing', $grouped);
        $this->assertArrayHasKey('lifecycle', $grouped);
    }

    // ==========================================
    // WorkflowEngine Core Execution Tests
    // ==========================================

    public function testSimpleEndWorkflow()
    {
        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'end', 'name' => 'End', 'type' => 'end', 'end_status' => 'completed'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->fresh()->status);
        $this->assertNotNull($run->fresh()->completed_at);
    }

    public function testEntityOperationMarkSent()
    {
        // Ensure invoice is in draft state
        $this->invoice->status_id = Invoice::STATUS_DRAFT;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                [
                    'id' => 'mark_sent',
                    'name' => 'Mark Sent',
                    'type' => 'action',
                    'action' => 'entity_operation',
                    'params' => ['entity_ref' => '$trigger', 'operation' => 'mark_sent'],
                    'on_guard_fail' => 'skip',
                    'on_error' => 'stop',
                ],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);

        // Verify the invoice was actually marked sent
        $this->assertEquals(Invoice::STATUS_SENT, $this->invoice->fresh()->status_id);
    }

    public function testGuardFailSkip()
    {
        // Set invoice to already-sent state so guard fails
        $this->invoice->status_id = Invoice::STATUS_SENT;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                [
                    'id' => 'mark_sent',
                    'name' => 'Mark Sent',
                    'type' => 'action',
                    'action' => 'entity_operation',
                    'params' => ['entity_ref' => '$trigger', 'operation' => 'mark_sent'],
                    'on_guard_fail' => 'skip', // Should skip, not fail
                ],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();
        // Should complete successfully even though guard failed — it was skipped
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);

        // Check step history has a 'skipped' entry
        $skipped = collect($run->step_history)->where('status', 'skipped')->first();
        $this->assertNotNull($skipped);
        $this->assertEquals('mark_sent', $skipped['step_id']);
    }

    public function testGuardFailStop()
    {
        // Set invoice to already-sent state
        $this->invoice->status_id = Invoice::STATUS_SENT;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                [
                    'id' => 'mark_sent',
                    'name' => 'Mark Sent',
                    'type' => 'action',
                    'action' => 'entity_operation',
                    'params' => ['entity_ref' => '$trigger', 'operation' => 'mark_sent'],
                    'on_guard_fail' => 'stop', // Should stop the run
                ],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_FAILED, $run->status);
    }

    public function testGuardFailGotoStep()
    {
        // Set invoice to already-sent state
        $this->invoice->status_id = Invoice::STATUS_SENT;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                [
                    'id' => 'mark_sent',
                    'name' => 'Mark Sent',
                    'type' => 'action',
                    'action' => 'entity_operation',
                    'params' => ['entity_ref' => '$trigger', 'operation' => 'mark_sent'],
                    'on_guard_fail' => 'already_sent_handler',
                ],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
                ['id' => 'already_sent_handler', 'name' => 'Already Sent', 'type' => 'end', 'end_status' => 'already_sent'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);
    }

    public function testBranchEvaluation()
    {
        $this->invoice->amount = 15000;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                [
                    'id' => 'branch',
                    'name' => 'Check amount',
                    'type' => 'branch',
                    'conditions' => [
                        [
                            'label' => 'High value',
                            'if' => ['field' => '$trigger.amount', 'operator' => '>', 'value' => 10000],
                            'goto' => 'high_value_end',
                        ],
                    ],
                    'default_next' => 'standard_end',
                ],
                ['id' => 'standard_end', 'name' => 'Standard', 'type' => 'end', 'end_status' => 'standard'],
                ['id' => 'high_value_end', 'name' => 'High Value', 'type' => 'end', 'end_status' => 'high_value'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);

        // Verify the branch took the 'high_value' path
        $branchEntry = collect($run->step_history)->where('step_id', 'branch')->where('status', 'completed')->first();
        $this->assertEquals('High value', $branchEntry['result']['branch_taken']);
    }

    public function testBranchDefaultPath()
    {
        $this->invoice->amount = 500;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                [
                    'id' => 'branch',
                    'name' => 'Check amount',
                    'type' => 'branch',
                    'conditions' => [
                        [
                            'label' => 'High value',
                            'if' => ['field' => '$trigger.amount', 'operator' => '>', 'value' => 10000],
                            'goto' => 'high_value_end',
                        ],
                    ],
                    'default_next' => 'standard_end',
                ],
                ['id' => 'standard_end', 'name' => 'Standard', 'type' => 'end', 'end_status' => 'standard'],
                ['id' => 'high_value_end', 'name' => 'High Value', 'type' => 'end', 'end_status' => 'high_value'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);

        $branchEntry = collect($run->step_history)->where('step_id', 'branch')->where('status', 'completed')->first();
        $this->assertEquals('default', $branchEntry['result']['branch_taken']);
    }

    public function testWaitDelay()
    {
        // Set due date in the future so the wait parks
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait 7 days after due', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => 7],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);
        $this->assertEquals('__timer__', $run->waiting_for);
        $this->assertNotNull($run->wait_until);
    }

    public function testWaitForEvent()
    {
        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'quote',
            'trigger_event' => 'sent',
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait for approval', 'type' => 'wait_for_event', 'event' => 'quote.approved|quote.rejected', 'timeout_days' => 3, 'on_timeout' => 'timeout_end'],
                ['id' => 'end', 'name' => 'Approved', 'type' => 'end'],
                ['id' => 'timeout_end', 'name' => 'Timed out', 'type' => 'end', 'end_status' => 'timed_out'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->quote, $this->company);

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);
        $this->assertStringContainsString('quote.approved', $run->waiting_for);
    }

    public function testSatisfiedWhenSkipsWait()
    {
        // Set quote to already approved
        $this->quote->status_id = Quote::STATUS_APPROVED;
        $this->quote->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'quote',
            'trigger_event' => 'sent',
            'steps' => [
                [
                    'id' => 'wait',
                    'name' => 'Wait for approval',
                    'type' => 'wait_for_event',
                    'event' => 'quote.approved',
                    'satisfied_when' => [
                        'field' => '$trigger.status_id',
                        'operator' => '=',
                        'value' => Quote::STATUS_APPROVED,
                    ],
                    'timeout_days' => 3,
                ],
                ['id' => 'end', 'name' => 'Done', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->quote, $this->company);

        $run = $run->fresh();
        // Should have skipped the wait and completed immediately
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);

        // Check step history shows the wait was skipped
        $skipped = collect($run->step_history)->where('step_id', 'wait')->where('status', 'skipped')->first();
        $this->assertNotNull($skipped);
        $this->assertEquals('satisfied_when already met', $skipped['result']['reason']);
    }

    public function testSatisfiedWhenDoesNotSkipWhenNotMet()
    {
        // Quote is in sent state, not yet approved
        $this->quote->status_id = Quote::STATUS_SENT;
        $this->quote->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'quote',
            'trigger_event' => 'sent',
            'steps' => [
                [
                    'id' => 'wait',
                    'name' => 'Wait for approval',
                    'type' => 'wait_for_event',
                    'event' => 'quote.approved',
                    'satisfied_when' => [
                        'field' => '$trigger.status_id',
                        'operator' => '=',
                        'value' => Quote::STATUS_APPROVED,
                    ],
                    'timeout_days' => 3,
                ],
                ['id' => 'end', 'name' => 'Done', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->quote, $this->company);

        $run = $run->fresh();
        // Should be waiting — condition not yet met
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);
    }

    public function testEventResumesWaitingRun()
    {
        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'quote',
            'trigger_event' => 'sent',
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait for approval', 'type' => 'wait_for_event', 'event' => 'quote.approved'],
                ['id' => 'end', 'name' => 'Done', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->quote, $this->company);

        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->fresh()->status);

        // Fire the approval event
        $engine->onEvent('quote', 'approved', $this->quote, $this->company);

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);
    }

    public function testCancelRun()
    {
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => 7],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->fresh()->status);

        $engine->cancelRun($run);

        $this->assertEquals(WorkflowRun::STATUS_CANCELLED, $run->fresh()->status);
    }

    public function testRetryFailedRun()
    {
        // Create a workflow that will fail (unknown operation)
        $workflow = $this->createTestWorkflow([
            'steps' => [
                [
                    'id' => 'bad_step',
                    'name' => 'Bad Step',
                    'type' => 'action',
                    'action' => 'entity_operation',
                    'params' => ['entity_ref' => '$trigger', 'operation' => 'nonexistent_op'],
                    'on_error' => 'stop',
                ],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $this->assertEquals(WorkflowRun::STATUS_FAILED, $run->fresh()->status);
    }

    // ==========================================
    // Trigger Conditions
    // ==========================================

    public function testTriggerConditionsMet()
    {
        $this->invoice->amount = 5000;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'trigger_conditions' => [
                ['field' => 'amount', 'operator' => '>', 'value' => 1000],
            ],
            'steps' => [
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $engine->onEvent('invoice', 'created', $this->invoice, $this->company);

        // Should have created a run since conditions are met
        $this->assertEquals(1, WorkflowRun::where('workflow_id', $workflow->id)->count());
    }

    public function testTriggerConditionsNotMet()
    {
        $this->invoice->amount = 500;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'trigger_conditions' => [
                ['field' => 'amount', 'operator' => '>', 'value' => 1000],
            ],
            'steps' => [
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $engine->onEvent('invoice', 'created', $this->invoice, $this->company);

        // Should NOT have created a run
        $this->assertEquals(0, WorkflowRun::where('workflow_id', $workflow->id)->count());
    }

    // ==========================================
    // Timer/Cron Tests
    // ==========================================

    public function testCronProcessesTimerDelays()
    {
        // Due date in the future so startRun parks the wait
        $this->invoice->due_date = now()->addDays(1)->format('Y-m-d');
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => 0],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        // Simulate the wait_until being in the past (as if time passed)
        $run->update(['wait_until' => now()->subMinute()]);

        $engine->processTimedOutRuns();

        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->fresh()->status);
    }

    public function testCronProcessesEventTimeout()
    {
        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'quote',
            'trigger_event' => 'sent',
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait', 'type' => 'wait_for_event', 'event' => 'quote.approved', 'timeout_days' => 1, 'on_timeout' => 'timeout_end'],
                ['id' => 'end', 'name' => 'Approved', 'type' => 'end'],
                ['id' => 'timeout_end', 'name' => 'Timed Out', 'type' => 'end', 'end_status' => 'timed_out'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->quote, $this->company);

        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->fresh()->status);

        // Simulate timeout
        $run->update(['wait_until' => now()->subMinute()]);

        $engine->processTimedOutRuns();

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);
    }

    public function testCronProcessesRetry()
    {
        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        // Create a run that looks like it's waiting for a retry
        $run = WorkflowRunFactory::create($this->company->id, $this->user->id, $workflow, $this->invoice);
        $run->current_step_id = 'end';
        $run->status = WorkflowRun::STATUS_WAITING;
        $run->waiting_for = '__retry__';
        $run->waiting_since = now()->subHour();
        $run->wait_until = now()->subMinute();
        $run->context = ['trigger' => $this->invoice->id, 'invoice' => $this->invoice->id];
        $run->save();

        $engine = new WorkflowEngine();
        $engine->processTimedOutRuns();

        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->fresh()->status);
    }

    // ==========================================
    // Context Resolution Tests
    // ==========================================

    public function testContextResolverTrigger()
    {
        $run = new WorkflowRun();
        $run->workflowable_type = get_class($this->invoice);
        $run->workflowable_id = $this->invoice->id;

        $entity = ContextResolver::resolveEntity('$trigger', [], $run);
        $this->assertNotNull($entity);
        $this->assertEquals($this->invoice->id, $entity->id);
    }

    public function testContextResolverField()
    {
        $this->invoice->amount = 999.99;
        $this->invoice->saveQuietly();

        $run = new WorkflowRun();
        $run->workflowable_type = get_class($this->invoice);
        $run->workflowable_id = $this->invoice->id;

        $context = ['trigger' => $this->invoice->id, 'invoice' => $this->invoice->id];

        $value = ContextResolver::resolveField('$invoice.amount', $context, $run);
        $this->assertEquals(999.99, $value);
    }

    // ==========================================
    // WorkflowOperationException Tests
    // ==========================================

    public function testWorkflowOperationExceptionCarriesType()
    {
        $e = new WorkflowOperationException('test', OperationFailureType::GUARD_FAILED);
        $this->assertEquals(OperationFailureType::GUARD_FAILED, $e->failureType);
        $this->assertEquals('test', $e->getMessage());
    }

    // ==========================================
    // Workflow Run API Tests
    // ==========================================

    public function testWorkflowRunIndex()
    {
        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $engine->startRun($workflow, $this->invoice, $this->company);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson('/api/v1/workflow_runs');

        $response->assertStatus(200);
    }

    public function testWorkflowRunCancel()
    {
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => 7],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/workflow_runs/{$run->hashed_id}/cancel");

        $response->assertStatus(200);
        $this->assertEquals(WorkflowRun::STATUS_CANCELLED, $run->fresh()->status);
    }

    public function testWorkflowRunAdvance()
    {
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => 7],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/workflow_runs/{$run->hashed_id}/advance");

        $response->assertStatus(200);
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->fresh()->status);
    }

    // ==========================================
    // Multi-Step Workflow Integration Tests
    // ==========================================

    public function testMultiStepWorkflowWithBranching()
    {
        $this->invoice->status_id = Invoice::STATUS_DRAFT;
        $this->invoice->balance = 5000;
        $this->invoice->amount = 5000;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                [
                    'id' => 'mark_sent',
                    'name' => 'Mark Sent',
                    'type' => 'action',
                    'action' => 'entity_operation',
                    'params' => ['entity_ref' => '$trigger', 'operation' => 'mark_sent'],
                    'on_guard_fail' => 'skip',
                ],
                [
                    'id' => 'check_balance',
                    'name' => 'Check balance',
                    'type' => 'branch',
                    'conditions' => [
                        [
                            'label' => 'Has balance',
                            'if' => ['field' => '$trigger.balance', 'operator' => '>', 'value' => 0],
                            'goto' => 'notify',
                        ],
                    ],
                    'default_next' => 'end_paid',
                ],
                ['id' => 'notify', 'name' => 'Notify', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'creator', 'message' => 'Invoice needs attention']],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
                ['id' => 'end_paid', 'name' => 'Already Paid', 'type' => 'end', 'end_status' => 'already_paid'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);

        // Verify steps executed in order
        $steps = collect($run->step_history)->where('status', 'completed')->pluck('step_id')->toArray();
        $this->assertContains('mark_sent', $steps);
        $this->assertContains('check_balance', $steps);
        $this->assertContains('notify', $steps);
    }

    public function testContextAccumulation()
    {
        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'client',
            'trigger_event' => 'created',
            'steps' => [
                [
                    'id' => 'create_task',
                    'name' => 'Create Task',
                    'type' => 'action',
                    'action' => 'create_task',
                    'params' => ['description' => 'Test task for {{client.name}}'],
                    'output_key' => 'task',
                ],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->client, $this->company);

        $run = $run->fresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);

        // Verify context has the task entity stored
        $this->assertArrayHasKey('task', $run->context);
        $this->assertNotNull($run->context['task']);
    }

    public function testRunCreatedOnStart()
    {
        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $engine->startRun($workflow, $this->invoice, $this->company);

        $this->assertEquals(1, WorkflowRun::where('workflow_id', $workflow->id)->count());
    }

    public function testArchivedWorkflowNotTriggered()
    {
        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        // Archive the workflow (soft-delete acts as inactive)
        $workflow->delete();

        $engine = new WorkflowEngine();
        $engine->onEvent('invoice', 'created', $this->invoice, $this->company);

        $this->assertEquals(0, WorkflowRun::where('workflow_id', $workflow->id)->count());
    }

    // ==========================================
    // Step History Tests
    // ==========================================

    public function testStepHistoryRecorded()
    {
        $this->invoice->status_id = Invoice::STATUS_DRAFT;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                [
                    'id' => 'mark_sent',
                    'name' => 'Mark Sent',
                    'type' => 'action',
                    'action' => 'entity_operation',
                    'params' => ['entity_ref' => '$trigger', 'operation' => 'mark_sent'],
                ],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();
        $history = $run->step_history;

        // Should have entries for: mark_sent started, mark_sent completed, end completed
        $this->assertGreaterThanOrEqual(3, count($history));

        // Verify structure
        $firstEntry = $history[0];
        $this->assertArrayHasKey('step_id', $firstEntry);
        $this->assertArrayHasKey('step_name', $firstEntry);
        $this->assertArrayHasKey('step_type', $firstEntry);
        $this->assertArrayHasKey('status', $firstEntry);
        $this->assertArrayHasKey('started_at', $firstEntry);
    }

    // ==========================================
    // Invoice Reminder Scenario Tests
    // ==========================================

    /**
     * Full scenario: trigger on invoice.sent → wait until due_date + 3 days → send email if overdue.
     *
     * Steps:
     * 1. Trigger: invoice is sent
     * 2. wait_delay: date_field=$trigger.due_date, offset_days=3
     * 3. branch: if balance > 0 → send_reminder, else → end
     * 4. send_reminder: send_email action
     * 5. end
     */
    public function testInvoiceReminderAfterDueDate()
    {
        // Set up an invoice that's due 10 days ago (so due_date + 3 is 7 days ago = already past)
        $this->invoice->status_id = Invoice::STATUS_SENT;
        $this->invoice->due_date = now()->subDays(10)->format('Y-m-d');
        $this->invoice->balance = 1000;
        $this->invoice->amount = 1000;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'invoice',
            'trigger_event' => 'sent',
            'steps' => [
                [
                    'id' => 'wait_overdue',
                    'name' => 'Wait 3 days after due date',
                    'type' => 'wait_delay',
                    'date_field' => '$trigger.due_date',
                    'offset_days' => 3,
                ],
                [
                    'id' => 'check_unpaid',
                    'name' => 'Is invoice still unpaid?',
                    'type' => 'branch',
                    'conditions' => [
                        [
                            'label' => 'Still has balance',
                            'if' => ['field' => '$trigger.balance', 'operator' => '>', 'value' => 0],
                            'goto' => 'send_reminder',
                        ],
                    ],
                    'default_next' => 'end_paid',
                ],
                [
                    'id' => 'send_reminder',
                    'name' => 'Send overdue reminder',
                    'type' => 'action',
                    'action' => 'send_email',
                    'params' => ['entity_ref' => '$trigger', 'template' => 'reminder1'],
                    'on_error' => 'skip',
                ],
                ['id' => 'end', 'name' => 'Done', 'type' => 'end'],
                ['id' => 'end_paid', 'name' => 'Already Paid', 'type' => 'end', 'end_status' => 'paid'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();

        // Due date was 10 days ago, offset is 3 days, so wait_until was 7 days ago
        // The wait should have been skipped (already past)
        $waitStep = collect($run->step_history)->where('step_id', 'wait_overdue')->first();
        $this->assertNotNull($waitStep);
        $this->assertEquals('skipped', $waitStep['status']);

        // Branch should have taken the 'Still has balance' path (balance > 0)
        $branchStep = collect($run->step_history)->where('step_id', 'check_unpaid')->where('status', 'completed')->first();
        $this->assertNotNull($branchStep);
        $this->assertEquals('Still has balance', $branchStep['result']['branch_taken']);

        // Workflow should have completed (through send_reminder → end)
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);
    }

    /**
     * Same scenario but the due date is in the future — run should park in waiting state.
     */
    public function testInvoiceReminderWaitsWhenNotYetDue()
    {
        // Invoice due in 30 days — due_date + 3 = 33 days from now
        $this->invoice->status_id = Invoice::STATUS_SENT;
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->balance = 1000;
        $this->invoice->amount = 1000;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'invoice',
            'trigger_event' => 'sent',
            'steps' => [
                [
                    'id' => 'wait_overdue',
                    'name' => 'Wait 3 days after due date',
                    'type' => 'wait_delay',
                    'date_field' => '$trigger.due_date',
                    'offset_days' => 3,
                ],
                [
                    'id' => 'check_unpaid',
                    'name' => 'Is invoice still unpaid?',
                    'type' => 'branch',
                    'conditions' => [
                        [
                            'label' => 'Still has balance',
                            'if' => ['field' => '$trigger.balance', 'operator' => '>', 'value' => 0],
                            'goto' => 'send_reminder',
                        ],
                    ],
                    'default_next' => 'end_paid',
                ],
                [
                    'id' => 'send_reminder',
                    'name' => 'Send overdue reminder',
                    'type' => 'action',
                    'action' => 'send_email',
                    'params' => ['entity_ref' => '$trigger', 'template' => 'reminder1'],
                ],
                ['id' => 'end', 'name' => 'Done', 'type' => 'end'],
                ['id' => 'end_paid', 'name' => 'Already Paid', 'type' => 'end', 'end_status' => 'paid'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();

        // Should be waiting — due date + 3 days is in the future
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);
        $this->assertEquals('__timer__', $run->waiting_for);
        $this->assertNotNull($run->wait_until);

        // wait_until should be approximately due_date + 3 days
        $expectedDate = now()->addDays(33)->startOfDay();
        $actualDate = \Illuminate\Support\Carbon::parse($run->wait_until)->startOfDay();
        $this->assertEquals($expectedDate->format('Y-m-d'), $actualDate->format('Y-m-d'));
    }

    /**
     * Invoice was paid before the reminder fires — branch should skip the reminder.
     */
    public function testInvoiceReminderSkipsWhenPaid()
    {
        // Due date was 10 days ago, but invoice is fully paid (balance = 0)
        $this->invoice->status_id = Invoice::STATUS_PAID;
        $this->invoice->due_date = now()->subDays(10)->format('Y-m-d');
        $this->invoice->balance = 0;
        $this->invoice->amount = 1000;
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'invoice',
            'trigger_event' => 'sent',
            'steps' => [
                [
                    'id' => 'wait_overdue',
                    'name' => 'Wait 3 days after due date',
                    'type' => 'wait_delay',
                    'date_field' => '$trigger.due_date',
                    'offset_days' => 3,
                ],
                [
                    'id' => 'check_unpaid',
                    'name' => 'Is invoice still unpaid?',
                    'type' => 'branch',
                    'conditions' => [
                        [
                            'label' => 'Still has balance',
                            'if' => ['field' => '$trigger.balance', 'operator' => '>', 'value' => 0],
                            'goto' => 'send_reminder',
                        ],
                    ],
                    'default_next' => 'end_paid',
                ],
                [
                    'id' => 'send_reminder',
                    'name' => 'Send overdue reminder',
                    'type' => 'action',
                    'action' => 'send_email',
                    'params' => ['entity_ref' => '$trigger', 'template' => 'reminder1'],
                ],
                ['id' => 'end', 'name' => 'Done', 'type' => 'end'],
                ['id' => 'end_paid', 'name' => 'Already Paid', 'type' => 'end', 'end_status' => 'paid'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);

        $run = $run->fresh();

        // Should have completed via the 'end_paid' path
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);

        // Branch should have taken the default path (balance is 0)
        $branchStep = collect($run->step_history)->where('step_id', 'check_unpaid')->where('status', 'completed')->first();
        $this->assertNotNull($branchStep);
        $this->assertEquals('default', $branchStep['result']['branch_taken']);

        // send_reminder should NOT appear in step history
        $reminderStep = collect($run->step_history)->where('step_id', 'send_reminder')->first();
        $this->assertNull($reminderStep);
    }

    // ==========================================
    // Restart / Loop Tests
    // ==========================================

    /**
     * Basic restart: end step with restart:true resets run to first step.
     * Uses a future wait_delay as the first step so the run parks after restart.
     */
    public function testRestartEndStepResetsToFirstStep()
    {
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => -3],
                ['id' => 'notify', 'name' => 'Notify', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'creator', 'message' => 'Loop cycle']],
                ['id' => 'loop', 'name' => 'Restart', 'type' => 'end', 'restart' => true],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);
        $run = $run->fresh();

        // Should be waiting on the first step (future date)
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);
        $this->assertEquals('wait', $run->current_step_id);

        // Simulate timer firing
        $run->update(['wait_until' => now()->subMinute()]);
        $engine->processTimedOutRuns();
        $run = $run->fresh();

        // After notify executes and restart fires, should be back at 'wait' and parked again
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);
        $this->assertEquals('wait', $run->current_step_id);

        // step_history should have a 'restarted' entry for the end step
        $restartedEntries = collect($run->step_history)->where('step_id', 'loop')->where('status', 'restarted');
        $this->assertEquals(1, $restartedEntries->count());
    }

    /**
     * Restart with wait_delay: after restart, the run parks on the wait step.
     */
    public function testRestartParksOnWaitDelay()
    {
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => -3],
                ['id' => 'notify', 'name' => 'Notify', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'creator', 'message' => 'Reminder']],
                ['id' => 'loop', 'name' => 'Restart', 'type' => 'end', 'restart' => true],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);
        $run = $run->fresh();

        // Should be waiting on the first step (due_date is in the future)
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);
        $this->assertEquals('wait', $run->current_step_id);
        $this->assertEquals('__timer__', $run->waiting_for);
    }

    /**
     * End step without restart:true completes normally (regression check).
     */
    public function testEndWithoutRestartCompletes()
    {
        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'notify', 'name' => 'Notify', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'creator', 'message' => 'Done']],
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);
        $run = $run->fresh();

        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);
        $this->assertNotNull($run->completed_at);
    }

    /**
     * Restart is halted when entity is archived.
     */
    public function testRestartStopsWhenEntityArchived()
    {
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => -3],
                ['id' => 'notify', 'name' => 'Notify', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'creator', 'message' => 'Loop']],
                ['id' => 'loop', 'name' => 'Restart', 'type' => 'end', 'restart' => true],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);
        $run = $run->fresh();

        // Run is waiting on the delay
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);

        // Archive the invoice
        $this->invoice->delete();

        // Simulate timer firing — entityIsInactive should catch it in advanceRun
        $run->update(['wait_until' => now()->subMinute()]);
        $engine->processTimedOutRuns();
        $run = $run->fresh();

        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);
        $this->assertStringContains('archived', $run->error_message);
    }

    /**
     * Restart is halted when entity is deleted.
     */
    public function testRestartStopsWhenEntityDeleted()
    {
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'steps' => [
                ['id' => 'wait', 'name' => 'Wait', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => -3],
                ['id' => 'notify', 'name' => 'Notify', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'creator', 'message' => 'Loop']],
                ['id' => 'loop', 'name' => 'Restart', 'type' => 'end', 'restart' => true],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $this->invoice, $this->company);
        $run = $run->fresh();

        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);

        // Mark as deleted
        $this->invoice->is_deleted = true;
        $this->invoice->saveQuietly();

        // Simulate timer firing
        $run->update(['wait_until' => now()->subMinute()]);
        $engine->processTimedOutRuns();
        $run = $run->fresh();

        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);
        $this->assertStringContains('deleted', $run->error_message);
    }

    /**
     * Full recurring invoice loop scenario:
     * wait_delay → send_email → wait_for_event (recurring_invoice.sent) → end (restart)
     *
     * Cycle 1: parks on wait_delay (next_send_date in future)
     * Timer fires: advances to send_email, then parks on wait_for_event
     * Event fires: recurring_invoice.sent resumes, hits restart, parks on wait_delay again
     * Cycle 2: next_send_date has advanced, so wait targets the new date
     */
    public function testRecurringInvoiceReminderLoop()
    {
        $ri = $this->recurring_invoice;
        $ri->next_send_date = now()->addDays(10)->format('Y-m-d');
        $ri->status_id = \App\Models\RecurringInvoice::STATUS_ACTIVE;
        $ri->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'recurring_invoice',
            'trigger_event' => 'started',
            'steps' => [
                ['id' => 'wait_before', 'name' => 'Wait until 3 days before send', 'type' => 'wait_delay', 'date_field' => '$trigger.next_send_date', 'offset_days' => -3],
                ['id' => 'send_reminder', 'name' => 'Send reminder', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'creator', 'message' => 'Invoice coming in 3 days']],
                ['id' => 'wait_sent', 'name' => 'Wait for invoice send', 'type' => 'wait_for_event', 'event' => 'recurring_invoice.sent', 'timeout_days' => 30],
                ['id' => 'loop', 'name' => 'Loop', 'type' => 'end', 'restart' => true],
            ],
        ]);

        $engine = new WorkflowEngine();

        // --- Start run (simulating recurring_invoice.started event) ---
        $run = $engine->startRun($workflow, $ri, $this->company);
        $run = $run->fresh();

        // Should be waiting on wait_delay (next_send_date - 3 days is in the future)
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);
        $this->assertEquals('wait_before', $run->current_step_id);
        $this->assertEquals('__timer__', $run->waiting_for);

        // --- Simulate timer firing (3 days before next_send_date) ---
        $run->update(['wait_until' => now()->subMinute()]);
        $engine->processTimedOutRuns();
        $run = $run->fresh();

        // Should have executed send_reminder and now be waiting for recurring_invoice.sent
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);
        $this->assertEquals('wait_sent', $run->current_step_id);
        $this->assertEquals('recurring_invoice.sent', $run->waiting_for);

        // --- Simulate recurring invoice sent (next_send_date advances) ---
        $ri->next_send_date = now()->addDays(40)->format('Y-m-d');
        $ri->saveQuietly();

        $engine->onEvent('recurring_invoice', 'sent', $ri, $this->company);
        $run = $run->fresh();

        // Restart should have fired — run should be waiting on wait_delay again with new date
        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);
        $this->assertEquals('wait_before', $run->current_step_id);
        $this->assertEquals('__timer__', $run->waiting_for);

        // wait_until should target the new next_send_date minus 3 days
        $expectedWaitUntil = \Illuminate\Support\Carbon::parse($ri->next_send_date)->subDays(3);
        $this->assertEquals($expectedWaitUntil->format('Y-m-d'), $run->wait_until->format('Y-m-d'));

        // Verify cycle count — should have one 'restarted' entry in step_history
        $restartedCount = collect($run->step_history)->where('status', 'restarted')->count();
        $this->assertEquals(1, $restartedCount);
    }

    /**
     * Recurring invoice loop: pausing the recurring invoice (archive) stops the loop.
     */
    public function testRecurringInvoiceLoopStopsOnArchive()
    {
        $ri = $this->recurring_invoice;
        $ri->next_send_date = now()->addDays(10)->format('Y-m-d');
        $ri->status_id = \App\Models\RecurringInvoice::STATUS_ACTIVE;
        $ri->saveQuietly();

        $workflow = $this->createTestWorkflow([
            'trigger_entity' => 'recurring_invoice',
            'trigger_event' => 'started',
            'steps' => [
                ['id' => 'wait_before', 'name' => 'Wait', 'type' => 'wait_delay', 'date_field' => '$trigger.next_send_date', 'offset_days' => -3],
                ['id' => 'notify', 'name' => 'Notify', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'creator', 'message' => 'Reminder']],
                ['id' => 'wait_sent', 'name' => 'Wait for send', 'type' => 'wait_for_event', 'event' => 'recurring_invoice.sent'],
                ['id' => 'loop', 'name' => 'Loop', 'type' => 'end', 'restart' => true],
            ],
        ]);

        $engine = new WorkflowEngine();
        $run = $engine->startRun($workflow, $ri, $this->company);
        $run = $run->fresh();

        $this->assertEquals(WorkflowRun::STATUS_WAITING, $run->status);

        // Archive the recurring invoice
        $ri->delete();

        // Simulate timer firing
        $run->update(['wait_until' => now()->subMinute()]);
        $engine->processTimedOutRuns();
        $run = $run->fresh();

        // Should be completed (entity is archived)
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $run->status);
        $this->assertStringContains('archived', $run->error_message);
    }

    // ==========================================
    // Helper
    // ==========================================

    /**
     * Assert that a string contains a substring.
     */
    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack, "Expected string containing '{$needle}', got null");
        $this->assertTrue(str_contains($haystack, $needle), "Expected '{$haystack}' to contain '{$needle}'");
    }

    private function createTestWorkflow(array $overrides = []): Workflow
    {
        $defaults = [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Workflow',
            'trigger_entity' => 'invoice',
            'trigger_event' => 'created',
            'steps' => [
                ['id' => 'end', 'name' => 'End', 'type' => 'end'],
            ],
            'is_deleted' => false,
            'is_template' => false,
        ];

        $data = array_merge($defaults, $overrides);

        $workflow = new Workflow();
        $workflow->fill($data);
        $workflow->company_id = $data['company_id'];
        $workflow->user_id = $data['user_id'];
        $workflow->is_deleted = $data['is_deleted'];
        $workflow->save();

        return $workflow;
    }
}
