<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    // Only created_at; reports are immutable once filed.
    public const UPDATED_AT = null;

    protected $fillable = ['material_id', 'reason', 'reporter_ip'];

    /**
     * The reasons the report form offers (REPORT_REASONS in CoursePage.vue),
     * spelled exactly as it sends them. A note can follow after " — ".
     */
    public const REASONS = [
        'Not my notes / wrong file',
        "Copyright — shouldn't be shared",
        'Inappropriate or offensive',
        'Spam',
        'Other',
    ];

    /** [the reason picked, the note typed]; either can be null. */
    public static function splitReason(?string $reason): array
    {
        foreach (self::REASONS as $label) {
            if ($reason === $label) {
                return [$label, null];
            }
            if (str_starts_with((string) $reason, $label.' — ')) {
                return [$label, substr($reason, strlen($label.' — '))];
            }
        }

        return [null, $reason];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
