<?php

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\DefaultSettings;
use App\Factory\ClientFactory;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\GeneratesNumberCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Traits\GeneratesCounter
 */
class GeneratesCounterTest extends TestCase
{
    use GeneratesCounter;
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();
        $this->faker = \Faker\Factory::create();
        Model::reguard();

        $this->makeTestData();
    }

    public function testHasSharedCounter()
    {
        $this->assertFalse($this->hasSharedCounter($this->client));
    }

    public function testHasTrueSharedCounter()
    {
        $settings = $this->client->getMergedSettings();
        $settings->invoice_number_counter = 1;
        $settings->invoice_number_pattern = '{$year}-{$counter}';
        $settings->shared_invoice_quote_counter = 1;
        $this->company->settings = $settings;

        $this->company->save();

        $this->client->settings = $settings;
        $this->client->save();

        $gs = $this->client->group_settings;
        $gs->settings = $settings;
        $gs->save();

        $this->assertTrue($this->hasSharedCounter($this->client));
    }

    public function testInvoiceNumberValue()
    {
        $invoice_number = $this->getNextInvoiceNumber($this->client);

        $this->assertEquals($invoice_number, '0008');

        $invoice_number = $this->getNextInvoiceNumber($this->client);

        $this->assertEquals($invoice_number, '0009');
    }

    public function testQuoteNumberValue()
    {
        $quote_number = $this->getNextQuoteNumber($this->client);

        $this->assertEquals($quote_number, 0002);

        $quote_number = $this->getNextQuoteNumber($this->client);

        $this->assertEquals($quote_number, '0003');
    }

    public function testInvoiceNumberPattern()
    {
        $settings = $this->client->company->settings;
        $settings->invoice_number_counter = 1;
        $settings->invoice_number_pattern = '{$year}-{$counter}';

        $this->client->company->settings = $settings;
        $this->client->company->save();

        $this->client->settings = $settings;
        $this->client->save();
        $this->client->fresh();

        $invoice_number = $this->getNextInvoiceNumber($this->client);
        $invoice_number2 = $this->getNextInvoiceNumber($this->client);

        $this->assertEquals($invoice_number, date('Y').'-0001');
        $this->assertEquals($invoice_number2, date('Y').'-0002');
        $this->assertEquals($this->client->company->settings->invoice_number_counter, 3);
    }

    public function testQuoteNumberPattern()
    {
        $settings = $this->client->company->settings;
        $settings->quote_number_counter = 1;
        $settings->quote_number_pattern = '{$year}-{$counter}';

        $this->client->company->settings = $settings;
        $this->client->company->save();

        $this->client->settings = $settings;
        $this->client->save();
        $this->client->fresh();

        $quote_number = $this->getNextQuoteNumber($this->client);
        $quote_number2 = $this->getNextQuoteNumber($this->client);

        $this->assertEquals($quote_number, date('Y').'-0001');
        $this->assertEquals($quote_number2, date('Y').'-0002');
        $this->assertEquals($this->client->company->settings->quote_number_counter, 3);
    }

    public function testQuoteNumberPatternWithSharedCounter()
    {
        $settings = $this->client->company->settings;
        $settings->quote_number_counter = 100;
        $settings->invoice_number_counter = 1000;
        $settings->quote_number_pattern = '{$year}-{$counter}';
        $settings->shared_invoice_quote_counter = true;

        $this->client->company->settings = $settings;
        $this->client->company->save();

        $gs = $this->client->group_settings;
        $gs->settings = $settings;
        $gs->save();

        $quote_number = $this->getNextQuoteNumber($this->client);
        $quote_number2 = $this->getNextQuoteNumber($this->client);

        $this->assertEquals($quote_number, date('Y').'-1000');
        $this->assertEquals($quote_number2, date('Y').'-1001');
        $this->assertEquals($this->client->company->settings->quote_number_counter, 100);
    }

    public function testInvoiceClientNumberPattern()
    {
        $settings = $this->company->settings;
        $settings->client_number_pattern = '{$year}-{$clientCounter}';
        $settings->client_number_counter = 10;

        $this->company->settings = $settings;
        $this->company->save();

        $settings = $this->client->settings;
        $settings->client_number_pattern = '{$year}-{$clientCounter}';
        $settings->client_number_counter = 10;
        $this->client->settings = $settings;
        $this->client->save();
        $this->client->fresh();

        $this->assertEquals($this->client->settings->client_number_counter, 10);
        $this->assertEquals($this->client->getSetting('client_number_pattern'), '{$year}-{$clientCounter}');

        $invoice_number = $this->getNextClientNumber($this->client);

        $this->assertEquals($invoice_number, date('Y').'-0001');

        $invoice_number = $this->getNextClientNumber($this->client);
        $this->assertEquals($invoice_number, date('Y').'-0002');
    }

    public function testInvoicePadding()
    {
        $settings = $this->company->settings;
        $settings->counter_padding = 5;
        $settings->invoice_number_counter = 7;
        //$this->client->settings = $settings;
        $this->company->settings = $settings;
        $this->company->save();

        $cliz = ClientFactory::create($this->company->id, $this->user->id);
        $cliz->settings = ClientSettings::defaults();
        $cliz->save();
        $invoice_number = $this->getNextInvoiceNumber($cliz);

        $this->assertEquals($cliz->getSetting('counter_padding'), 5);
        $this->assertEquals($invoice_number, '00007');
        $this->assertEquals(strlen($invoice_number), 5);

        $settings = $this->company->settings;
        $settings->counter_padding = 10;
        $this->company->settings = $settings;
        $this->company->save();

        $cliz = ClientFactory::create($this->company->id, $this->user->id);
        $cliz->settings = ClientSettings::defaults();
        $cliz->save();

        $invoice_number = $this->getNextInvoiceNumber($cliz);

        $this->assertEquals($cliz->getSetting('counter_padding'), 10);
        $this->assertEquals(strlen($invoice_number), 10);
        $this->assertEquals($invoice_number, '0000000007');
    }

    public function testInvoicePrefix()
    {
        $settings = $this->company->settings;
        $this->company->settings = $settings;
        $this->company->save();

        $cliz = ClientFactory::create($this->company->id, $this->user->id);
        $cliz->settings = ClientSettings::defaults();
        $cliz->save();

        $invoice_number = $this->getNextInvoiceNumber($cliz);

        $this->assertEquals($invoice_number, '0008');

        $invoice_number = $this->getNextInvoiceNumber($cliz);

        $this->assertEquals($invoice_number, '0009');
    }

    public function testClientNumber()
    {
        $client_number = $this->getNextClientNumber($this->client);

        $this->assertEquals($client_number, '0001');

        $client_number = $this->getNextClientNumber($this->client);

        $this->assertEquals($client_number, '0002');
    }

    public function testClientNumberPrefix()
    {
        $settings = $this->company->settings;
        $this->company->settings = $settings;
        $this->company->save();

        $cliz = ClientFactory::create($this->company->id, $this->user->id);
        $cliz->settings = ClientSettings::defaults();
        $cliz->save();

        $client_number = $this->getNextClientNumber($cliz);

        $this->assertEquals($client_number, '0001');

        $client_number = $this->getNextClientNumber($cliz);

        $this->assertEquals($client_number, '0002');
    }

    public function testClientNumberPattern()
    {
        $settings = $this->company->settings;
        $settings->client_number_pattern = '{$year}-{$user_id}-{$counter}';
        $this->company->settings = $settings;
        $this->company->save();

        $cliz = ClientFactory::create($this->company->id, $this->user->id);
        $cliz->settings = ClientSettings::defaults();
        $cliz->save();

        $client_number = $this->getNextClientNumber($cliz);

        $this->assertEquals($client_number, date('Y').'-'.str_pad($this->client->user_id, 2, '0', STR_PAD_LEFT).'-0001');

        $client_number = $this->getNextClientNumber($cliz);

        $this->assertEquals($client_number, date('Y').'-'.str_pad($this->client->user_id, 2, '0', STR_PAD_LEFT).'-0002');
    }

    /*

        public function testClientNextNumber()
        {
            $this->assertEquals($this->getNextNumber($this->client),1);
        }
        public function testRecurringInvoiceNumberPrefix()
        {
            //$this->assertEquals($this->getNextNumber(RecurringInvoice::class), 'R1');
            $this->assertEquals($this->getCounter($this->client), 1);

        }
        public function testClientIncrementer()
        {
            $this->incrementCounter($this->client);
            $this->assertEquals($this->getCounter($this->client), 2);
        }
    /*
        public function testCounterValues()
        {
            $this->assertEquals($this->getCounter(Invoice::class), 1);
            $this->assertEquals($this->getCounter(RecurringInvoice::class), 1);
            $this->assertEquals($this->getCounter(Credit::class), 1);
        }
        public function testClassIncrementers()
        {
            $this->client->incrementCounter(Invoice::class);
            $this->client->incrementCounter(RecurringInvoice::class);
            $this->client->incrementCounter(Credit::class);
            $this->assertEquals($this->getCounter(Invoice::class), 3);
            $this->assertEquals($this->getCounter(RecurringInvoice::class), 3);
            $this->assertEquals($this->getCounter(Credit::class), 2);
        }

        public function testClientNumberPattern()
        {
            $settings = $this->client->getSettingsByKey('client_number_pattern');
            $settings->client_number_pattern = '{$year}-{$counter}';
            $this->client->setSettingsByEntity(Client::class, $settings);
            $company = Company::find($this->client->company_id);
            $this->assertEquals($company->settings->client_number_counter,1);
            $this->assertEquals($this->getNextNumber($this->client), date('y').'-1');
            $this->assertEquals($this->getNextNumber($this->client), date('y').'-2');

            $company = Company::find($this->client->company_id);
            $this->assertEquals($company->settings->client_number_counter,2);
            $this->assertEquals($this->client->settings->client_number_counter,1);
        }
        public function testClientNumberPatternWithDate()
        {
            date_default_timezone_set('US/Eastern');
            $settings = $this->client->getSettingsByKey('client_number_pattern');
            $settings->client_number_pattern = '{$date:j}-{$counter}';
            $this->client->setSettingsByEntity(Client::class, $settings);

            $this->assertEquals($this->getNextNumber($this->client), date('j') . '-1');
        }
        public function testClientNumberPatternWithDate2()
        {
            date_default_timezone_set('US/Eastern');
            $settings = $this->client->getSettingsByKey('client_number_pattern');
            $settings->client_number_pattern = '{$date:d M Y}-{$counter}';
            $this->client->setSettingsByEntity(Client::class, $settings);

            $this->assertEquals($this->getNextNumber($this->client), date('d M Y') . '-1');
        }
     */
}
