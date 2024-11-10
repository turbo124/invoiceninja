<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper;

use App\Casts\InvoiceSyncCast;
use Illuminate\Contracts\Database\Eloquent\Castable;

/**
 * InvoiceSync.
 */
class InvoiceSync implements Castable
{
    public string $qb_id;
    
    public mixed $email;

    public function __construct(array $attributes = [])
    {
        
        $this->qb_id = $attributes['qb_id'] ?? '';
        $this->email = new \stdClass();

        if(isset($attributes['email']))
        {
            
            $this->email->body = $attributes['email']['body'] ?? '';
            $this->email->subject = $attributes['email']['subject'] ?? '';
            $this->email->template = $attributes['email']['template'] ?? '';
            $this->email->entity = $attributes['email']['entity'] ?? '';
            $this->email->entity_id = $attributes['email']['entity_id'] ?? '';
            $this->email->cc_email = $attributes['email']['cc_email'] ?? '';
            $this->email->action = $attributes['email']['action'] ?? '';
            $this->email->ids = $attributes['email']['ids'] ?? [];
            $this->email->email_type = $attributes['email']['type'] ?? '';
        }
    }

    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return InvoiceSyncCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
