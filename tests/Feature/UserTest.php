<?php

namespace Feature;

use App\Factory\CompanyUserFactory;
use App\Factory\UserFactory;
use App\Http\Middleware\PasswordProtection;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\UserController
 */
class UserTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        $this->makeTestData();

        Model::reguard();

        $this->withoutMiddleware(
            ThrottleRequests::class,
            PasswordProtection::class
        );
    }

    public function testUserList()
    {
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
                'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/users');

        $response->assertStatus(200);
    }

    public function testUserStore()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                    'is_admin' => false,
                    'is_owner' => false,
                    'permissions' => 'create_client,create_invoice',
                ],
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
                'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->post('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['company_user']);
    }

    public function testUserAttachAndDetach()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $user = UserFactory::create($this->account->id);
        $user->first_name = 'Test';
        $user->last_name = 'Palloni';
        $user->save();

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
                'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->post('/api/v1/users/'.$this->encodePrimaryKey($user->id).'/attach_to_company?include=company_user');

        $response->assertStatus(200);

        $this->assertNotNull($user->company_user);
        $this->assertEquals($user->company_user->company_id, $this->company->id);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
                    'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->delete('/api/v1/users/'.$this->encodePrimaryKey($user->id).'/detach_from_company?include=company_user');

        $response->assertStatus(200);

        $cu = CompanyUser::whereUserId($user->id)->whereCompanyId($this->company->id)->first();
        $ct = CompanyToken::whereUserId($user->id)->whereCompanyId($this->company->id)->first();

        $this->assertNull($cu);
        $this->assertNull($ct);
        $this->assertNotNull($user);
    }

    public function testAttachUserToMultipleCompanies()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        /* Create New Company */
        $company2 = factory(\App\Models\Company::class)->create([
            'account_id' => $this->account->id,
        ]);

        $company_token = new CompanyToken;
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $company2->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = \Illuminate\Support\Str::random(64);
        $company_token->save();

        /*Manually link this user to the company*/
        $cu = CompanyUserFactory::create($this->user->id, $company2->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->save();

        /*Create New Blank User and Attach to Company 2*/
        $new_user = UserFactory::create($this->account->id);
        $new_user->first_name = 'Test';
        $new_user->last_name = 'Palloni';
        $new_user->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
        ])->post('/api/v1/users/'.$this->encodePrimaryKey($new_user->id).'/attach_to_company?include=company_user');

        $response->assertStatus(200);

        $this->assertNotNull($new_user->company_user);
        $this->assertEquals($new_user->company_user->company_id, $company2->id);

        /*Create brand new user manually with company_user object and attach to a different company*/
        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob@good.ole.boys.co2.com',
            'company_user' => [
                    'is_admin' => false,
                    'is_owner' => false,
                    'permissions' => 'create_client,create_invoice',
                ],
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $company_token->token,
            ])->post('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['company_user']);
        $this->assertFalse($arr['data']['company_user']['is_admin']);
        $this->assertFalse($arr['data']['company_user']['is_owner']);
        $this->assertEquals($arr['data']['company_user']['permissions'], 'create_client,create_invoice');

        $user = User::whereEmail('bob@good.ole.boys.co2.com')->first();

        $this->assertNotNull($user);

        $cu = CompanyUser::whereUserId($user->id)->whereCompanyId($company2->id)->first();

        $this->assertNotNull($cu);

        /*Update the user permissions of this user*/
        $data = [
            'first_name' => 'Captain',
            'last_name' => 'Morgain',
            'email' => 'bob@good.ole.boys.co2.com',
            'company_user' => [
                    'is_admin' => true,
                    'is_owner' => false,
                    'permissions' => 'create_invoice,create_invoice',
                ],
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $company_token->token,
                'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            ])->put('/api/v1/users/'.$this->encodePrimaryKey($user->id).'?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['company_user']);
        $this->assertTrue($arr['data']['company_user']['is_admin']);
        $this->assertFalse($arr['data']['company_user']['is_owner']);
        $this->assertEquals($arr['data']['company_user']['permissions'], 'create_invoice,create_invoice');
    }
}
