<?php

namespace Tests\Feature;

use App\Jobs\Account\CreateAccount;
use App\Jobs\Util\ReminderJob;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Jobs\Util\ReminderJob
 */
class ReminderTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testReminderQueryCatchesDate()
    {
        $this->invoice->next_send_date = now()->format('Y-m-d');
        $this->invoice->save();

        $invoices = Invoice::where('next_send_date', Carbon::today())->get();

        $this->assertEquals(1, $invoices->count());
    }

    public function testReminderHits()
    {
        $this->invoice->date = now()->format('Y-m-d');
        $this->invoice->due_date = Carbon::now()->addDays(30)->format('Y-m-d');

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 7;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 1;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 1;

        $this->company->settings = $settings;
        $this->invoice->service()->markSent();
        $this->invoice->setReminder($settings);

        $this->assertEquals($this->invoice->next_send_date, Carbon::now()->addDays(7)->format('Y-m-d'));

        ReminderJob::dispatchNow();
    }
}
