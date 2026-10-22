<?php

namespace App\Models;

use App\Mail\AbandonedBoardWarning;
use App\Services\TelegramNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An isolated board. No accounts: the owner secret (stored only as a bcrypt
 * hash) is the single credential, handed out once as a capability URL.
 */
class Workspace extends Model
{
    // recovery_email is deliberately NOT fillable — set only via
    // setRecoveryEmail() behind the owner gate, never mass-assigned.
    // upload_passphrase is fillable for existing setup flows, but is hashed
    // by the mutator below before it is stored.
    protected $fillable = ['name', 'slug', 'owner_secret_hash', 'upload_passphrase'];

    protected $hidden = ['owner_secret_hash', 'recovery_email'];

    protected $casts = [
        // Encrypted at rest: a leaked DB/backup must not expose emails.
        'recovery_email' => 'encrypted',
        'last_accessed_at' => 'datetime',
        'operator_note_at' => 'datetime',
        'abandon_warned_at' => 'datetime',
        'uploads_owner_only' => 'boolean',
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function materials(): HasManyThrough
    {
        return $this->hasManyThrough(Material::class, Course::class);
    }

    /**
     * Delete this board for good: files from disk first, then its rows
     * (materials, courses, the board). Outside the tenancy scope, so it works
     * from the operator console as well as the board's own page.
     */
    public function purge(): void
    {
        $courseIds = Course::withoutGlobalScopes()->where('workspace_id', $this->id)->pluck('id');
        Material::whereIn('course_id', $courseIds)->pluck('stored_path')
            ->each(fn ($path) => Storage::disk('local')->delete($path));
        Material::whereIn('course_id', $courseIds)->delete();
        Course::withoutGlobalScopes()->whereIn('id', $courseIds)->delete();
        $this->delete();
    }

    /** An empty board unopened this long (by a person) is removed. */
    public const ABANDONED_AFTER_DAYS = 30;

    /** Owners with a recovery email are warned this long before. */
    public const ABANDON_WARNING_DAYS = 7;

    /** When this board was last opened by a person, or made if never. */
    public function lastActiveAt(): Carbon
    {
        return $this->last_accessed_at ?? $this->created_at;
    }

    /**
     * When the cleanup would remove this board if it stays empty and
     * unopened: 30 days after it was last active, and never sooner than 7
     * days after its owner was warned.
     */
    public function removalDueAt(): Carbon
    {
        $due = $this->lastActiveAt()->copy()->addDays(self::ABANDONED_AFTER_DAYS);
        if ($this->abandon_warned_at) {
            $due = $due->max($this->abandon_warned_at->copy()->addDays(self::ABANDON_WARNING_DAYS));
        }

        return $due;
    }

    /**
     * Remove boards with no files that nobody has opened for 30 days,
     * warning owners who set a recovery email a week before. Opening the
     * board resets the clock and the warning; a board with any file is never
     * touched. Returns [removed, warned].
     *
     * @return array{0:int,1:int}
     */
    public static function pruneAbandoned(): array
    {
        $mailOn = ! in_array(config('mail.default'), ['log', 'array', null], true);
        $warnFrom = now()->subDays(self::ABANDONED_AFTER_DAYS - self::ABANDON_WARNING_DAYS);
        $removed = 0;
        $warned = 0;

        static::query()
            ->whereDoesntHave('materials')
            ->whereRaw('COALESCE(last_accessed_at, created_at) < ?', [$warnFrom])
            ->each(function (self $board) use ($mailOn, &$removed, &$warned) {
                // Warn first, once, where there is someone to warn.
                if ($mailOn && filled($board->recovery_email) && ! $board->abandon_warned_at) {
                    try {
                        Mail::to($board->recovery_email)->send(new AbandonedBoardWarning(
                            $board->name,
                            route('courses.index', ['workspace' => $board->slug]),
                            self::ABANDON_WARNING_DAYS,
                        ));
                        $board->forceFill(['abandon_warned_at' => now()])->saveQuietly();
                        $warned++;
                    } catch (\Throwable $e) {
                        Log::warning('Abandoned-board warning failed', ['workspace' => $board->id]);
                    }

                    return;
                }

                if ($board->removalDueAt()->isPast()) {
                    $board->purge();
                    $removed++;
                }
            });

        if ($removed > 0) {
            dispatch(fn () => app(TelegramNotifier::class)->notifyAbandonedRemoved($removed))->afterResponse();
        }

        return [$removed, $warned];
    }

    /**
     * pruneAbandoned at most once a day, from ordinary requests (a board
     * being made, the operator console), so it runs even where no scheduler
     * does. The scheduled command runs it too, where one is.
     */
    public static function pruneAbandonedDaily(): void
    {
        if (Cache::add('prune_abandoned_boards', true, now()->addDay())) {
            static::pruneAbandoned();
        }
    }

    /**
     * Create a workspace and return [Workspace, plaintextOwnerSecret].
     * The plaintext is shown to the creator exactly once and never stored.
     */
    public static function provision(string $name): array
    {
        $secret = Str::random(40);

        $workspace = static::create([
            'name' => trim($name),
            'slug' => static::uniqueSlug($name),
            'owner_secret_hash' => Hash::make($secret),
        ]);

        return [$workspace, $secret];
    }

    /** Timing-safe owner check. */
    public function verifyOwner(?string $given): bool
    {
        return is_string($given) && $given !== ''
            && Hash::check($given, $this->owner_secret_hash);
    }

    public function setUploadPassphraseAttribute(?string $value): void
    {
        $value = is_string($value) ? trim($value) : null;

        $this->attributes['upload_passphrase'] = blank($value)
            ? null
            : (Hash::isHashed($value) ? $value : Hash::make($value));
    }

    public function uploadPassphraseMatches(?string $given): bool
    {
        if (blank($this->upload_passphrase) || ! is_string($given) || $given === '') {
            return false;
        }

        if (Hash::isHashed($this->upload_passphrase)) {
            return Hash::check($given, $this->upload_passphrase);
        }

        // Legacy plaintext rows are accepted once, then upgraded in place.
        if (hash_equals($this->upload_passphrase, $given)) {
            $this->upload_passphrase = $given;
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * Opt-in recovery email. Stored normalised (trim + lowercase) so the
     * recovery check is a clean comparison. Empty string clears it.
     */
    public function setRecoveryEmail(?string $email): void
    {
        $email = $email !== null ? mb_strtolower(trim($email)) : null;
        $this->recovery_email = ($email === null || $email === '') ? null : $email;
        $this->save();
    }

    /**
     * Constant-time match against the stored recovery email. False when no
     * recovery email is set — callers MUST NOT branch differently on "no
     * email" vs "wrong email" (no enumeration; identical response either way).
     */
    public function recoveryEmailMatches(?string $given): bool
    {
        if (blank($this->recovery_email) || ! is_string($given) || $given === '') {
            return false;
        }

        return hash_equals($this->recovery_email, mb_strtolower(trim($given)));
    }

    /**
     * Generate a fresh owner secret and its hash without persisting it yet.
     * Lets the caller avoid retiring the current owner link unless the
     * replacement link has actually been handed off successfully.
     *
     * @return array{0:string,1:string}
     */
    public function draftOwnerSecretRotation(): array
    {
        $secret = Str::random(40);

        return [$secret, Hash::make($secret)];
    }

    /**
     * Record that someone looked at this board or pulled a file from it.
     *
     * Throttled to the hour: the question this answers is "has anyone touched
     * this board in months", so finer resolution buys nothing and would mean a
     * write on every page view. Skips the model's timestamps so a visit never
     * looks like an edit.
     */
    public function touchAccess(): void
    {
        if ($this->last_accessed_at?->gt(now()->subHour())) {
            return;
        }

        $this->last_accessed_at = now();
        // Opened again: any "about to be removed" warning no longer applies.
        $this->abandon_warned_at = null;

        // timestamps=false, not just saveQuietly(): saveQuietly suppresses
        // events but still bumps updated_at, which would make every visit
        // look like an edit.
        $this->timestamps = false;
        $this->saveQuietly();
        $this->timestamps = true;
    }

    /** Slug from the name, with a numeric suffix if it collides. */
    public static function uniqueSlug(string $name): string
    {
        // The name, then a random code: the link is the only key to a board,
        // and from the name alone ("econ-level-200") anyone could guess it.
        // 6 characters from 31 (no 0/o, 1/l/i to misread): about 900 million
        // per name, with misses rate-limited (see the workspace binding).
        $base = Str::limit(Str::slug($name) ?: 'board', 60, '');
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $reserved = static::reservedSlugs();
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $slug = "{$base}-{$code}";
        } while (in_array($slug, $reserved, true) || static::where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Slugs the site's own pages already answer to (/start, /privacy,
     * /lecturers, /operator…). Those routes are declared before the
     * /{workspace} catch-all, so a board named "Privacy" would get a link
     * that opens the privacy page and could never be reached. Read from the
     * router, so a new page is covered without remembering to list it.
     *
     * @return list<string>
     */
    private static function reservedSlugs(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => explode('/', $route->uri())[0])
            ->reject(fn ($segment) => $segment === '' || str_starts_with($segment, '{'))
            ->unique()
            ->values()
            ->all();
    }

    /** Per-workspace session keys so unlocking one never unlocks another. */
    public function ownerSessionKey(): string
    {
        return "ws_owner_{$this->id}";
    }

    /**
     * What an owner session holds: a fingerprint of the current owner secret's
     * hash, not a bare true. Rotating the secret (recovery, or Reset owner
     * link) changes it, so every browser that unlocked with the old link stops
     * being owner on its next request. With true, a leaked link that had been
     * used stayed owner for as long as its session lived.
     */
    public function ownerSessionValue(): string
    {
        return hash('sha256', 'owner|'.$this->owner_secret_hash);
    }

    /** Whether this session holds owner access under the current secret. */
    public function sessionIsOwner(): bool
    {
        $held = session($this->ownerSessionKey());

        // A session unlocked before fingerprints holds a bare true. Bind it to
        // the current secret on first sight rather than signing every owner
        // out on deploy -- some have lost their link. From here on a rotation
        // revokes it like any other.
        if ($held === true) {
            session([$this->ownerSessionKey() => $this->ownerSessionValue()]);

            return true;
        }

        return is_string($held) && hash_equals($this->ownerSessionValue(), $held);
    }

    public function uploadUnlockKey(): string
    {
        return "ws_upload_ok_{$this->id}";
    }

    /**
     * Total bytes used by this workspace's materials. Sums file_size across
     * every material in every course; trusts the column rather than touching
     * disk per request (the column is set at upload time + backfilled).
     */
    public function storageBytes(): int
    {
        return (int) Material::query()
            ->whereIn('course_id', $this->courses()->select('id'))
            ->sum('file_size');
    }

    /** Bytes remaining before this workspace hits its soft cap (>= 0). */
    public function storageRemaining(): int
    {
        return max(0, (int) config('noteshare.workspace_storage_bytes') - $this->storageBytes());
    }

    public function storageFull(): bool
    {
        return $this->storageRemaining() <= 0;
    }

    /** Files this workspace can still accept before its count cap (>= 0). */
    public function filesRemaining(): int
    {
        return max(0, (int) config('noteshare.workspace_max_files') - $this->materials()->count());
    }
}
