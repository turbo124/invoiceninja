<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Helpers\Encrypt\Secure;
use App\Http\Requests\Account\CreateAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Jobs\Account\CreateAccount;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\CompanyUser;
use App\Transformers\AccountTransformer;
use App\Transformers\CompanyUserTransformer;
use App\Utils\TruthSource;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Response;

class AccountController extends BaseController
{
    use DispatchesJobs;

    protected $entity_type = CompanyUser::class;

    protected $entity_transformer = CompanyUserTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        // return view('signup.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(CreateAccountRequest $request)
    {

        if ($request->has('cf-turnstile-response') && config('ninja.cloudflare.turnstile.secret')) {
            $r = \Illuminate\Support\Facades\Http::post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('ninja.cloudflare.turnstile.secret'),
                'response' => $request->input('cf-turnstile-response'),
                'remoteip' => $request->getClientIp(),
            ]);

            if ($r->successful()) {

                if ($r->json()['success'] === true) {
                    // Captcha passed
                } else {
                    return response()->json(['message' => 'Captcha Failed'], 400);
                }
            }

        }

        if ($request->has('hash') && config('ninja.cloudflare.turnstile.secret')) { //@todo once all platforms are implemented, we disable access to the rest of this route without a success response.

            if (Secure::decrypt($request->input('hash')) !== $request->input('email')) {
                return response()->json(['message' => 'Invalid Signup Payload'], 400);
            }

        }

        $account = (new CreateAccount($request->all(), $request->getClientIp()))->handle();
        if (! ($account instanceof Account)) {
            return $account;
        }

        MultiDB::findAndSetDbByAccountKey($account->key);

        $cu = CompanyUser::query()->where('user_id', $account->users()->first()->id);

        $company_user = $cu->first();

        $truth = app()->make(TruthSource::class);
        $truth->setCompanyUser($company_user);
        $truth->setUser($company_user->user);
        $truth->setCompany($company_user->company);
        $truth->setCompanyToken($company_user->tokens()->where('user_id', $company_user->user_id)->where('company_id', $company_user->company_id)->first());

        return $this->listResponse($cu);
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $fi = new \FilesystemIterator(public_path('react'), \FilesystemIterator::SKIP_DOTS);

        if (iterator_count($fi) < 30) {
            return response()->json(['message' => 'React App Not Installed, Please install the React app before attempting to switch.'], 400);
        }

        $account->fill($request->all());
        $account->save();

        $this->entity_type = Account::class;

        $this->entity_transformer = AccountTransformer::class;

        return $this->itemResponse($account);
    }
}
