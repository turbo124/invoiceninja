<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Util;

use App\DataMapper\CompanySettings;
use App\Exceptions\MigrationValidatorFailed;
use App\Exceptions\ResourceDependencyMissing;
use App\Exceptions\ResourceNotAvailableForMigration;
use App\Factory\ClientFactory;
use App\Factory\CompanyLedgerFactory;
use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Factory\ProductFactory;
use App\Factory\QuoteFactory;
use App\Factory\TaxRateFactory;
use App\Factory\UserFactory;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\ValidationRules\ValidCompanyGatewayFeesAndLimitsRule;
use App\Http\ValidationRules\ValidUserForCompany;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\Ninja\CompanySizeCheck;
use App\Jobs\Util\VersionCheck;
use App\Libraries\MultiDB;
use App\Mail\MigrationCompleted;
use App\Mail\MigrationFailed;
use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\User;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\CreditRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\Migration\InvoiceMigrationRepository;
use App\Repositories\Migration\PaymentMigrationRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\UserRepository;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\CompanyGatewayFeesAndLimitsSaver;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Import implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use CompanyGatewayFeesAndLimitsSaver;
    use MakesHash;
    use CleanLineItems;

    /**
     * @var array
     */
    private $data;

    /**
     * @var Company
     */
    private $company;

    /**
     * @var array
     */
    private $available_imports = [
        'company',
        'users',
        'payment_terms',
        'tax_rates',
        'clients',
        'products',
        'invoices',
        'quotes',
        'payments',
        'credits',
        'company_gateways',
        //'documents',
        'client_gateway_tokens',
    ];

    /**
     * @var User
     */
    private $user;

    /**
     * Custom list of resources to be imported.
     *
     * @var array
     */
    private $resources;

    /**
     * Local state manager for ids.
     *
     * @var array
     */
    private $ids = [];

    public $tries = 1;

    public $timeout = 86400;

    public $retryAfter = 86430;

    /**
     * Create a new job instance.
     *
     * @param array $data
     * @param Company $company
     * @param User $user
     * @param array $resources
     */
    public function __construct(array $data, Company $company, User $user, array $resources = [])
    {
        $this->data = $data;
        $this->company = $company;
        $this->user = $user;
        $this->resources = $resources;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle() :bool
    {
        set_time_limit(0);

        foreach ($this->data as $key => $resource) {
            if (! in_array($key, $this->available_imports)) {
                //throw new ResourceNotAvailableForMigration("Resource {$key} is not available for migration.");
                info("Resource {$key} is not available for migration.");
                continue;
            }

            $method = sprintf('process%s', Str::ucfirst(Str::camel($key)));

            info("Importing {$key}");

            $this->{$method}($resource);
        }

        $this->setInitialCompanyLedgerBalances();

        Mail::to($this->user)->send(new MigrationCompleted());

        /*After a migration first some basic jobs to ensure the system is up to date*/
        VersionCheck::dispatch();
        CompanySizeCheck::dispatch();

        info('Completed🚀🚀🚀🚀🚀 at '.now());

        return true;
    }

    private function setInitialCompanyLedgerBalances()
    {
        Client::cursor()->each(function ($client) {
            $company_ledger = CompanyLedgerFactory::create($client->company_id, $client->user_id);
            $company_ledger->client_id = $client->id;
            $company_ledger->adjustment = $client->balance;
            $company_ledger->notes = 'Migrated Client Balance';
            $company_ledger->balance = $client->balance;
            $company_ledger->activity_id = Activity::CREATE_CLIENT;
            $company_ledger->save();

            $client->company_ledger()->save($company_ledger);
        });
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    private function processCompany(array $data): void
    {
        Company::unguard();

        $data = $this->transformCompanyData($data);

        $rules = (new UpdateCompanyRequest())->rules();

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        if (isset($data['account_id'])) {
            unset($data['account_id']);
        }

        if (isset($data['referral_code'])) {
            $account = $this->company->account;
            $account->referral_code = $data['referral_code'];
            $account->save();

            unset($data['referral_code']);
        }

        $company_repository = new CompanyRepository();
        $company_repository->save($data, $this->company);

        Company::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $rules = null;
        $validator = null;
        $company_repository = null;
    }

    private function transformCompanyData(array $data): array
    {
        $company_settings = CompanySettings::defaults();

        if (array_key_exists('settings', $data)) {
            foreach ($data['settings'] as $key => $value) {
                if ($key == 'invoice_design_id' || $key == 'quote_design_id' || $key == 'credit_design_id') {
                    $value = $this->encodePrimaryKey($value);
                }

                if ($key == 'payment_terms' && $key = '') {
                    $value = -1;
                }

                $company_settings->{$key} = $value;
            }

            $data['settings'] = $company_settings;
        }

        return $data;
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    private function processTaxRates(array $data): void
    {
        TaxRate::unguard();

        $rules = [
            '*.name' => 'required',
            //'*.name' => 'required|distinct|unique:tax_rates,name,null,null,company_id,' . $this->company->id,
            '*.rate' => 'required|numeric',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        foreach ($data as $resource) {
            $modified = $resource;
            $company_id = $this->company->id;
            $user_id = $this->processUserId($resource);

            if (isset($resource['user_id'])) {
                unset($resource['user_id']);
            }

            if (isset($resource['company_id'])) {
                unset($resource['company_id']);
            }

            $tax_rate = TaxRateFactory::create($this->company->id, $user_id);
            $tax_rate->fill($resource);

            $tax_rate->save();
        }

        TaxRate::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $rules = null;
        $validator = null;
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    private function processUsers(array $data): void
    {
        User::unguard();

        $rules = [
            '*.first_name' => ['string'],
            '*.last_name' => ['string'],
            '*.email' => ['distinct'],
        ];

        // if (config('ninja.db.multi_db_enabled')) {
        //     array_push($rules['*.email'], new ValidUserForCompany());
        // }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $user_repository = new UserRepository();

        foreach ($data as $resource) {
            $modified = $resource;
            unset($modified['id']);

            $user = $user_repository->save($modified, $this->fetchUser($resource['email']), true, true);

            $user_agent = array_key_exists('token_name', $resource) ?: request()->server('HTTP_USER_AGENT');

            CreateCompanyToken::dispatchNow($this->company, $user, $user_agent);

            $key = "users_{$resource['id']}";

            $this->ids['users'][$key] = [
                'old' => $resource['id'],
                'new' => $user->id,
            ];
        }

        User::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $rules = null;
        $validator = null;
        $user_repository = null;
    }

    /**
     * @param array $data
     * @throws ResourceDependencyMissing
     * @throws \Exception
     */
    private function processClients(array $data): void
    {
        Client::unguard();

        $contact_repository = new ClientContactRepository();
        $client_repository = new ClientRepository($contact_repository);

        foreach ($data as $key => $resource) {
            $modified = $resource;
            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);
            $modified['balance'] = $modified['balance'] ?: 0;
            $modified['paid_to_date'] = $modified['paid_to_date'] ?: 0;

            unset($modified['id']);
            unset($modified['contacts']);

            $client = $client_repository->save(
                $modified,
                ClientFactory::create(
                    $this->company->id,
                    $modified['user_id']
                )
            );

            $client->contacts()->forceDelete();

            if (array_key_exists('contacts', $resource)) { // need to remove after importing new migration.json
                $modified_contacts = $resource['contacts'];

                foreach ($modified_contacts as $key => $client_contacts) {
                    $modified_contacts[$key]['company_id'] = $this->company->id;
                    $modified_contacts[$key]['user_id'] = $this->processUserId($resource);
                    $modified_contacts[$key]['client_id'] = $client->id;
                    $modified_contacts[$key]['password'] = 'mysuperpassword'; // @todo, and clean up the code..
                    unset($modified_contacts[$key]['id']);
                }

                $saveable_contacts['contacts'] = $modified_contacts;

                $contact_repository->save($saveable_contacts, $client);
            }

            $key = "clients_{$resource['id']}";

            $this->ids['clients'][$key] = [
                'old' => $resource['id'],
                'new' => $client->id,
            ];
        }

        Client::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $contact_repository = null;
        $client_repository = null;
    }

    private function processProducts(array $data): void
    {
        Product::unguard();

        $rules = [
            //'*.product_key' => 'required|distinct|unique:products,product_key,null,null,company_id,' . $this->company->id,
            '*.cost' => 'numeric',
            '*.price' => 'numeric',
            '*.quantity' => 'numeric',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $product_repository = new ProductRepository();

        foreach ($data as $resource) {
            $modified = $resource;
            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            unset($modified['id']);

            $product_repository->save(
                $modified,
                ProductFactory::create(
                    $this->company->id,
                    $modified['user_id']
                )
            );
        }

        Product::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $product_repository = null;
    }

    private function processInvoices(array $data): void
    {
        Invoice::unguard();

        $rules = [
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $invoice_repository = new InvoiceMigrationRepository();

        foreach ($data as $key => $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing invoices failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;
            $modified['line_items'] = $this->cleanItems($modified['line_items']);

            unset($modified['id']);

            $invoice = $invoice_repository->save(
                $modified,
                InvoiceFactory::create($this->company->id, $modified['user_id'])
            );

            $key = "invoices_{$resource['id']}";

            $this->ids['invoices'][$key] = [
                'old' => $resource['id'],
                'new' => $invoice->id,
            ];
        }

        Invoice::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $invoice_repository = null;
    }

    private function processCredits(array $data): void
    {
        Credit::unguard();

        $rules = [
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $credit_repository = new CreditRepository();

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing credits failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;

            unset($modified['id']);

            $credit = $credit_repository->save(
                $modified,
                CreditFactory::create($this->company->id, $modified['user_id'])
            );

            $key = "credits_{$resource['id']}";

            $this->ids['credits'][$key] = [
                'old' => $resource['id'],
                'new' => $credit->id,
            ];
        }

        Credit::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $credit_repository = null;
    }

    private function processQuotes(array $data): void
    {
        Quote::unguard();

        $rules = [
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $quote_repository = new QuoteRepository();

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing quotes failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);

            $modified['company_id'] = $this->company->id;

            unset($modified['id']);

            $invoice = $quote_repository->save(
                $modified,
                QuoteFactory::create($this->company->id, $modified['user_id'])
            );

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $key = "invoices_{$resource['id']}";

            $this->ids['quotes'][$key] = [
                'old' => $resource['id'],
                'new' => $invoice->id,
            ];
        }

        Quote::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $quote_repository = null;
    }

    private function processPayments(array $data): void
    {
        Payment::reguard();

        $rules = [
            '*.amount' => ['required'],
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $payment_repository = new PaymentMigrationRepository(new CreditRepository());

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing payments failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);
            //$modified['invoice_id'] = $this->transformId('invoices', $resource['invoice_id']);
            $modified['company_id'] = $this->company->id;

            //unset($modified['invoices']);
            unset($modified['invoice_id']);

            if (isset($modified['invoices'])) {
                foreach ($modified['invoices'] as $key => $invoice) {
                    $modified['invoices'][$key]['invoice_id'] = $this->transformId('invoices', $invoice['invoice_id']);
                }
            }

            $payment = $payment_repository->save(
                $modified,
                PaymentFactory::create($this->company->id, $modified['user_id'])
            );

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $this->ids['payments'] = [
                "payments_{$old_user_key}" => [
                    'old' => $old_user_key,
                    'new' => $payment->id,
                ],
            ];
        }

        Payment::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $payment_repository = null;
    }

    private function processDocuments(array $data): void
    {
        Document::unguard();
        /* No validators since data provided by database is already valid. */

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('invoice_id', $resource) && $resource['invoice_id'] && ! array_key_exists('invoices', $this->ids)) {
                throw new ResourceDependencyMissing('Processing documents failed, because of missing dependency - invoices.');
            }

            if (array_key_exists('expense_id', $resource) && $resource['expense_id'] && ! array_key_exists('expenses', $this->ids)) {
                throw new ResourceDependencyMissing('Processing documents failed, because of missing dependency - expenses.');
            }

            /* Remove because of polymorphic joins. */
            unset($modified['invoice_id']);
            unset($modified['expense_id']);

            if (array_key_exists('invoice_id', $resource) && $resource['invoice_id'] && array_key_exists('invoices', $this->ids)) {
                $modified['documentable_id'] = $this->transformId('invoices', $resource['invoice_id']);
                $modified['documentable_type'] = \App\Models\Invoice::class;
            }

            if (array_key_exists('expense_id', $resource) && $resource['expense_id'] && array_key_exists('expenses', $this->ids)) {
                $modified['documentable_id'] = $this->transformId('expenses', $resource['expense_id']);
                $modified['documentable_type'] = \App\Models\Expense::class;
            }

            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;

            $document = Document::create($modified);

            // $entity = $modified['documentable_type']::find($modified['documentable_id']);
            // $entity->documents()->save($modified);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $this->ids['documents'] = [
                "documents_{$old_user_key}" => [
                    'old' => $resource['id'],
                    'new' => $document->id,
                ],
            ];
        }

        Document::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    private function processPaymentTerms(array $data) :void
    {
        PaymentTerm::unguard();

        $modified = collect($data)->map(function ($item) {
            $item['user_id'] = $this->user->id;
            $item['company_id'] = $this->company->id;

            return $item;
        })->toArray();

        PaymentTerm::insert($modified);

        PaymentTerm::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    private function processCompanyGateways(array $data) :void
    {
        CompanyGateway::unguard();

        $rules = [
            '*.gateway_key' => 'required',
            '*.fees_and_limits' => new ValidCompanyGatewayFeesAndLimitsRule(),
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        foreach ($data as $resource) {
            $modified = $resource;

            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;

            unset($modified['id']);

            if (isset($modified['config'])) {
                $modified['config'] = encrypt($modified['config']);
            }

            if (isset($modified['fees_and_limits'])) {
                $modified['fees_and_limits'] = $this->cleanFeesAndLimits($modified['fees_and_limits']);
            }

            $company_gateway = CompanyGateway::create($modified);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $this->ids['company_gateways'] = [
                "company_gateways_{$old_user_key}" => [
                    'old' => $resource['id'],
                    'new' => $company_gateway->id,
                ],
            ];
        }

        CompanyGateway::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    private function processClientGatewayTokens(array $data) :void
    {
        ClientGatewayToken::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);

            $cgt = ClientGatewayToken::Create($modified);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $this->ids['client_gateway_tokens'] = [
                "client_gateway_tokens_{$old_user_key}" => [
                    'old' => $resource['id'],
                    'new' => $cgt->id,
                ],
            ];
        }

        ClientGatewayToken::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    /**
     * |--------------------------------------------------------------------------
     * | Additional migration methods.
     * |--------------------------------------------------------------------------
     * |
     * | These methods aren't initialized automatically, so they don't depend on
     * | the migration data.
     */

    /**
     * Cloned from App\Http\Requests\User\StoreUserRequest.
     *
     * @param string $data
     * @return User
     */
    public function fetchUser(string $data): User
    {
        $user = MultiDB::hasUser(['email' => $data]);

        if (! $user) {
            $user = UserFactory::create($this->company->account->id);
        }

        return $user;
    }

    /**
     * @param string $resource
     * @param string $old
     * @return int
     * @throws \Exception
     */
    public function transformId(string $resource, string $old): int
    {
        if (! array_key_exists($resource, $this->ids)) {
            throw new \Exception("Resource {$resource} not available.");
        }

        if (! array_key_exists("{$resource}_{$old}", $this->ids[$resource])) {
            throw new \Exception("Missing resource key: {$resource}_{$old}");
        }

        return $this->ids[$resource]["{$resource}_{$old}"]['new'];
    }

    /**
     * Process & handle user_id.
     *
     * @param array $resource
     * @return int|mixed
     * @throws \Exception
     */
    public function processUserId(array $resource)
    {
        if (! array_key_exists('user_id', $resource)) {
            return $this->user->id;
        }

        if (array_key_exists('user_id', $resource) && ! array_key_exists('users', $this->ids)) {
            return $this->user->id;
        }

        return $this->transformId('users', $resource['user_id']);
    }

    public function failed($exception = null)
    {
        info('the job failed');
        info(print_r($exception->getMessage(), 1));
    }
}
