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

class ES extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "ES:FACE", "ES:VAT", "ES:FACE"],
            ["B", "", "ES:VAT", "ES:VAT"],
        ];
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        if (!isset($invoice->due_date)) {
            $p_invoice->DueDate = new \DateTime($invoice->date);
        }

        // B2G: Route through FACe network with three ES:FACE identifiers
        // ES-01-FISCAL: fiscal identifier of the recipient
        // ES-02-RECEPTOR: receptor code (office)
        // ES-03-PAGADOR: payer code (accounting unit)
        if ($invoice->client->classification == 'government') {
            $routing_id = $invoice->client->routing_id ?? '';

            // FACe routing requires fiscal/receptor/pagador codes
            // These are typically provided as a semicolon-delimited string in routing_id
            // Format: "FISCAL;RECEPTOR;PAGADOR"
            $face_parts = explode(';', $routing_id);
            $fiscal = trim($face_parts[0] ?? '');
            $receptor = trim($face_parts[1] ?? $fiscal);
            $pagador = trim($face_parts[2] ?? $fiscal);

            if (strlen($fiscal) > 0) {
                $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                    ["scheme" => 'ES:FACE', "id" => "ES-01-FISCAL:{$fiscal}"],
                    ["scheme" => 'ES:FACE', "id" => "ES-02-RECEPTOR:{$receptor}"],
                    ["scheme" => 'ES:FACE', "id" => "ES-03-PAGADOR:{$pagador}"],
                ]));
            }

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        if ($invoice->client->classification == 'business' && $invoice->company->getSetting('classification') == 'business') {
            // B2B requires payment means as credit_transfer
            $mutator_util->setPaymentMeans(true);
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Receiver mutations for when the client is in Spain but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // Non-ES sender to ES B2G receiver: route through FACe
        if ($invoice->client->classification == 'government') {
            $routing_id = $invoice->client->routing_id ?? '';
            $face_parts = explode(';', $routing_id);
            $fiscal = trim($face_parts[0] ?? '');
            $receptor = trim($face_parts[1] ?? $fiscal);
            $pagador = trim($face_parts[2] ?? $fiscal);

            if (strlen($fiscal) > 0) {
                $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                    ["scheme" => 'ES:FACE', "id" => "ES-01-FISCAL:{$fiscal}"],
                    ["scheme" => 'ES:FACE', "id" => "ES-02-RECEPTOR:{$receptor}"],
                    ["scheme" => 'ES:FACE', "id" => "ES-03-PAGADOR:{$pagador}"],
                ]));
            }
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
