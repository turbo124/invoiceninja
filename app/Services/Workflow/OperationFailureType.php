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

enum OperationFailureType: string
{
    case PERMANENT = 'permanent';
    case TRANSIENT = 'transient';
    case GUARD_FAILED = 'guard_failed';
    case ASSERTION_FAILED = 'assertion_failed';
}
