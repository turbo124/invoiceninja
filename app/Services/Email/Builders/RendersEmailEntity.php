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
use App\Services\Email\EmailObject;

/**
 * A per-entity strategy that hydrates the entity-specific portions of an
 * EmailObject (variables, and for pre-rendered entities the body/subject/
 * attachments). Generic chrome (from/to/cc/bcc/reply-to/template/headers) is
 * always applied afterwards by EmailDefaults.
 */
interface RendersEmailEntity
{
    public function build(EmailObject $email_object, Company $company): void;
}
