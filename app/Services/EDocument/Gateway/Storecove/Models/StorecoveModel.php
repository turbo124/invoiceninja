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


class StorecoveModel
{
    public int $legalEntityId;
    public string $idempotencyGuid;
    public Routing $routing;
    /** @var Attachments[] */
    public array $attachments;
    public Document $document;

    /**
     * @param Attachments[] $attachments
     */
    public function __construct(
        int $legalEntityId,
        string $idempotencyGuid,
        Routing $routing,
        array $attachments,
        Document $document
    ) {
        $this->legalEntityId = $legalEntityId;
        $this->idempotencyGuid = $idempotencyGuid;
        $this->routing = $routing;
        $this->attachments = $attachments;
        $this->document = $document;
    }
}
