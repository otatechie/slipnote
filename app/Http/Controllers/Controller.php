<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Tenancy\Tenancy;

abstract class Controller
{
    /** The workspace resolved for the current request. */
    protected function workspace(): Workspace
    {
        return app(Tenancy::class)->current();
    }

    /** Whether the current session has owner access to the resolved workspace. */
    protected function isOwner(): bool
    {
        return session($this->workspace()->ownerSessionKey()) === true;
    }
}
