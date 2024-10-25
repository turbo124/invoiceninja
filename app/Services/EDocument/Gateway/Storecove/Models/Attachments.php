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


class Attachments
{
    public string $filename;
    public string $document;
    public string $mimeType;
    public bool $primaryImage;
    public string $documentId;
    public string $description;

    public function __construct(
        string $filename,
        string $document,
        string $mimeType,
        bool $primaryImage,
        string $documentId,
        string $description
    ) {
        $this->filename = $filename;
        $this->document = $document;
        $this->mimeType = $mimeType;
        $this->primaryImage = $primaryImage;
        $this->documentId = $documentId;
        $this->description = $description;
    }
}
