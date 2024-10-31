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

namespace App\Services\EDocument\Gateway\Storecove\Models;


class References
{
    public string $documentType;
    public string $documentId;
    public ?string $lineId;
    public ?string $issueDate;

    public function __construct(
        string $documentType,
        string $documentId,
        ?string $lineId = null,
        ?string $issueDate = null
    ) {
        $this->documentType = $documentType;
        $this->documentId = $documentId;
        $this->lineId = $lineId;
        $this->issueDate = $issueDate;
    }
}
