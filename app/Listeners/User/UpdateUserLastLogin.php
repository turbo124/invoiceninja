<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\User;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\User\UserLoggedIn;
use App\Models\SystemLog;
use App\Utils\Ninja;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class UpdateUserLastLogin implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     */
    public function handle($event): void
    {
        MultiDB::setDb($event->company->db);

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($event->company->settings));
        App::setLocale($event->company->locale());

        $user = $event->user;
        $user->last_login = now();
        $user->save();

        $event_vars = $event->event_vars;
        $ip = array_key_exists('ip', $event->event_vars) ? $event->event_vars['ip'] : 'IP address not resolved';
        $key = "user_logged_in_{$user->id}{$event->company->db}";

        if ($user->ip != $ip && is_null(Cache::get($key)) && $user->user_logged_in_notification) {
            $nmo = new NinjaMailerObject();
            $nmo->mailable = new UserLoggedIn($user, $user->account->companies->first(), $ip);
            $nmo->company = $user->account->companies->first();
            $nmo->settings = $user->account->companies->first()->settings;
            $nmo->to_user = $user;
            NinjaMailerJob::dispatch($nmo, true);

            $user->ip = $ip;
            $user->save();
        }

        Cache::put($key, true, 60 * 24);
        $arr = json_encode(['ip' => $ip]);
        $arr = ctrans('texts.new_login_detected')." {$ip}";

        SystemLogger::dispatch(
            $arr,
            SystemLog::CATEGORY_SECURITY,
            SystemLog::EVENT_USER,
            SystemLog::TYPE_LOGIN_SUCCESS,
            null,
            $event->company,
        );
    }
}
