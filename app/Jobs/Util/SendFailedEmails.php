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

use App\Helpers\Email\InvoiceEmail;
use App\Jobs\Invoice\EmailInvoice;
use App\Libraries\MultiDB;
use App\Models\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendFailedEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! config('ninja.db.multi_db_enabled')) {
            $this->processEmails();
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->processEmails();
            }
        }
    }

    private function processEmails()
    {
        \Log::error('processing emails');
        //info("process emails");
        //@todo check that the quota is available for the job

        $email_jobs = SystemLog::where('event_id', SystemLog::EVENT_MAIL_RETRY_QUEUE)->get();

        $email_jobs->each(function ($job) {
            $job_meta_array = $job->log;

            $invitation = $job_meta_array['entity_name']::where('key', $job_meta_array['invitation_key'])->with('contact')->first();

            if ($invitation->invoice) {
                $email_builder = (new InvoiceEmail())->build($invitation, $job_meta_array['reminder_template']);

                if ($invitation->contact->send_email && $invitation->contact->email) {
                    EmailInvoice::dispatch($email_builder, $invitation, $invitation->company);
                }
            }
        });
    }
}
