<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\Decorators;

class BaseDecorator 
{

    public function __construct(public mixed $entity, public string $key)
    {
        
    }
    
    public function transform(): string 
    {
        return '';
    }
}