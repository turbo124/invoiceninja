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

namespace App\Services\Email\Builders;

use App\Models\Company;
use App\Utils\HtmlEngine;
use App\Utils\VendorHtmlEngine;
use App\Services\Email\EmailObject;

/**
 * Handles invoice / quote / credit / recurring_invoice (client party, via
 * HtmlEngine) and purchase_order (vendor party, via VendorHtmlEngine).
 *
 * Only resolves the variable set; EmailDefaults assembles the body, subject
 * and attachments from the entity + settings afterwards. The client/vendor
 * split lives solely in the engine choice below.
 */
class ReceivableEmailStrategy implements RendersEmailEntity
{
    public function build(EmailObject $email_object, Company $company): void
    {
        $overrides = $email_object->variables;

        $email_object->variables = match (class_basename($email_object->entity ?? '')) {
            'Invoice', 'Quote', 'Credit', 'RecurringInvoice' => (new HtmlEngine($email_object->invitation))->makeValues(),
            'PurchaseOrder' => (new VendorHtmlEngine($email_object->invitation))->makeValues(),
            default => [],
        };

        foreach ($overrides as $key => $value) {
            $email_object->variables[$key] = $value;
        }
    }
}
