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
use App\Models\CompanyUser;
use App\Models\WorkflowRun;
use App\Services\Workflow\ContextResolver;
use App\Utils\Traits\MakesHash;

class AssignUserAction implements WorkflowActionInterface
{
    use MakesHash;

    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $entity = ContextResolver::resolveEntity($params['entity_ref'] ?? '$trigger', $context, $run);

        if (! $entity || ! isset($entity->assigned_user_id)) {
            throw new \RuntimeException('Entity does not support user assignment');
        }

        $strategy = $params['strategy'] ?? 'specific';

        $userId = match ($strategy) {
            'specific' => $this->decodePrimaryKey($params['user_id'] ?? ''),
            'round_robin' => $this->roundRobinUser($company),
            default => throw new \RuntimeException("Unknown assignment strategy: {$strategy}"),
        };

        $entity->assigned_user_id = $userId;
        $entity->saveQuietly();

        return [
            'action' => 'assign_user',
            'strategy' => $strategy,
            'user_id' => $userId,
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->id,
        ];
    }

    private function roundRobinUser(Company $company): int
    {
        $users = CompanyUser::where('company_id', $company->id)
            ->where('is_locked', false)
            ->orderBy('updated_at', 'asc')
            ->get();

        if ($users->isEmpty()) {
            return $company->owner()->id;
        }

        $user = $users->first();
        $user->touch();

        return $user->user_id;
    }
}
