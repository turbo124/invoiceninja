<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use League\Fractal\Serializer\ArraySerializer as FractalArraySerializer;

/**
 * Class ArraySerializer.
 */
class ArraySerializer extends FractalArraySerializer
{
    public function collection(?string $resourceKey, array $data): array
    {
        return $data;
    }

    public function item(?string $resourceKey, array $data): array
    {
        return $data;
    }
}
