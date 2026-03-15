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

namespace App\Services\Workflow;

use App\Models\WorkflowRun;

class TemplateVariableResolver
{
    /**
     * Resolve {{variable}} placeholders in a string.
     */
    public static function resolve(string $template, array $context, WorkflowRun $run): string
    {
        return preg_replace_callback('/\{\{(\w+)\.(\w+)\}\}/', function ($matches) use ($context, $run) {
            $entityKey = $matches[1];
            $fieldName = $matches[2];

            // Special variables
            if ($entityKey === 'date') {
                return now()->format('Y-m-d');
            }

            if ($entityKey === 'workflow') {
                $workflow = $run->workflow;
                return $workflow->{$fieldName} ?? '';
            }

            // Resolve from context
            $value = ContextResolver::resolveField('$' . $entityKey . '.' . $fieldName, $context, $run);

            if ($value === null) {
                return $matches[0]; // Return original placeholder if unresolved
            }

            // Handle related entity presenters
            if ($fieldName === 'name' && is_object($value)) {
                return (string) $value;
            }

            return (string) $value;
        }, $template);
    }
}
