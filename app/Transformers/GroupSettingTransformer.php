<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\GroupSetting;
use App\Utils\Traits\MakesHash;

/**
 * class ClientTransformer.
 */
class GroupSettingTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
    ];

    /**
     * @param Client $client
     *
     * @return array
     */
    public function transform(GroupSetting $group_setting)
    {
        return [
            'id' => $this->encodePrimaryKey($group_setting->id),
            'name' => (string) $group_setting->name ?: '',
            'settings' => $group_setting->settings ?: new \stdClass,
            'created_at' => (int) $group_setting->created_at,
            'updated_at' => (int) $group_setting->updated_at,
            'archived_at' => (int) $group_setting->deleted_at,
            'is_deleted' => (bool) $group_setting->is_deleted,
        ];
    }
}
