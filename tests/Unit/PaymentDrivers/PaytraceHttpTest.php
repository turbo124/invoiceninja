<?php

namespace Tests\Unit\PaymentDrivers;

use App\Models\CompanyGateway;
use App\PaymentDrivers\PaytracePaymentDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaytraceHttpTest extends TestCase
{
    public function testGatewayRequestUsesOAuthFormPostThenJsonApiCall(): void
    {
        $gateway = new CompanyGateway();
        $gateway->setConfig([
            'testMode' => true,
            'username' => 'user',
            'password' => 'pass',
            'integratorId' => '123456789012',
        ]);

        Http::fake([
            'https://api.sandbox.paytrace.com/oauth/token' => Http::response(['access_token' => 'token-abc'], 200),
            'https://api.sandbox.paytrace.com/v1/customer/export' => Http::response(['success' => true], 200),
        ]);

        $driver = new PaytracePaymentDriver($gateway);
        $result = $driver->gatewayRequest('/v1/customer/export', [
            'integrator_id' => '123456789012',
            'customer_id' => 'invoice_ninja_auth_check',
        ]);

        $this->assertTrue($result->success);
        Http::assertSentCount(2);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.sandbox.paytrace.com/oauth/token'
                && $request->method() === 'POST'
                && $request->data() === [
                    'grant_type' => 'password',
                    'username' => 'user',
                    'password' => 'pass',
                ];
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.sandbox.paytrace.com/v1/customer/export'
                && $request->method() === 'POST'
                && $request->body() === json_encode([
                    'integrator_id' => '123456789012',
                    'customer_id' => 'invoice_ninja_auth_check',
                ])
                && $request->hasHeader('Authorization', 'Bearer token-abc')
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function testGetAuthTokenRequestsPaymentFieldsTokenWithBearerAuth(): void
    {
        $gateway = new CompanyGateway();
        $gateway->setConfig([
            'testMode' => false,
            'username' => 'live-user',
            'password' => 'live-pass',
        ]);

        Http::fake([
            'https://api.paytrace.com/oauth/token' => Http::response(['access_token' => 'live-token'], 200),
            'https://api.paytrace.com/v1/payment_fields/token/create' => Http::response(['clientKey' => 'client-key-123'], 200),
        ]);

        $driver = new PaytracePaymentDriver($gateway);

        $this->assertSame('client-key-123', $driver->getAuthToken());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.paytrace.com/v1/payment_fields/token/create'
                && $request->method() === 'POST'
                && $request->body() === ''
                && $request->hasHeader('Authorization', 'Bearer live-token')
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }
}
