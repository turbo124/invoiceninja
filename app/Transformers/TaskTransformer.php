<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\Task;
use App\Utils\Traits\MakesHash;

/**
 * class TaskTransformer.
 */
class TaskTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
    ];

    public function transform(Task $task)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($task->id),
            'description' => $task->description ?: '',
            'duration' => 0,
            'created_at' => (int) $task->created_at,
            'updated_at' => (int) $task->updated_at,
            'archived_at' => (int) $task->deleted_at,
            'invoice_id' => $this->encodePrimaryKey($task->invoice_id),
            'client_id' => $this->encodePrimaryKey($task->client_id),
            'project_id' => $this->encodePrimaryKey($task->project_id),
            'is_deleted' => (bool) $task->is_deleted,
            'time_log' => $task->time_log ?: '',
            'is_running' => (bool) $task->is_running,
            'custom_value1' => $task->custom_value1 ?: '',
            'custom_value2' => $task->custom_value2 ?: '',
            'custom_value3' => $task->custom_value3 ?: '',
            'custom_value4' => $task->custom_value4 ?: '',
            'task_status_id' => $this->encodePrimaryKey($task->task_status_id),
            'task_status_sort_order' => (int) $task->task_status_sort_order,
        ];
    }
}
