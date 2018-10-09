<?php

namespace App\Listeners;

use Auth;
use Utils;
use App\Events\UserSignedUp;
use App\Ninja\Mailers\UserMailer;
use App\Ninja\Repositories\AccountRepository;

/**
 * Class HandleUserSignedUp.
 */
class HandleUserSignedUp
{
    /**
     * @var AccountRepository
     */
    protected $accountRepo;

    /**
     * @var UserMailer
     */
    protected $userMailer;

    /**
     * Create the event handler.
     *
     * @param AccountRepository $accountRepo
     * @param UserMailer        $userMailer
     */
    public function __construct(AccountRepository $accountRepo, UserMailer $userMailer)
    {
        $this->accountRepo = $accountRepo;
        $this->userMailer = $userMailer;
    }

    /**
     * Handle the event.
     *
     * @param UserSignedUp $event
     */
    public function handle(UserSignedUp $event)
    {
        $user = Auth::user();

        if (Utils::isNinjaProd() && ! $user->confirmed) {
            $this->userMailer->sendConfirmation($user);
        } elseif (Utils::isNinjaDev()) {
            // do nothing
        } else {
            $this->accountRepo->registerNinjaUser($user);
        }

        session([SESSION_COUNTER => -1]);
        session([SESSION_DB_SERVER => config('database.default')]);
    }
}
