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

namespace App\Factory;

use App\Models\GroupSetting;

class GroupSettingFactory
{
    public static function create(int $company_id, int $user_id): GroupSetting
    {
        $gs = new GroupSetting();
        $gs->id = 0;
        $gs->name = '';
        $gs->company_id = $company_id;
        $gs->user_id = $user_id;
        $gs->settings = new \stdClass();

        return $gs;
    }
}
