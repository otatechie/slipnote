<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        // Read flash eagerly — lazy closures resolve AFTER the flash bag
        // drains, which means no toast ever shows.
        $session = $request->session();

        return array_merge(parent::share($request), [
            // Needed by native <form> POSTs (e.g. the per-row owner delete),
            // which can't rely on the XSRF cookie header that axios sends.
            'csrf_token' => csrf_token(),
            'flash' => [
                'success' => $session->get('success'),
                'created' => $session->get('created'),
                'uploaded' => $session->get('uploaded'),
                'reported' => $session->get('reported'),
                'manageUrl' => $session->get('manageUrl'),
                'recoverySaved' => $session->get('recoverySaved'),
                'done' => $session->get('done'),
                'createdName' => $session->get('createdName'),
                'createdUrl' => $session->get('createdUrl'),
                'ownerUrl' => $session->get('ownerUrl'),
            ],
            'errors' => fn () => $session->get('errors')
                ? $session->get('errors')->getBag('default')->getMessages()
                : (object) [],
        ]);
    }
}
