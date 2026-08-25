<?php

namespace Tests\Feature;

use App\DataMapper\CompanySettings;
use App\Jobs\Company\CompanyTaxRate;
use App\Models\Company;
use App\Repositories\CompanyRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use stdClass;
use Tests\MockAccountData;
use Tests\TestCase;

class CompanySettingsCastIntegrationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testCompanyRepositoryPersistsACompleteTypedObjectThroughTheRegisteredCast(): void
    {
        $company = Company::query()->findOrFail($this->company->id);
        $company->settings->custom_value1 = 'preserved';
        $company->save();

        app(CompanyRepository::class)->save([
            'settings' => [
                'currency_id' => 2,
                'send_reminders' => 'false',
                'default_task_rate' => '12.5',
                'translations' => [
                    'invoice' => 'Invoice',
                    'quote' => '',
                ],
            ],
        ], $company);

        $stored = json_decode($company->getRawOriginal('settings'), true, 512, JSON_THROW_ON_ERROR);
        $freshCompany = Company::query()->findOrFail($company->id);

        $this->assertCount(count(CompanySettings::$casts) + 1, $stored);
        $this->assertSame('2', $stored['currency_id']);
        $this->assertFalse($stored['send_reminders']);
        $this->assertSame(12.5, $stored['default_task_rate']);
        $this->assertSame('preserved', $stored['custom_value1']);
        $this->assertSame(['invoice' => 'Invoice'], $stored['translations']);
        $this->assertInstanceOf(CompanySettings::class, $company->settings);
        $this->assertInstanceOf(CompanySettings::class, $freshCompany->settings);
        $this->assertSame('2', $freshCompany->settings->currency_id);
        $this->assertFalse($freshCompany->settings->send_reminders);
        $this->assertSame(12.5, $freshCompany->settings->default_task_rate);
        $this->assertSame('preserved', $freshCompany->settings->custom_value1);
        $this->assertSame('Invoice', $freshCompany->settings->translations->invoice);
    }

    public function testCanonicalCompanySettingsRemainCleanWhileTypedMutationBecomesDirty(): void
    {
        $company = Company::query()->findOrFail($this->company->id);
        $company->settings = CompanySettings::defaults();
        $company->save();

        $company = Company::query()->findOrFail($company->id);

        $this->assertFalse($company->isDirty('settings'));

        $company->settings;

        $this->assertFalse($company->isDirty('settings'));

        $company->settings->currency_id = '2';

        $this->assertTrue($company->isDirty('settings'));

        $company->save();
        $company = Company::query()->findOrFail($company->id);

        $this->assertFalse($company->isDirty('settings'));
        $this->assertSame('2', $company->settings->currency_id);
    }

    public function testEmptyRepositorySettingsInputDoesNotResetPersistedSettings(): void
    {
        $company = Company::query()->findOrFail($this->company->id);
        $company->settings->custom_value1 = 'preserved';
        $company->save();

        app(CompanyRepository::class)->save([
            'settings' => [],
        ], $company);

        $this->assertSame('preserved', $company->fresh()->settings->custom_value1);
    }

    public function testCompanyRepositorySavesOnlyOnceWhenSettingsArePresent(): void
    {
        $company = Company::query()->findOrFail($this->company->id);
        $savedEvent = 'eloquent.saved: '.Company::class;

        Event::fake([$savedEvent]);

        app(CompanyRepository::class)->save([
            'settings' => [
                'currency_id' => 2,
            ],
            'smtp_username' => 'single-save@example.com',
        ], $company);

        Event::assertDispatchedTimes($savedEvent, 1);
        $this->assertSame('2', $company->fresh()->settings->currency_id);
        $this->assertSame('single-save@example.com', $company->fresh()->smtp_username);
    }

    public function testBackupSettingsSaverPreservesTheFormerPersistencePath(): void
    {
        $settings = $this->company->fresh()->settings;
        $settings->currency_id = 2;
        $settings->default_task_rate = 12.5;

        $this->company->backupSaveSettings($settings, $this->company);

        $settings = $this->company->fresh()->settings;

        $this->assertSame('2', $settings->currency_id);
        $this->assertSame(12.5, $settings->default_task_rate);
    }

    public function testSaveSettingsRetainsAnAlreadyTypedSettingsInstance(): void
    {
        $company = Company::query()->findOrFail($this->company->id);
        $settings = $company->settings;
        $settings->currency_id = '2';

        $company->saveSettings($settings, $company);

        $this->assertSame($settings, $company->settings);
        $this->assertSame('2', $company->fresh()->settings->currency_id);
    }

    public function testNullRawCompanyValuesUseTypedDefaultsAndSerializeCompletely(): void
    {
        $defaults = CompanySettings::defaults();
        $company = Company::query()->findOrFail($this->company->id);
        $company->setRawAttributes([
            ...$company->getAttributes(),
            'settings' => json_encode([
                'currency_id' => null,
                'send_reminders' => null,
                'translations' => null,
            ], JSON_THROW_ON_ERROR),
        ], true);
        $arraySettings = $company->toArray()['settings'];

        $this->assertSame($defaults->currency_id, $company->settings->currency_id);
        $this->assertSame($defaults->send_reminders, $company->settings->send_reminders);
        $this->assertInstanceOf(stdClass::class, $company->settings->translations);
        $this->assertInstanceOf(stdClass::class, $arraySettings);
        $this->assertCount(count(CompanySettings::$casts) + 1, get_object_vars($arraySettings));
    }

    public function testSaveSettingsDispatchesTaxRefreshAfterTheNewSettingsAreSaved(): void
    {
        $company = Company::query()->findOrFail($this->company->id);
        $company->account->plan = 'enterprise';
        $company->account->save();
        $company->calculate_taxes = true;
        $company->settings = [
            'country_id' => '840',
            'postal_code' => '10001',
        ];
        $company->save();

        Queue::fake();

        $company->saveSettings([
            'postal_code' => '90210',
        ], $company);

        Queue::assertPushed(
            CompanyTaxRate::class,
            static fn (CompanyTaxRate $job): bool => $job->company->settings->postal_code === '90210',
        );
        $this->assertSame('90210', $company->fresh()->settings->postal_code);
    }

    public function testSaveSettingsDispatchesTaxRefreshWhenTaxCalculationIsEnabled(): void
    {
        $company = Company::query()->findOrFail($this->company->id);
        $company->account->plan = 'enterprise';
        $company->account->save();
        $company->calculate_taxes = false;
        $company->settings = [
            'country_id' => '840',
            'postal_code' => '10001',
        ];
        $company->save();

        Queue::fake();

        $company->calculate_taxes = true;
        $company->saveSettings([], $company);

        Queue::assertPushed(CompanyTaxRate::class);
        $this->assertSame(1, $company->fresh()->calculate_taxes);
    }
}
