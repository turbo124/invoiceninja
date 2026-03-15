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

namespace App\Services\Workflow\Actions;

use App\Models\Company;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Services\Workflow\ContextResolver;
use App\Services\Workflow\TemplateVariableResolver;
use App\Utils\Traits\MakesHash;

class NotifyUserAction implements WorkflowActionInterface
{
    use MakesHash;

    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $to = $params['to'] ?? 'assigned_user';
        $message = TemplateVariableResolver::resolve($params['message'] ?? '', $context, $run);

        $users = $this->resolveRecipients($to, $params, $context, $run, $company);

        foreach ($users as $user) {
            // Use existing notification infrastructure
            // For now, log the notification - extend with actual notification channels
            nlog("Workflow notification to user {$user->id}: {$message}");
        }

        return [
            'action' => 'notify_user',
            'to' => $to,
            'recipients' => $users->pluck('id')->toArray(),
            'message' => $message,
        ];
    }

    private function resolveRecipients(string $to, array $params, array $context, WorkflowRun $run, Company $company)
    {
        return match ($to) {
            'assigned_user' => $this->getAssignedUser($context, $run),
            'creator' => collect([User::find($run->user_id)]),
            'specific_user' => collect([User::find($this->decodePrimaryKey($params['user_id'] ?? ''))]),
            'all_admins' => $company->users()->wherePivot('is_admin', true)->get(),
            default => collect([User::find($run->user_id)]),
        };
    }

    private function getAssignedUser(array $context, WorkflowRun $run)
    {
        $entity = $run->entity();

        if ($entity && isset($entity->assigned_user_id) && $entity->assigned_user_id) {
            return collect([User::find($entity->assigned_user_id)]);
        }

        return collect([User::find($run->user_id)]);
    }
}
