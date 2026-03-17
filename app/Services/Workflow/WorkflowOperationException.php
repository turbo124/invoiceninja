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

class WorkflowOperationException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly OperationFailureType $failureType,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
