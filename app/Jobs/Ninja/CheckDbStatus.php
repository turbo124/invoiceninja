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

namespace App\Jobs\Ninja;

use App\Helpers\Email\InvoiceEmail;
use App\Jobs\Invoice\EmailInvoice;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Turbo124\Beacon\Jobs\Database\MySQL\DbStatus;

class CheckDbStatus implements ShouldQueue
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
        DbStatus::dispatchNow('db-ninja-01', 'db.status.db-ninja-01');
        DbStatus::dispatchNow('db-ninja-02', 'db.status.db-ninja-02');
    }
}
