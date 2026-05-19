<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use BelongsToWorkspace;

    // workspace_id is intentionally NOT fillable: it is set by the
    // BelongsToWorkspace trait from the resolved tenant, never from request
    // input. This prevents a crafted request from planting a course in
    // another workspace.
    protected $fillable = ['code', 'title', 'slug'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
