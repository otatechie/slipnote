<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'created' => fn () => $request->session()->get('created'),
                'uploaded' => fn () => $request->session()->get('uploaded'),
                'manageUrl' => fn () => $request->session()->get('manageUrl'),
                'recoverySaved' => fn () => $request->session()->get('recoverySaved'),
                'done' => fn () => $request->session()->get('done'),
                'createdName' => fn () => $request->session()->get('createdName'),
                'createdUrl' => fn () => $request->session()->get('createdUrl'),
                'ownerUrl' => fn () => $request->session()->get('ownerUrl'),
            ],
            'errors' => fn () => $request->session()->get('errors')
                ? $request->session()->get('errors')->getBag('default')->getMessages()
                : (object) [],
        ]);
    }
}
