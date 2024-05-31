<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Common;

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use Illuminate\Http\Request;

interface MethodInterface
{
    /**
     * Authorization page for the gateway method.
     */
    public function authorizeView(array $data);

    /**
     * Process the response from the authorization page.
     */
    public function authorizeResponse(Request $request);

    /**
     * Payment page for the gateway method.
     */
    public function paymentView(array $data);

    /**
     * Process the response from the payments page.
     */
    public function paymentResponse(PaymentResponseRequest $request);
}
