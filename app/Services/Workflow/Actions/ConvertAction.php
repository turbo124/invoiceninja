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
use App\Services\Quote\ConvertQuote;
use App\Services\Quote\ConvertQuoteToProject;
use App\Services\Workflow\ContextResolver;

class ConvertAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $conversion = ($params['from'] ?? '') . '->' . ($params['to'] ?? '');

        return match ($conversion) {
            'quote->invoice' => $this->quoteToInvoice($context, $run),
            'quote->project' => $this->quoteToProject($context, $run),
            default => throw new \RuntimeException("Unsupported conversion: {$conversion}"),
        };
    }

    private function quoteToInvoice(array $context, WorkflowRun $run): array
    {
        $quote = ContextResolver::resolveEntityByKey('quote', $context, $run);

        if (! $quote) {
            throw new \RuntimeException('No quote found in workflow context for conversion');
        }

        $invoice = (new ConvertQuote($quote->client))->run($quote);

        return [
            'action' => 'convert',
            'from' => 'quote',
            'to' => 'invoice',
            'entity_type' => 'Invoice',
            'entity_id' => $invoice->id,
        ];
    }

    private function quoteToProject(array $context, WorkflowRun $run): array
    {
        $quote = ContextResolver::resolveEntityByKey('quote', $context, $run);

        if (! $quote) {
            throw new \RuntimeException('No quote found in workflow context for conversion');
        }

        $project = (new ConvertQuoteToProject($quote))->run();

        return [
            'action' => 'convert',
            'from' => 'quote',
            'to' => 'project',
            'entity_type' => 'Project',
            'entity_id' => $project->id,
        ];
    }
}
