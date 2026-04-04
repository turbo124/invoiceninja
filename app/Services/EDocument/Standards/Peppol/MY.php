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
 * Malaysia - MyInvois + Peppol
 *
 * Mandatory for companies > MYR 500K annual turnover from July 2025.
 * Uses MY:EIF (E-Invoice Framework ID) for routing and MY:TIN for tax.
 * Storecove handles LHDNM validation before delivery.
 */
class MY extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["B", "MY:EIF", "MY:TIN", "MY:EIF"];
    }

    /**
     * MY uses id_number (EIF) for routing.
     */
    public function resolveClientIdentifier(mixed $invoice, string $routingCode): ?string
    {
        if ($routingCode === 'MY:EIF' && strlen($invoice->client->id_number ?? '') > 1) {
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

        // Enable MyInvois network
        $storecove_meta = $this->mergeMeta($storecove_meta, ["networks" => [
            [
                "application" => "my-myinvois",
                "settings" => [
                    "enabled" => true,
                ],
            ],
        ]]);

        // B2C: email routing fallback for individuals
        if ($invoice->client->classification == 'individual') {
            $email = $invoice->client->present()->email();

            if (strlen($email) > 2) {
                $storecove_meta = $this->setEmailRouting($storecove_meta, $email);
            }

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // B2B: route via MY:EIF
        $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
            ["scheme" => 'MY:EIF', "id" => $invoice->client->id_number],
        ]));

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Receiver mutations for when the client is in Malaysia but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // Non-MY sender to MY B2B receiver
        if (in_array($invoice->client->classification, ['business', 'government'])) {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'MY:EIF', "id" => $invoice->client->id_number],
            ]));
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
