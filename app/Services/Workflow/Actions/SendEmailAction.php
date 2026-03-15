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
use App\Models\WorkflowRun;
use App\Services\Workflow\ContextResolver;

class SendEmailAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $entity = ContextResolver::resolveEntity($params['entity_ref'] ?? '$trigger', $context, $run);

        if (! $entity) {
            throw new \RuntimeException("Cannot resolve entity reference: " . ($params['entity_ref'] ?? '$trigger'));
        }

        if (! method_exists($entity, 'service')) {
            throw new \RuntimeException("Entity " . get_class($entity) . " does not support service()->sendEmail()");
        }

        $entity->service()->markSent()->save();

        if ($entity->invitations->count() > 0) {
            $entity->service()->sendEmail();
        }

        return [
            'action' => 'send_email',
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->hashed_id,
            'template' => $params['template'] ?? 'default',
        ];
    }
}
