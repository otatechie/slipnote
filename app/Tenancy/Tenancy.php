<?php

namespace App\Tenancy;

use App\Models\Workspace;
use RuntimeException;

class Tenancy
{
    private ?Workspace $workspace = null;

    public function set(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function has(): bool
    {
        return $this->workspace !== null;
    }

    public function current(): Workspace
    {
        if ($this->workspace === null) {
            throw new RuntimeException(
                'No current workspace. A tenant-scoped query ran outside a '
                .'workspace-resolved request.'
            );
        }

        return $this->workspace;
    }

    public function id(): ?int
    {
        return $this->workspace?->id;
    }
}
