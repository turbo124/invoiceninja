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

namespace App\Services\Quickbooks\Connection;

use App\Helpers\Cache\Atomic;
use App\Models\Company;
use App\Services\Email\AdminEmail;
use App\Services\Email\EmailObject;
use App\Utils\Ninja;
use Illuminate\Mail\Mailables\Address;

class QuickbooksReconnectNotifier
{
    public function notifyOwnerTokenExpired(Company $company): void
    {
        $cache_key = "qb_token_expired_notified:{$company->company_key}";

        if (! Atomic::set($cache_key, true, 60 * 60 * 24)) {
            return;
        }

        try {
            $mo = new EmailObject();
            $mo->subject = ctrans('texts.quickbooks_requires_reauth');
            $mo->body = ctrans('texts.quickbooks_requires_reauth_body');
            $mo->text_body = ctrans('texts.quickbooks_requires_reauth_body');
            $mo->company_key = $company->company_key;
            $mo->html_template = 'email.template.admin';
            $mo->to = [new Address($company->owner()->email, $company->owner()->present()->name())];
            $mo->url = Ninja::isHosted() ? config('ninja.react_url') . '/#/settings/integrations/quickbooks' : config('ninja.app_url');
            $mo->button = ctrans('texts.quickbooks_reconnect');
            $mo->settings = $company->settings;
            $mo->company = $company;
            $mo->logo = $company->present()->logo();

            AdminEmail::dispatch($mo, $company);
        } catch (\Exception $e) {
            nlog("Failed to send QuickBooks token expired notification: " . $e->getMessage());
        }
    }
}
