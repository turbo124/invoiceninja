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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\UpdateClientRequest;
use App\Http\Requests\ClientPortal\UpdateContactRequest;
use App\Jobs\Util\UploadAvatar;
use App\Models\ClientContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Show the form for editing the specified resource.
     *
     * @param ClientContact $client_contact
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(ClientContact $client_contact)
    {
        return $this->render('profile.index');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateContactRequest $request
     * @param ClientContact $client_contact
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateContactRequest $request, ClientContact $client_contact)
    {
        $client_contact->fill($request->all());

        if ($request->has('password')) {
            $client_contact->password = encrypt($request->password);
        }

        $client_contact->save();

        // auth()->user()->fresh();

        return back()->withSuccess(
            ctrans('texts.profile_updated_successfully')
        );
    }

    public function updateClient(UpdateClientRequest $request, ClientContact $client_contact)
    {
        $client = $client_contact->client;

        //update avatar if needed
        if ($request->file('logo')) {
            $path = UploadAvatar::dispatchNow($request->file('logo'), auth()->user()->client->client_hash);

            if ($path) {
                $client->logo = $path;
            }
        }

        $client->fill($request->all());
        $client->save();

        return back()->withSuccess(
            ctrans('texts.profile_updated_successfully')
        );
    }
}
