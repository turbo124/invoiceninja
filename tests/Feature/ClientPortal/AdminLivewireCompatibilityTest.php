<?php

namespace Tests\Feature\ClientPortal;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class AdminLivewireCompatibilityTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->actingAs($this->user);
    }

    public static function tableComponents(): array
    {
        return [
            ['admin::accounts-table'],
            ['admin::companies-table'],
            ['admin::users-table'],
        ];
    }

    public function testAllAdminComponentNamesResolve(): void
    {
        foreach ([
            'accounts-table', 'companies-table', 'users-table', 'system-logs-table',
            'create-campaign-form', 'companies.company-form', 'accounts.account-form',
            'accounts.subscription-form', 'users.delete-user', 'licenses.create-license-form',
            'licenses.show-license-form', 'licenses-table',
        ] as $component) {
            $this->assertTrue(Livewire::exists('admin::'.$component), $component);
        }
    }

    public function testAdminLicenseAndLogTablesRenderAndUpdate(): void
    {
        Livewire::test('admin::licenses-table')
            ->assertOk()
            ->set('filter', 'livewire-upgrade')
            ->assertOk();

        Livewire::test('admin::system-logs-table')
            ->assertOk()
            ->set('per_page', 10)
            ->assertOk();
    }

    #[DataProvider('tableComponents')]
    public function testAdminTablesRenderFilterSortAndPaginate(string $component): void
    {
        Livewire::test($component, ['db' => $this->company->db])
            ->assertOk()
            ->set('search_query', (string) $this->company->id)
            ->assertOk()
            ->call('sortBy', 'id')
            ->call('nextPage')
            ->assertOk();
    }

    public function testAdminCompanyFormUpdatesTheSelectedDatabase(): void
    {
        Livewire::test('admin::companies.company-form', [
            'company_id' => $this->company->id,
            'db' => $this->company->db,
        ])
            ->assertOk()
            ->set('is_disabled', true)
            ->call('updateCompany')
            ->assertHasNoErrors();

        $this->assertSame(1, (int) $this->company->fresh()->is_disabled);
    }

    public function testAdminAccountFormUpdatesTheSelectedDatabase(): void
    {
        Livewire::test('admin::accounts.account-form', [
            'account_id' => $this->account->id,
            'db' => $this->company->db,
        ])
            ->assertOk()
            ->set('e_invoice_quota', 123)
            ->call('updateAccount')
            ->assertHasNoErrors();

        $this->assertSame(123, $this->account->fresh()->e_invoice_quota);
    }

    public function testAdminUserActionsUseTheSelectedDatabase(): void
    {
        Livewire::test('admin::users.delete-user', [
            'user_id' => $this->user->id,
            'db' => $this->company->db,
        ])
            ->assertOk()
            ->call('verifyUser')
            ->assertOk();

        $this->assertNotNull($this->user->fresh()->email_verified_at);
    }
}
