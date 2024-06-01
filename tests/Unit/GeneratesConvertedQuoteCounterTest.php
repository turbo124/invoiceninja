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
use App\Models\Quote;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use App\DataMapper\CompanySettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\Utils\Traits\GeneratesConvertedQuoteCounter;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 * @covers  App\Utils\Traits\GeneratesConvertedQuoteCounter
 */
class GeneratesConvertedQuoteCounterTest extends TestCase
{
    use GeneratesConvertedQuoteCounter;
    use MakesHash;

    protected $faker;

    protected function setUp() :void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

    }

    public function testCounterExtraction()
    {
        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $account->num_users = 3;
        $account->save();

        $fake_email = $this->faker->email();

        $user = User::where('email',$fake_email)->first();

        if (! $user) {
            $user = User::factory()->create([
                'account_id' => $account->id,
                'confirmation_code' => 'xxsd',
                // 'confirmation_code' => $this->createDbHash(config('database.default')),
                'email' => $fake_email,
            ]);
        }

        $user_id = $user->id;

        $settings = CompanySettings::defaults();
        $settings->currency_id = '1';
        $settings->invoice_number_counter = 1;
        $settings->invoice_number_pattern = '{$year}-I{$counter}';
        $settings->quote_number_pattern = '{$year}-Q{$counter}';
        $settings->shared_invoice_quote_counter = 1;
        $settings->timezone_id = '31';

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $client_settings = \App\DataMapper\ClientSettings::defaults();
        $client_settings->currency_id = '1';

        $client = Client::factory()->create([
            'user_id' => $user_id,
            'company_id' => $company->id,
            'settings' => $client_settings
        ]);

        $contact = ClientContact::factory()->create([
            'user_id' => $user_id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $quote = Quote::factory()->create([
            'user_id' => $client->user_id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => null,
        ]);

        $quote = $quote->service()->markSent()->convert()->save();

        $invoice = Invoice::find($quote->invoice_id);

        $this->assertNotNull($invoice);

        $this->assertEquals(now()->format('Y'). '-Q0001', $quote->number);
        $this->assertEquals(now()->format('Y'). '-I0001', $invoice->number);

        $settings = $company->settings;
        $settings->invoice_number_counter = 100;
        $settings->invoice_number_pattern = 'I{$counter}';
        $settings->quote_number_pattern = 'Q{$counter}';
        $settings->shared_invoice_quote_counter = 1;
        $settings->timezone_id = '31';
        $settings->currency_id = '1';
        
        $company->settings = $settings;
        $company->save();

        $quote = Quote::factory()->create([
            'user_id' => $client->user_id,
            'company_id' => $client->company_id,
            'client_id' => $client->id,
        ]);

        $quote = $quote->service()->markSent()->convert()->save();

        $invoice = Invoice::find($quote->invoice_id);

        $this->assertNotNull($invoice);

        $this->assertEquals('Q0100', $quote->number);
        $this->assertEquals('I0100', $invoice->number);
    }
}
