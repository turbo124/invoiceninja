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

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class StorecoveRouterTest extends TestCase
{
    use DatabaseTransactions;
    
    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();
               
        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

    }

    private function buildData()
    {
                
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => 'xyz123',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => \Illuminate\Support\Facades\Hash::make('ALongAndBriliantPassword'),
        ]);

        $client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id
        ]);

        $invoice->service()->markSent()->save();

        return $invoice;

    }

    public function testDeBusinessClientRouting()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        $client->vat_number = 'DE123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DE:VAT', $storecove->router->resolveTaxScheme('DE', 'business'));

    }

    public function testDeGovClientRouting()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        $client->vat_number = 'DE123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals(false, $storecove->router->resolveTaxScheme('DE', 'government'));

    }
}