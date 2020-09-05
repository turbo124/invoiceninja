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

use App\Events\Invoice\InvoiceWasEmailed;
use App\Helpers\Email\InvoiceEmail;
use App\Jobs\Invoice\EmailInvoice;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Invoice;
use App\Utils\ClientPortal\CustomMessage\invitation;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        //always make sure you have set the company as this command is being
        //run from the console so we have no awareness of the DB.

        if (! config('ninja.db.multi_db_enabled')) {
            $this->processReminders();
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->processReminders($db);
            }
        }
    }

    private function processReminders($db = null)
    {
        $invoices = Invoice::where('next_send_date', Carbon::today()->format('Y-m-d'))->get();

        $invoices->each(function ($invoice) {
            if ($invoice->isPayable()) {
                $invoice->invitations->each(function ($invitation) use ($invoice) {
                    $email_builder = (new InvoiceEmail())->build($invitation);

                    EmailInvoice::dispatch($email_builder, $invitation, $invoice->company);

                    info("Firing email for invoice {$invoice->number}");
                });

                if ($invoice->invitations->count() > 0) {
                    event(new InvoiceWasEmailed($invoice->invitations->first(), $invoice->company, Ninja::eventVars()));
                }
            } else {
                $invoice->next_send_date = null;
                $invoice->save();
            }
        });
    }
}
