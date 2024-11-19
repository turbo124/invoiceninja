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

namespace App\DataMapper\EInvoice;


class TaxEntity
{
    /** @var string $version */
    public string $version = 'alpha';

    public ?int $legal_entity_id = null;

    public string $company_key = '';

    /** @var array<mixed> */
    public array $received_documents = [];

    /**
     * __construct
     *
     * @param mixed $entity
     */
    public function __construct(mixed $entity = null)
    {
        if (!$entity) {
            $this->init();
            return $this;
        }

        $entityArray = is_object($entity) ? get_object_vars($entity) : $entity;

        // $entityArray = get_object_vars($entity);
        foreach ($entityArray as $key => $value) {
            $this->{$key} = $value;
        }

        $this->migrate();
    }

    public function init(): self
    {
        return $this;
    }

    private function migrate(): self
    {
        return $this;
    }
}
