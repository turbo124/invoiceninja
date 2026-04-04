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
 * Singapore - InvoiceNow (Peppol) + IRAS reporting
 *
 * Uses SG:UEN (Unique Entity Number) for routing.
 * Storecove reports all sales e-invoices to IRAS automatically.
 * CorpPass OAuth flow is used for entity registration.
 */
class SG extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "SG:UEN", false, "0195:SGUENT08GA0028A"],
            ["B", "SG:UEN", "SG:GST", "SG:UEN"],
        ];
    }

    /**
     * SG uses id_number (UEN) for routing, not vat_number.
     */
    public function resolveClientIdentifier(mixed $invoice, string $routingCode): ?string
    {
        if (strlen($invoice->client->id_number ?? '') > 1) {
            return $invoice->client->id_number;
        }

        return null;
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // SG sender to SG B2G receiver: route to InvoiceNow government endpoint
        if ($invoice->client->classification == 'government') {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'SG:UEN', "id" => $invoice->client->id_number],
            ]));

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // SG sender to SG B2C receiver: email routing fallback
        if ($invoice->client->classification == 'individual') {
            $email = $invoice->client->present()->email();

            if (strlen($email) > 2) {
                $storecove_meta = $this->setEmailRouting($storecove_meta, $email);
            }

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // SG sender to SG B2B receiver: route via UEN
        $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
            ["scheme" => 'SG:UEN', "id" => $invoice->client->id_number],
        ]));

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Receiver mutations for when the client is in Singapore but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // Non-SG sender to SG B2G receiver
        if ($invoice->client->classification == 'government') {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'SG:UEN', "id" => $invoice->client->id_number],
            ]));

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // Non-SG sender to SG B2B receiver
        if (in_array($invoice->client->classification, ['business'])) {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'SG:UEN', "id" => $invoice->client->id_number],
            ]));
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
