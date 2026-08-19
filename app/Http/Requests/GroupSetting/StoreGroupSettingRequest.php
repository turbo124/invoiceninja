<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\GroupSetting;

use App\DataMapper\CompanySettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Models\Account;
use App\Models\GroupSetting;

class StoreGroupSettingRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', GroupSetting::class) && $user->account->hasFeature(Account::FEATURE_API);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules['name'] = 'required|unique:group_settings,name,null,null,company_id,' . $user->companyId();

        $rules['settings'] = new ValidClientGroupSettingsRule();

        return $rules;
    }

    public function withValidator($validator)
    {

        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        
        $validator->after(function ($validator) {

            $user = auth()->user();
            $company = $user->company();

            if (isset($this->settings['lock_invoices']) && $company->verifactuEnabled() && $this->settings['lock_invoices'] != 'when_sent') {
                $validator->errors()->add('settings.lock_invoices', 'Locked Invoices Cannot Be Disabled');
            }

        });
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('settings', $input)) {
            $input['settings'] = $this->filterSaveableSettings($input['settings']);
        } else {
            $input['settings'] = [];
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'settings' => 'settings must be a valid json structure',
        ];
    }

    /**
     * For the hosted platform, we restrict the feature settings.
     *
     * This method will trim the company settings object
     * down to the free plan setting properties which
     * are saveable
     *
     * @param  object $settings
     * @return array $settings
     */
    private function filterSaveableSettings($settings): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $settings = (array) $settings;
        unset($settings['translations'], $settings['pdf_variables']);

        if (! $user->account->isFreeHostedClient()) {
            return $settings;
        }

        $saveable_casts = CompanySettings::$free_plan_casts;

        foreach ($settings as $key => $value) {
            if (! array_key_exists($key, $saveable_casts)) {
                unset($settings[$key]);
            }
        }

        return $settings;
    }

}
