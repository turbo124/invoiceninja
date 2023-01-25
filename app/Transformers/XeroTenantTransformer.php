<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
namespace App\Transformers;

use App\Models\XeroTenant;
use App\Utils\Traits\MakesHash;

class XeroTenantTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [];

    /**
     * @var array
     */
    protected $availableIncludes = [];

    /**
     * @param XeroTenant $xero_tenant
     *
     * @return array
     */
    public function transform(XeroTenant $xero_tenant)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($xero_tenant->id),
            'tenant_id' => (string) $xero_tenant->tenant_id,
            'tenant_name' => (string) $xero_tenant->tenant_name,
            'tenant_type' => (string) $xero_tenant->tenant_type,
            'is_deleted' => (bool) $xero_tenant->is_deleted,
            'company_id' => (string) $this->encodePrimaryKey($xero_tenant->company_id),
            'user_id' => (string) $this->encodePrimaryKey($xero_tenant->user_id),
            'archived_at' => (int) $xero_tenant->deleted_at,
            'updated_at' => (int) $xero_tenant->updated_at,
            'created_at' => (int) $xero_tenant->created_at,
        ];
    }
}
