<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedUpload extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['content_hash'];
}
