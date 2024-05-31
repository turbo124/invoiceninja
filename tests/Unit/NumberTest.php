<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Utils\Number;
use Tests\TestCase;

/**
 * @test
 *
 * @covers  App\Utils\Number
 */
class NumberTest extends TestCase
{
    public function testRangeOfNumberFormats(): void
    {

        $floatvals = [
            '22000.76' => '22 000,76',
            '22000.76' => '22.000,76',
            '22000.76' => '22,000.76',
            '2201' => '2,201',
            '22001' => '22 001',
            '22002' => '22,002',
            '37123' => '37,123',
            '22' => '22.000',
            '22000' => '22.000,',
            '22000.76' => '22000.76',
            '22000.76' => '22000,76',
            '1022000.76' => '1.022.000,76',
            '1022000.76' => '1,022,000.76',
            '1000000' => '1,000,000',
            // "1000000" =>"1.000.000",
            '1022000.76' => '1022000.76',
            '1022000.76' => '1022000,76',
            '1022000' => '1022000',
            '0.76' => '0.76',
            '0.76' => '0,76',
            '0' => '0.00',
            '0' => '0,00',
            '1' => '1.00',
            '1' => '1,00',
            '423545' => '423545 €',
            '423545' => '423,545 €',
            // "423545" =>"423.545 €",
            '1' => '1,00 €',
            '1.02' => '€ 1.02',
            '1000.02' => "1'000,02 EUR",
            '1000.02' => '1 000.02$',
            '1000.02' => '1,000.02$',
            '1000.02' => '1.000,02 EURO',
            '9.975' => '9.975',
            '9975' => '9.975,',
            '9975' => '9.975,00',
            // "0.571" => "0,571",
        ];

        foreach ($floatvals as $key => $value) {

            $this->assertEquals($key, Number::parseFloat($value));

        }

    }

    public function testThreeDecimalFloatAsTax(): void
    {

        $value = '9.975';

        $res = Number::parseFloat($value);

        $this->assertEquals(9.975, $res);

    }

    public function testNegativeFloatParse(): void
    {

        $value = '-22,00';

        $res = Number::parseFloat($value);

        $this->assertEquals(-22.0, $res);

        $value = '-22.00';

        $res = Number::parseFloat($value);

        $this->assertEquals(-22.0, $res);

        $value = '-2200,00';

        $res = Number::parseFloat($value);

        $this->assertEquals(-2200.0, $res);

        $value = '-2.200,00';

        $res = Number::parseFloat($value);

        $this->assertEquals(-2200.0, $res);

        $this->assertEquals(-2200, $res);

    }

    public function testConvertDecimalCommaFloats(): void
    {
        $value = '22,00';

        $res = Number::parseFloat($value);

        $this->assertEquals(22.0, $res);

        $value = '22.00';

        $res = Number::parseFloat($value);

        $this->assertEquals(22.0, $res);

        $value = '1,000.00';

        $res = Number::parseFloat($value);

        $this->assertEquals(1000.0, $res);

        $value = '1.000,00';

        $res = Number::parseFloat($value);

        $this->assertEquals(1000.0, $res);

    }

    public function testFloatPrecision(): void
    {
        $value = 1.1;

        $precision = (int) strpos(strrev($value), '.');

        $result = round($value, $precision);

        $this->assertEquals(1.1, $result);
    }

    public function testFloatPrecision1(): void
    {
        $value = '1.1';

        $precision = (int) strpos(strrev($value), '.');

        $result = round($value, $precision);

        $this->assertEquals(1.1, $result);
    }

    public function testFloatPrecision2(): void
    {
        $value = 9.975;

        $precision = (int) strpos(strrev($value), '.');

        $result = round($value, $precision);

        $this->assertEquals(9.975, $result);
    }

    public function testFloatPrecision3(): void
    {
        $value = '9.975';

        $precision = (int) strpos(strrev($value), '.');

        $result = round($value, $precision);

        $this->assertEquals(9.975, $result);
    }

    public function testRoundingThreeLow(): void
    {
        $rounded = Number::roundValue(3.144444444444, 3);

        $this->assertEquals(3.144, $rounded);
    }

    public function testRoundingThreeHigh(): void
    {
        $rounded = Number::roundValue(3.144944444444, 3);

        $this->assertEquals(3.145, $rounded);
    }

    public function testRoundingTwoLow(): void
    {
        $rounded = Number::roundValue(2.145);

        $this->assertEquals(2.15, $rounded);
    }

    public function testParsingStringCurrency(): void
    {
        $amount = '€7,99';

        $converted_amount = Number::parseFloat($amount);

        $this->assertEquals(7.99, $converted_amount);
    }

    public function testMultiCommaNumber(): void
    {
        $amount = '100,100.00';

        $converted_amount = Number::parseFloat($amount);

        $this->assertEquals(100100, $converted_amount);
    }

    public function testMultiDecimalNumber(): void
    {
        $amount = '100.1000.000,00';

        $converted_amount = Number::parseFloat($amount);

        $this->assertEquals(1001000000, $converted_amount);
    }
}
