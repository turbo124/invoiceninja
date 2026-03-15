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
use Illuminate\Support\Facades\Http;

class SendWebhookAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $url = $params['url'] ?? '';
        $method = strtolower($params['method'] ?? 'post');
        $headers = $params['headers'] ?? [];

        if (empty($url)) {
            throw new \RuntimeException('Webhook URL is required');
        }

        $payload = [
            'workflow_id' => $run->workflow_id,
            'workflow_run_id' => $run->id,
            'entity_type' => class_basename($run->entity_type),
            'entity_id' => $run->entity_id,
            'context' => $context,
            'company_id' => $company->company_key,
        ];

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
}
