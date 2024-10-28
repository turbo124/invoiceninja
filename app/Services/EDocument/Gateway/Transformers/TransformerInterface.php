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


namespace App\Services\EDocument\Gateway\Transformers;

interface TransformerInterface
{
    public function transform(mixed $peppolInvoice);

    public function getInvoice();

    public function toJson();
}
