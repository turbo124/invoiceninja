<?php

namespace Tests\Feature;

use App\DataMapper\CompanySettings;
use App\Events\User\UserWasPurged;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserPurgeTest extends TestCase
{
    use DatabaseTransactions;

    public static function membershipStates(): array
    {
        return [
            'active membership' => ['active'],
            'archived membership' => ['archived'],
            'permanently detached membership' => ['detached'],
        ];
    }

    #[DataProvider('membershipStates')]
    public function testPurgePreservesRecordsAcrossAccountCompanies(string $membershipState): void
    {
        // Activity delivery is separate from the transactional ownership transfer.
        Event::fake([UserWasPurged::class]);

        $account = Account::factory()->create();
        $owner = User::factory()->create(['account_id' => $account->id, 'email' => Str::uuid().'@example.com']);
        $departing = User::factory()->create(['account_id' => $account->id, 'email' => Str::uuid().'@example.com']);
        $companies = Company::factory()->count(2)->create(['account_id' => $account->id]);
        $account->update(['default_company_id' => $companies->first()->id]);
        $owner->setCompany($companies->first());

        $records = [];

        foreach ($companies as $company) {
            foreach ([$owner, $departing] as $user) {
                $user->companies()->attach($company->id, [
                    'account_id' => $account->id,
                    'is_owner' => $user->is($owner),
                    'is_admin' => true,
                    'is_locked' => false,
                    'permissions' => '',
                    'notifications' => CompanySettings::notificationAdminDefaults(),
                    'settings' => null,
                ]);
            }

            $client = Client::factory()->create([
                'company_id' => $company->id,
                'user_id' => $departing->id,
                'assigned_user_id' => $departing->id,
            ]);

            foreach ([$departing, $owner] as $paymentOwner) {
                $payment = Payment::factory()->create([
                    'company_id' => $company->id,
                    'client_id' => $client->id,
                    'user_id' => $paymentOwner->id,
                    'assigned_user_id' => $departing->id,
                ]);

                // Both ownership transfer and assignment-only cleanup must include archives.
                if ($paymentOwner->is($owner)) {
                    $payment->deleteQuietly();
                }

                $records[] = [$payment, $payment->fresh()->getRawOriginal('deleted_at')];
            }

            $records[] = [$client, null];
        }

        $membership = $departing->company_users()
            ->where('company_id', $companies->last()->id)
            ->firstOrFail();

        if ($membershipState === 'archived') {
            $membership->delete();
        } elseif ($membershipState === 'detached') {
            // Mirrors UserController::detach: remove membership, retain business records.
            $membership->forceDelete();
        }

        $otherAccount = Account::factory()->create();
        $otherOwner = User::factory()->create(['account_id' => $otherAccount->id, 'email' => Str::uuid().'@example.com']);
        $otherCompany = Company::factory()->create(['account_id' => $otherAccount->id]);
        $unrelated = Client::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherOwner->id,
            'assigned_user_id' => $otherOwner->id,
        ]);
        $unrelatedBefore = $unrelated->fresh()->getRawOriginal();

        (new UserRepository())->purge($departing, $owner);

        $this->assertNull(User::withTrashed()->find($departing->id));

        foreach ($records as [$record, $deletedAt]) {
            $fresh = $record->newQuery()->withTrashed()->find($record->id);
            $this->assertNotNull($fresh, 'Purge must transfer records, not cascade-delete them.');
            $this->assertEquals($owner->id, $fresh->user_id);
            $this->assertNull($fresh->assigned_user_id);
            $this->assertSame($deletedAt, $fresh->getRawOriginal('deleted_at'));
        }

        $this->assertSame($unrelatedBefore, $unrelated->fresh()->getRawOriginal());
        Event::assertDispatched(UserWasPurged::class);
    }
}
