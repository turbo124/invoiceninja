<?php

namespace Tests\Feature;

use App\DataMapper\CompanySettings;
use App\Models\Company;
use App\Repositories\CompanyRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

        app(CompanyRepository::class)->save([
            'settings' => [
                'currency_id' => 2,
                'send_reminders' => false,
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
        $this->assertSame(['invoice' => 'Invoice'], $stored['translations']);
        $this->assertInstanceOf(CompanySettings::class, $freshCompany->settings);
        $this->assertSame('2', $freshCompany->settings->currency_id);
        $this->assertFalse($freshCompany->settings->send_reminders);
        $this->assertSame(12.5, $freshCompany->settings->default_task_rate);
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
}
