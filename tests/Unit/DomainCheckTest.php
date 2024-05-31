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

use Tests\TestCase;

/**
 * @test
 */
class DomainCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDomainCheck(): void
    {

        $this->assertTrue(in_array('yopmail.com', \App\DataProviders\Domains::getDomains()));
        $this->assertFalse(in_array('invoiceninja.com', \App\DataProviders\Domains::getDomains()));

    }

    public function testSubdomainValidation(): void
    {
        $this->assertFalse($this->checker('invoiceninja'));
        $this->assertFalse($this->checker('hello'));
        $this->assertTrue($this->checker('nasty.pasty'));
    }

    public function checker($subdomain)
    {
        return ! preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?$/', $subdomain);
    }
}
