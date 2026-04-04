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

class FR extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "FR:SIRET + customerAssignedAccountIdValue", false, "0009:11000201100044"],
            ["B", "FR:SIRENE or FR:SIRET", "FR:VAT", "FR:SIRENE or FR:SIRET"],
        ];
    }

    public function resolveRoutingOverride(?string $classification, ?object $invoice = null): ?string
    {
        if (!$invoice) {
            return null;
        }

        $code = match ($classification) {
            'government' => 'G',
            'individual' => 'C',
            default => 'B',
        };

        if ($code === 'B' && strlen($invoice->client->id_number) == 9) {
            return 'FR:SIRENE';
        } elseif ($code === 'B' && strlen($invoice->client->id_number) == 14) {
            return 'FR:SIRET';
        } elseif ($code === 'G') {
            return '0009:11000201100044';
        }

        return null;
    }

    public function resolveTaxSchemeOverride(?string $classification, ?object $invoice = null): ?string
    {
        if (!$invoice) {
            return null;
        }

        $code = match ($classification) {
            'government' => 'G',
            'individual' => 'C',
            default => 'B',
        };

        if ($code === 'G') {
            return '0009:11000201100044';
        }

        return null;
    }

    /**
     * FR always uses id_number (SIREN/SIRET) for routing, not vat_number.
     */
    public function resolveClientIdentifier(mixed $invoice, string $routingCode): ?string
    {
        return $invoice->client->id_number;
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // B2G: Route to Chorus Pro via SIRET 0009:11000201100044
        if ($invoice->client->classification == 'government') {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => '11000201100044'],
            ]));

            // The SIRET / 0009 identifier of the final recipient is to be included
            // in the invoice.accountingCustomerParty.publicIdentifiers array.
            $mutator_util->setCustomerAssignedAccountId(true);

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // B2C: Email routing fallback for individuals
        if ($invoice->client->classification == 'individual') {
            $email = $invoice->client->present()->email();

            if (strlen($email) > 2) {
                $storecove_meta = $this->setEmailRouting($storecove_meta, $email);
            }

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // B2B: Route via SIRENE (9-digit) or SIRET (14-digit)
        $id_number = $invoice->client->id_number ?? '';

        if (strlen($id_number) == 9) {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'FR:SIRENE', "id" => $id_number],
            ]));
        } else {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => $id_number],
            ]));
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Receiver mutations for when the client is in France but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // Non-FR sender to FR government receiver: route to Chorus Pro
        if ($invoice->client->classification == 'government') {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => '11000201100044'],
            ]));

            $mutator_util->setCustomerAssignedAccountId(true);

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // Non-FR sender to FR B2B receiver: set SIRENE/SIRET routing
        $id_number = $invoice->client->id_number ?? '';

        if (strlen($id_number) == 9) {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'FR:SIRENE', "id" => $id_number],
            ]));
        } elseif (strlen($id_number) >= 14) {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => $id_number],
            ]));
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
