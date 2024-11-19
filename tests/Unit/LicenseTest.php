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

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Design;
use App\Models\License;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * 
 */
class LicenseTest extends TestCase
{
   
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testLicenseValidity()
    {
        $l = new License();
        $l->license_key = rand(0,10);
        $l->email = 'test@gmail.com';
        $l->transaction_reference = Str::random(10);
        $l->e_invoice_quota = 0;
        $l->save();


        $this->assertInstanceOf(License::class, $l);

        $this->assertTrue($l->isValid());
    }


    public function testLicenseValidityExpired()
    {
        $l = new License();
        $l->license_key = rand(0,10);
        $l->email = 'test@gmail.com';
        $l->transaction_reference = Str::random(10);
        $l->e_invoice_quota = 0;
        $l->save();

        $l->created_at = now()->subYears(2);
        $l->save();

        $this->assertInstanceOf(License::class, $l);

        $this->assertFalse($l->isValid());
    }

}
