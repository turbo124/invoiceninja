<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards\Peppol;

use App\Services\EDocument\Gateway\MutatorUtil;

/**
 * Belgium - Peppol BIS 3.0
 *
 * B2B e-invoicing mandatory from January 1, 2026.
 * Uses BE:EN (Enterprise Number) for business routing.
 * BE:VAT as fallback if BE:EN discovery fails.
 */
class BE extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["B+G", "BE:EN", "BE:VAT", "BE:EN"];
    }

    /**
     * BE uses id_number (Enterprise Number) for routing.
     * Strip BE prefix from vat_number to get the EN.
     */
    public function resolveClientIdentifier(mixed $invoice, string $routingCode): ?string
    {
        if ($routingCode === 'BE:EN') {
            $identifier = $invoice->client->id_number ?? $invoice->client->vat_number ?? '';
            return preg_replace("/^BE/i", "", preg_replace("/[^a-zA-Z0-9]/", "", $identifier));
        }

        return null;
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        $identifier = preg_replace("/[^a-zA-Z0-9]/", "", $invoice->client->vat_number ?? $invoice->client->id_number ?? '');
        $identifier = preg_replace("/^BE/i", "", $identifier);

        // Try BE:EN first (Enterprise Number without country prefix)
        $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
            ["scheme" => 'BE:EN', "id" => $identifier],
        ]));

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Receiver mutations for when the client is in Belgium but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        $identifier = preg_replace("/[^a-zA-Z0-9]/", "", $invoice->client->vat_number ?? $invoice->client->id_number ?? '');
        $identifier = preg_replace("/^BE/i", "", $identifier);

        $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
            ["scheme" => 'BE:EN', "id" => $identifier],
        ]));

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
