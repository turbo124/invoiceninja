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

namespace App\Utils\Traits;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Timezone;
use Illuminate\Support\Carbon;

/**
 * Class GeneratesCounter.
 */
trait GeneratesCounter
{
    //todo in the form validation, we need to ensure that if a prefix and pattern is set we throw a validation error,
    //only one type is allow else this will cause confusion to the end user

    /**
     * Gets the next invoice number.
     *
     * @param      \App\Models\Client  $client  The client
     *
     * @return     string              The next invoice number.
     */
    public function getNextInvoiceNumber(Client $client) :string
    {
        //Reset counters if enabled
        $this->resetCounters($client);

        //todo handle if we have specific client patterns in the future
        $pattern = $client->getSetting('invoice_number_pattern');
        //Determine if we are using client_counters
        if (strpos($pattern, 'clientCounter')) {
            if (property_exists($client->settings, 'invoice_number_counter')) {
                $counter = $client->settings->invoice_number_counter;
            } else {
                $counter = 1;
            }

            $counter_entity = $client;
        } elseif (strpos($pattern, 'groupCounter')) {
            $counter = $client->group_settings->invoice_number_counter;
            $counter_entity = $client->group_settings;
        } else {
            $counter = $client->company->settings->invoice_number_counter;
            $counter_entity = $client->company;
        }

        //Return a valid counter
        $pattern = $client->getSetting('invoice_number_pattern');
        $padding = $client->getSetting('counter_padding');

        $invoice_number = $this->checkEntityNumber(Invoice::class, $client, $counter, $padding, $pattern);

        $this->incrementCounter($counter_entity, 'invoice_number_counter');

        return $invoice_number;
    }

    /**
     * Gets the next credit number.
     *
     * @param      \App\Models\Client  $client  The client
     *
     * @return     string              The next credit number.
     */
    public function getNextCreditNumber(Client $client) :string
    {
        //Reset counters if enabled
        $this->resetCounters($client);

        //todo handle if we have specific client patterns in the future
        $pattern = $client->getSetting('credit_number_pattern');
        //Determine if we are using client_counters
        if (strpos($pattern, 'clientCounter')) {
            $counter = $client->settings->credit_number_counter;
            $counter_entity = $client;
        } elseif (strpos($pattern, 'groupCounter')) {
            $counter = $client->group_settings->credit_number_counter;
            $counter_entity = $client->group_settings;
        } else {
            $counter = $client->company->settings->credit_number_counter;
            $counter_entity = $client->company;
        }

        //Return a valid counter
        $pattern = $client->getSetting('credit_number_pattern');
        $padding = $client->getSetting('counter_padding');

        $credit_number = $this->checkEntityNumber(Credit::class, $client, $counter, $padding, $pattern);

        $this->incrementCounter($client->company, 'credit_number_counter');

        return $credit_number;
    }

    public function getNextQuoteNumber(Client $client)
    {
        //Reset counters if enabled
        $this->resetCounters($client);

        $used_counter = 'quote_number_counter';

        if ($this->hasSharedCounter($client)) {
            $used_counter = 'invoice_number_counter';
        }

        //todo handle if we have specific client patterns in the future
        $pattern = $client->getSetting('quote_number_pattern');
        //Determine if we are using client_counters
        if (strpos($pattern, 'clientCounter')) {
            $counter = $client->settings->{$used_counter};
            $counter_entity = $client;
        } elseif (strpos($pattern, 'groupCounter')) {
            $counter = $client->group_settings->{$used_counter};
            $counter_entity = $client->group_settings;
        } else {
            $counter = $client->company->settings->{$used_counter};
            $counter_entity = $client->company;
        }

        //Return a valid counter
        $pattern = $client->getSetting('quote_number_pattern');
        $padding = $client->getSetting('counter_padding');

        $quote_number = $this->checkEntityNumber(Quote::class, $client, $counter, $padding, $pattern);

        $this->incrementCounter($counter_entity, $used_counter);

        return $quote_number;
    }

