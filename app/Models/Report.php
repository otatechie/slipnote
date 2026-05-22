<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    // Only created_at; reports are immutable once filed.
    public const UPDATED_AT = null;

    protected $fillable = ['material_id', 'reason', 'reporter_ip'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
