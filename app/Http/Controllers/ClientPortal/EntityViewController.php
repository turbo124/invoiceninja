<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Hash;

class EntityViewController extends Controller
{
    use MakesHash;

    /**
     * Available options for viewing.
     *
     * @var array
     */
    private $entity_types = ['invoice', 'quote'];

    /**
     * Show the entity outside client portal.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(string $entity_type, string $invitation_key)
    {
        if (! in_array($entity_type, $this->entity_types)) {
            abort(404);
        }

        $invitation_entity = sprintf('App\\Models\\%sInvitation', ucfirst($entity_type));

        $key = $entity_type.'_id';

        $invitation = $invitation_entity::whereRaw('BINARY `key`= ?', [$invitation_key])->firstOrFail();

        $contact = $invitation->contact;

        if (is_null($contact->password) || empty($contact->password)) {
            return redirect("/client/password/reset?email={$contact->email}");
        }

        $entity_class = sprintf('App\\Models\\%s', ucfirst($entity_type));
        $entity = $entity_class::findOrFail($invitation->{$key});

        if ((bool) $invitation->contact->client->getSetting('enable_client_portal_password') !== false) {
            session()->flash("{$entity_type}_VIEW_{$entity->hashed_id}", true);
        }

        if (! session("{$entity_type}_VIEW_{$entity->hashed_id}")) {
            return redirect()->route('client.entity_view.password', compact('entity_type', 'invitation_key'));
        }

        return $this->render('view_entity.index', [
            'root' => 'themes',
            'entity' => $entity,
        ]);
    }

    /**
     * Show the form for entering password.
     *
     * @param string $entity_type
     * @param string $invitation_key
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function password(string $entity_type, string $invitation_key)
    {
        return $this->render('view_entity.password', [
            'root' => 'themes',
            'entity_type' => $entity_type,
        ]);
    }

    /**`
     * Handle the password check.
     *
     * @param string $entity_type
     * @param string $invitation_key
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse|mixed
     */
    public function handlePassword(string $entity_type, string $invitation_key)
    {
        if (! in_array($entity_type, $this->entity_types)) {
            abort(404);
        }

        $invitation_entity = sprintf('App\\Models\\%sInvitation', ucfirst($entity_type));

        $key = $entity_type.'_id';

        $invitation = $invitation_entity::whereRaw('BINARY `key`= ?', [$invitation_key])->firstOrFail();

        $contact = $invitation->contact;

        $check = Hash::check(request()->password, $contact->password);

        $entity_class = sprintf('App\\Models\\%s', ucfirst($entity_type));

        $entity = $entity_class::findOrFail($invitation->{$key});

        if ($check) {
            session()->flash("{$entity_type}_VIEW_{$entity->hashed_id}", true);

            return redirect()->route('client.entity_view', compact('entity_type', 'invitation_key'));
        }

        session()->flash('PASSWORD_FAILED', true);

        return back();
    }
}
