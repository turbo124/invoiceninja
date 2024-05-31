<?php

use App\Jobs\Cron\AutoBillCron;
use App\Jobs\Cron\RecurringExpensesCron;
use App\Jobs\Cron\RecurringInvoicesCron;
use App\Jobs\Cron\SubscriptionCron;
use App\Jobs\Cron\UpdateCalculatedFields;
use App\Jobs\Invoice\InvoiceCheckLateWebhook;
use App\Jobs\Ninja\AdjustEmailQuota;
use App\Jobs\Ninja\BankTransactionSync;
use App\Jobs\Ninja\CheckACHStatus;
use App\Jobs\Ninja\CompanySizeCheck;
use App\Jobs\Ninja\QueueSize;
use App\Jobs\Ninja\SystemMaintenance;
use App\Jobs\Ninja\TaskScheduler;
use App\Jobs\Quote\QuoteCheckExpired;
use App\Jobs\Subscription\CleanStaleInvoiceOrder;
use App\Jobs\Util\DiskCleanup;
use App\Jobs\Util\ReminderJob;
use App\Jobs\Util\SchedulerCheck;
use App\Jobs\Util\UpdateExchangeRates;
use App\Jobs\Util\VersionCheck;
use App\Models\Account;
use App\Utils\Ninja;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/


/* Check for the latest version of Invoice Ninja */
Schedule::job(new VersionCheck())->daily();

/* Returns the number of jobs in the queue */
Schedule::job(new QueueSize())->everyFiveMinutes()->withoutOverlapping()->name('queue-size-job')->onOneServer();

/* Send reminders */
Schedule::job(new ReminderJob())->hourly()->withoutOverlapping()->name('reminder-job')->onOneServer();

/* Sends recurring invoices*/
Schedule::job(new RecurringInvoicesCron())->hourly()->withoutOverlapping()->name('recurring-invoice-job')->onOneServer();

/* Checks for scheduled tasks */
Schedule::job(new TaskScheduler())->hourlyAt(10)->withoutOverlapping()->name('task-scheduler-job')->onOneServer();

/* Stale Invoice Cleanup*/
Schedule::job(new CleanStaleInvoiceOrder())->hourlyAt(30)->withoutOverlapping()->name('stale-invoice-job')->onOneServer();

/* Stale Invoice Cleanup*/
Schedule::job(new UpdateCalculatedFields())->hourlyAt(40)->withoutOverlapping()->name('update-calculated-fields-job')->onOneServer();

/* Checks for large companies and marked them as is_large */
Schedule::job(new CompanySizeCheck())->dailyAt('23:20')->withoutOverlapping()->name('company-size-job')->onOneServer();

/* Pulls in the latest exchange rates */
Schedule::job(new UpdateExchangeRates())->dailyAt('23:30')->withoutOverlapping()->name('exchange-rate-job')->onOneServer();

/* Runs cleanup code for subscriptions */
Schedule::job(new SubscriptionCron())->dailyAt('00:01')->withoutOverlapping()->name('subscription-job')->onOneServer();

/* Sends recurring expenses*/
Schedule::job(new RecurringExpensesCron())->dailyAt('00:10')->withoutOverlapping()->name('recurring-expense-job')->onOneServer();

/* Checks the status of the scheduler */
Schedule::job(new SchedulerCheck())->dailyAt('01:10')->withoutOverlapping();

/* Checks and cleans redundant files */
Schedule::job(new DiskCleanup())->dailyAt('02:10')->withoutOverlapping()->name('disk-cleanup-job')->onOneServer();

/* Performs system maintenance such as pruning the backup table */
Schedule::job(new SystemMaintenance())->sundays()->at('02:30')->withoutOverlapping()->name('system-maintenance-job')->onOneServer();

/* Fires notifications for expired Quotes */
Schedule::job(new QuoteCheckExpired())->dailyAt('05:10')->withoutOverlapping()->name('quote-expired-job')->onOneServer();

/* Performs auto billing */
Schedule::job(new AutoBillCron())->dailyAt('06:20')->withoutOverlapping()->name('auto-bill-job')->onOneServer();

/* Fires webhooks for overdue Invoice */
Schedule::job(new InvoiceCheckLateWebhook())->dailyAt('07:00')->withoutOverlapping()->name('invoice-overdue-job')->onOneServer();

/* Pulls in bank transactions from third party services */
Schedule::job(new BankTransactionSync())->everyFourHours()->withoutOverlapping()->name('bank-trans-sync-job')->onOneServer();

if (Ninja::isSelfHost()) {
    Schedule::call(function () {
        Account::query()->whereNotNull('id')->update(['is_scheduler_running' => true]);
    })->everyFiveMinutes();
}

/* Run hosted specific jobs */
if (Ninja::isHosted()) {
    Schedule::job(new AdjustEmailQuota())->dailyAt('23:30')->withoutOverlapping();

    /* Checks ACH verification status and updates state to authorize when verified */
    Schedule::job(new CheckACHStatus())->everySixHours()->withoutOverlapping()->name('ach-status-job')->onOneServer();

    Schedule::command('ninja:check-data --database=db-ninja-01')->dailyAt('02:10')->withoutOverlapping()->name('check-data-db-1-job')->onOneServer();

    Schedule::command('ninja:check-data --database=db-ninja-02')->dailyAt('02:20')->withoutOverlapping()->name('check-data-db-2-job')->onOneServer();

    Schedule::command('ninja:s3-cleanup')->dailyAt('23:15')->withoutOverlapping()->name('s3-cleanup-job')->onOneServer();
}

if (config('queue.default') == 'database' && Ninja::isSelfHost() && config('ninja.internal_queue_enabled') && ! config('ninja.is_docker')) {
    Schedule::command('queue:work database --stop-when-empty --memory=256')->everyMinute()->withoutOverlapping();

    Schedule::command('queue:restart')->everyFiveMinutes()->withoutOverlapping();
}
