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

class DE extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "DE:LWID", false, "DE:LWID"],
            ["B", "", "DE:VAT", "DE:VAT"],
        ];
    }

    public function resolveRoutingOverride(?string $classification, ?object $invoice = null): ?string
    {
        if ($classification === 'individual') {
            return 'DE:STNR';
        }

        return null;
    }

    public function resolveTaxSchemeOverride(?string $classification, ?object $invoice = null): ?string
    {
        if ($classification === 'individual') {
            return 'DE:STNR';
        }

        return null;
    }

    /**
     * DE government uses routing_id (Leitweg-ID), not vat_number.
     */
    public function resolveClientIdentifier(mixed $invoice, string $routingCode): ?string
    {
        if ($invoice->client->classification === 'government') {
            return $invoice->client->routing_id;
        }

        return null;
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        $mutator_util->setPaymentMeans(true);

        // XRechnung B2G: BuyerReference must contain the Leitweg-ID
        // when there is no PO number
        if ($invoice->client->classification === 'government') {
            $leitweg_id = $invoice->client->routing_id ?? '';

            if (strlen($leitweg_id) > 1 && empty($invoice->po_number)) {
                $p_invoice->BuyerReference = $leitweg_id;
            }

            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'DE:LWID', "id" => $leitweg_id],
            ]));
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Receiver mutations for when the client is in Germany but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // Non-DE sender to DE B2G receiver: route via Leitweg-ID
        if ($invoice->client->classification === 'government') {
            $leitweg_id = $invoice->client->routing_id ?? '';

            if (strlen($leitweg_id) > 1) {
                $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                    ["scheme" => 'DE:LWID', "id" => $leitweg_id],
                ]));

                if (empty($invoice->po_number)) {
                    $p_invoice->BuyerReference = $leitweg_id;
                }
            }
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