    public function getNextRecurringInvoiceNumber(Client $client)
    {

        //Reset counters if enabled
        $this->resetCounters($client);

        $is_client_counter = false;

        //todo handle if we have specific client patterns in the future
        $pattern = $client->company->settings->invoice_number_pattern;

        //Determine if we are using client_counters
        if (strpos($pattern, 'client_counter') === false) {
            $counter = $client->company->settings->invoice_number_counter;
        } else {
            $counter = $client->settings->invoice_number_counter;
            $is_client_counter = true;
        }

        //Return a valid counter
        $pattern = '';
        $padding = $client->getSetting('counter_padding');
        $invoice_number = $this->checkEntityNumber(Invoice::class, $client, $counter, $padding, $pattern);
        $invoice_number = $this->prefixCounter($invoice_number, $client->getSetting('recurring_number_prefix'));

        //increment the correct invoice_number Counter (company vs client)
        if ($is_client_counter) {
            $this->incrementCounter($client, 'invoice_number_counter');
        } else {
            $this->incrementCounter($client->company, 'invoice_number_counter');
        }

        return $invoice_number;
    }

    /**
     * Payment Number Generator.
     * @return string The payment number
     */
    public function getNextPaymentNumber(Client $client) :string
    {

        //Reset counters if enabled
        $this->resetCounters($client);

        $is_client_counter = false;

        //todo handle if we have specific client patterns in the future
        $pattern = $client->company->settings->payment_number_pattern;

        //Determine if we are using client_counters
        if (strpos($pattern, 'client_counter') === false) {
            $counter = $client->company->settings->payment_number_counter;
        } else {
            $counter = $client->settings->payment_number_counter;
            $is_client_counter = true;
        }

        //Return a valid counter
        $pattern = '';
        $padding = $client->getSetting('counter_padding');
        $payment_number = $this->checkEntityNumber(Payment::class, $client, $counter, $padding, $pattern);

        //increment the correct invoice_number Counter (company vs client)
        if ($is_client_counter) {
            $this->incrementCounter($client, 'payment_number_counter');
        } else {
            $this->incrementCounter($client->company, 'payment_number_counter');
        }

        return (string) $payment_number;
    }

    /**
     * Gets the next client number.
     *
     * @param      \App\Models\Client  $client  The client
     *
     * @return     string              The next client number.
     */
    public function getNextClientNumber(Client $client) :string
    {
        //Reset counters if enabled
        $this->resetCounters($client);

        $counter = $client->getSetting('client_number_counter');
        $setting_entity = $client->getSettingEntity('client_number_counter');

        $client_number = $this->checkEntityNumber(Client::class, $client, $counter, $client->getSetting('counter_padding'), $client->getSetting('client_number_pattern'));

        $this->incrementCounter($setting_entity, 'client_number_counter');

        return $client_number;
    }

    /**
     * Determines if it has shared counter.
     *
     * @param      \App\Models\Client  $client  The client
     *
     * @return     bool             True if has shared counter, False otherwise.
     */
    public function hasSharedCounter(Client $client) : bool
    {
        return (bool) $client->getSetting('shared_invoice_quote_counter');
    }

    /**
     * Checks that the number has not already been used.
     *
     * @param      Collection  $entity   The entity ie App\Models\Client, Invoice, Quote etc
     * @param      int  $counter  The counter
     * @param      int   $padding  The padding
     *
     * @return     string   The padded and prefixed invoice number
     */
    private function checkEntityNumber($class, $client, $counter, $padding, $pattern)
    {
        $check = false;

        do {
            $number = $this->padCounter($counter, $padding);

            $number = $this->applyNumberPattern($client, $number, $pattern);

            if ($class == Invoice::class || $class == RecurringInvoice::class) {
                $check = $class::whereCompanyId($client->company_id)->whereNumber($number)->withTrashed()->first();
            } elseif ($class == Client::class) {
                $check = $class::whereCompanyId($client->company_id)->whereIdNumber($number)->withTrashed()->first();
            } elseif ($class == Credit::class) {
                $check = $class::whereCompanyId($client->company_id)->whereNumber($number)->withTrashed()->first();
            } elseif ($class == Quote::class) {
                $check = $class::whereCompanyId($client->company_id)->whereNumber($number)->withTrashed()->first();
            } elseif ($class == Payment::class) {
                $check = $class::whereCompanyId($client->company_id)->whereNumber($number)->withTrashed()->first();
            }

            $counter++;
        } while ($check);

        return $number;
    }

