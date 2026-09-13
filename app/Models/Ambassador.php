<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Ambassador extends Model
{
    /** A ref slug: lowercase, digits, hyphens; must start alphanumeric. */
    public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]{0,39}$/';

    public const NETWORKS = ['mtn' => 'MTN', 'telecel' => 'Telecel', 'airteltigo' => 'AirtelTigo', 'other' => 'Other'];

    protected $fillable = ['name', 'slug', 'campus', 'phone', 'network'];

    protected $casts = [
        'phone' => 'encrypted',
        'retired_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    /** The link this ambassador hands to course reps. */
    public function link(): string
    {
        return route('welcome', ['ref' => $this->slug]);
    }

    /**
     * The message the operator sends the ambassador: their link, the pitch
     * to pass on, and the deal. Plain text, meant for WhatsApp.
     */
    public function inviteMessage(): string
    {
        $link = $this->link();

        return "Hi {$this->name} — here's your SlipNote ambassador link:\n"
            ."{$link}\n\n"
            ."Send it to course reps. When a rep creates a board through it, it counts as yours. "
            ."A board counts as live once it has files in it and classmates are opening it — that's what the reward is paid on, not sign-ups.\n\n"
            ."The pitch that works: \"One link for all our slides and past papers, no account needed.\"";
    }
}
