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
 * Poland - KSeF (Krajowy System e-Faktur)
 *
 * Mandate: Feb 2026 for businesses > PLN 200M, Apr 2026 for all VAT-registered.
 * Storecove handles KSeF clearance when the network is enabled.
 */
class PL extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["G+B", "", "PL:VAT", "PL:VAT"];
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // Enable KSeF network for Polish senders
        $storecove_meta = $this->mergeMeta($storecove_meta, ["networks" => [
            [
                "application" => "pl-ksef",
                "settings" => [
                    "enabled" => true,
                ],
            ],
        ]]);

        // Route via PL:VAT
        $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
            ["scheme" => 'PL:VAT', "id" => $invoice->client->vat_number],
        ]));

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Receiver mutations for when the client is in Poland but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // Non-PL sender to PL receiver: route via PL:VAT
        $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
            ["scheme" => 'PL:VAT', "id" => $invoice->client->vat_number],
        ]));

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
