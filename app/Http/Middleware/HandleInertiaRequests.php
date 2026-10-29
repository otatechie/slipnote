<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $session = $request->session();

        return array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),
            'ambassadorFormUrl' => config('noteshare.ambassador_form_url'),
            'operatorName' => config('noteshare.operator_name'),
            'flash' => [
                'success' => $session->get('success'),
                'created' => $session->get('created'),
                'uploaded' => $session->get('uploaded'),
                'reported' => $session->get('reported'),
                'manageUrl' => $session->get('manageUrl'),
                'undoBatch' => $session->get('undoBatch'),
                'recoverySaved' => $session->get('recoverySaved'),
                'nameSaved' => $session->get('nameSaved'),
                'done' => $session->get('done'),
                'createdName' => $session->get('createdName'),
                'createdUrl' => $session->get('createdUrl'),
                'ownerUrl' => $session->get('ownerUrl'),
                'recovered' => $session->get('recovered'),
                'ownerReset' => $session->get('ownerReset'),
                'boardDeleted' => $session->get('boardDeleted'),
                // Pin, unpin, removals: shown briefly at the foot of the screen.
                'notice' => $session->get('notice'),
                'addedName' => $session->get('addedName'),
                'addedCount' => $session->get('addedCount'),
            ],
            'errors' => fn () => $session->get('errors')
                ? $session->get('errors')->getBag('default')->getMessages()
                : (object) [],
        ]);
    }
}