    /**
     * Saves counters at both the company and client level.
     *
     * @param      \App\Models\Client                 $client        The client
     * @param      \App\Models\Client|int|string  $counter_name  The counter name
     */
    private function incrementCounter($entity, string $counter_name) :void
    {
        $settings = $entity->settings;

        if ($counter_name == 'invoice_number_counter' && ! property_exists($entity->settings, 'invoice_number_counter')) {
            $settings->invoice_number_counter = 0;
        }

        $settings->{$counter_name} = $settings->{$counter_name} + 1;

        $entity->settings = $settings;

        $entity->save();
    }

    private function prefixCounter($counter, $prefix) : string
    {
        if (strlen($prefix) == 0) {
            return $counter;
        }

        return  $prefix.$counter;
    }

    /**
     * Pads a number with leading 000000's.
     *
     * @param      int  $counter  The counter
     * @param      int  $padding  The padding
     *
     * @return     int  the padded counter
     */
    private function padCounter($counter, $padding) :string
    {
        return str_pad($counter, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * If we are using counter reset,
     * check if we need to reset here.
     *
     * @param  Client $client client entity
     * @return void
     */
    private function resetCounters(Client $client)
    {
        $timezone = Timezone::find($client->getSetting('timezone_id'));

        $reset_date = Carbon::parse($client->getSetting('reset_counter_date'), $timezone->name);

        if (! $reset_date->isToday() || ! $client->getSetting('reset_counter_date')) {
            return false;
        }

        switch ($client->company->reset_counter_frequency_id) {
            case RecurringInvoice::FREQUENCY_WEEKLY:
                $reset_date->addWeek();
                break;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                $reset_date->addWeeks(2);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                $reset_date->addWeeks(4);
                break;
            case RecurringInvoice::FREQUENCY_MONTHLY:
                $reset_date->addMonth();
                break;
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                $reset_date->addMonths(2);
                break;
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                $reset_date->addMonths(3);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                $reset_date->addMonths(4);
                break;
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                $reset_date->addMonths(6);
                break;
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                $reset_date->addYear();
                break;
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                $reset_date->addYears(2);
                break;
        }

        $settings = $client->company->settings;
        $settings->reset_counter_date = $reset_date->format($client->date_format());
        $settings->invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->credit_number_counter = 1;

        $client->company->settings = $settings;
        $client->company->save();
    }

    /**
     * { function_description }.
     *
     * @param      \App\Models\Client  $client   The client
     * @param      string              $counter  The counter
     * @param      null|string             $pattern  The pattern
     *
     * @return     string              ( description_of_the_return_value )
     */
    private function applyNumberPattern(Client $client, string $counter, $pattern) :string
    {
        if (! $pattern) {
            return $counter;
        }

        $search = ['{$year}'];
        $replace = [date('Y')];

        $search[] = '{$counter}';
        $replace[] = $counter;

        $search[] = '{$clientCounter}';
        $replace[] = $counter;

        $search[] = '{$groupCounter}';
        $replace[] = $counter;

        if (strstr($pattern, '{$user_id}')) {
            $user_id = $client->user_id ? $client->user_id : 0;
            $search[] = '{$user_id}';
            $replace[] = str_pad(($user_id), 2, '0', STR_PAD_LEFT);
        }

        $matches = false;
        preg_match('/{\$date:(.*?)}/', $pattern, $matches);
        if (count($matches) > 1) {
            $format = $matches[1];
            $search[] = $matches[0];

            /* The following adjusts for the company timezone - may bork tests depending on the time of day the tests are run!!!!!!*/
            $date = Carbon::now($client->company->timezone()->name)->format($format);
            $replace[] = str_replace($format, $date, $matches[1]);
        }

        $search[] = '{$custom1}';
        $replace[] = $client->custom_value1;

        $search[] = '{$custom2}';
        $replace[] = $client->custom_value2;

        $search[] = '{$custom3}';
        $replace[] = $client->custom_value3;

        $search[] = '{$custom4}';
        $replace[] = $client->custom_value4;

        $search[] = '{$id_number}';
        $replace[] = $client->id_number;

        return str_replace($search, $replace, $pattern);
    }
}
