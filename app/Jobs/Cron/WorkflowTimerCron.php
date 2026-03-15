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

namespace App\Jobs\Cron;

use App\Libraries\MultiDB;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class WorkflowTimerCron implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        if (! config('ninja.db.multi_db_enabled')) {
            $engine = new WorkflowEngine();
            $engine->processTimedOutRuns();

            return;
        }

        // Multi-DB: process each database
        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            $engine = new WorkflowEngine();
            $engine->processTimedOutRuns();
        }
    }
}
