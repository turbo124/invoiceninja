<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\Tax;

use App\Services\Tax\VatNumberCheck;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 *  App\Services\Tax\VatNumberCheck
 */
class VatNumberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testVatNumber()
    {
        Http::fake(['ec.europa.eu/*' => Http::response(['countryCode' => 'IE', 'vatNumber' => '1234567L', 'valid' => false, 'name' => '---', 'address' => '---'])]);

        $vat_checker = new VatNumberCheck("1234567L", "IE");
        $result = $vat_checker->run();

        $this->assertFalse($result->isValid());
    }

    public function testValidVatNumber()
    {
        Http::fake(['ec.europa.eu/*' => Http::response(['countryCode' => 'AT', 'vatNumber' => 'U12345678', 'valid' => true, 'name' => 'Example GmbH', 'address' => 'Example Street 1, 1010 Wien'])]);

        $result = (new VatNumberCheck("ATU 123 456 78", "AT"))->run();

        $this->assertTrue($result->isValid());
        $this->assertEquals('Example GmbH', $result->getName());

        Http::assertSent(fn ($request) => $request['countryCode'] == 'AT' && $request['vatNumber'] == 'U12345678');
    }

    public function testGreeceIsCheckedAsEl()
    {
        Http::fake(['ec.europa.eu/*' => Http::response(['countryCode' => 'EL', 'vatNumber' => '123456789', 'valid' => false])]);

        (new VatNumberCheck("GR123456789", "GR"))->run();

        Http::assertSent(fn ($request) => $request['countryCode'] == 'EL' && $request['vatNumber'] == '123456789');
    }

    public function testUnavailableMemberStateIsNotAnInvalidNumber()
    {
        Http::fake(['ec.europa.eu/*' => Http::response(['actionSucceed' => false, 'errorWrappers' => [['error' => 'MS_UNAVAILABLE']]])]);

        $this->expectException(\RuntimeException::class);

        (new VatNumberCheck("1234567L", "IE"))->run();
    }

    public function testRateLimitPageIsNotAnInvalidNumber()
    {
        Http::fake(['ec.europa.eu/*' => Http::response('<html><body>Access denied</body></html>', 403)]);

        $this->expectException(\RuntimeException::class);

        (new VatNumberCheck("1234567L", "IE"))->run();
    }

    public function testEmptyVatNumberIsNotSent()
    {
        Http::fake();

        $this->assertFalse((new VatNumberCheck(null, "IE"))->run()->isValid());

        Http::assertNothingSent();
    }
}
