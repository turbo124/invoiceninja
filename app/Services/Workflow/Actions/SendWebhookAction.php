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
use App\Services\Workflow\TemplateVariableResolver;
use Illuminate\Support\Facades\Http;

class SendWebhookAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $url = $params['url'] ?? '';
        $method = strtolower($params['method'] ?? 'post');
        $headers = $params['headers'] ?? [];
        $payloadMode = $params['payload'] ?? 'entity';

        if (empty($url)) {
            throw new \RuntimeException('Webhook URL is required');
        }

        $payload = $payloadMode === 'custom'
            ? $this->buildCustomPayload($params, $context, $run)
            : $this->buildEntityPayload($context, $run, $company);

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->{$method}($url, $payload);

        return [
            'action' => 'send_webhook',
            'url' => $url,
            'method' => $method,
            'status_code' => $response->status(),
            'success' => $response->successful(),
        ];
    }

    private function buildEntityPayload(array $context, WorkflowRun $run, Company $company): array
    {
        $entity = $run->workflowable;

        return [
            'workflow_id' => $run->workflow_id,
            'workflow_run_id' => $run->id,
            'entity_type' => class_basename($run->workflowable_type),
            'entity_id' => $run->workflowable_id,
            'entity' => $entity ? $entity->toArray() : [],
            'context' => $context,
            'company_id' => $company->company_key,
        ];
    }

    private function buildCustomPayload(array $params, array $context, WorkflowRun $run): array
    {
        $body = TemplateVariableResolver::resolve($params['custom_body'] ?? '{}', $context, $run);

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['body' => $body];
    }
}
