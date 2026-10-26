<?php

namespace App\Models;

use App\Mail\AbandonedBoardWarning;
use App\Services\TelegramNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Workspace extends Model
{
    protected $fillable = ['name', 'slug', 'owner_secret_hash', 'upload_passphrase'];

    protected $hidden = ['owner_secret_hash', 'recovery_email'];

    protected $casts = [
        // Encrypted at rest: a leaked DB/backup must not expose emails.
        'recovery_email' => 'encrypted',
        'last_accessed_at' => 'datetime',
        'operator_note_at' => 'datetime',
        'operator_note_seen_at' => 'datetime',
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

    public function purge(): void
    {
        $courseIds = Course::withoutGlobalScopes()->where('workspace_id', $this->id)->pluck('id');
        Material::whereIn('course_id', $courseIds)->pluck('stored_path')
            ->each(fn ($path) => Storage::disk('local')->delete($path));
        Material::whereIn('course_id', $courseIds)->delete();
        Course::withoutGlobalScopes()->whereIn('id', $courseIds)->delete();
        DB::table('board_visit_days')->where('workspace_id', $this->id)->delete();
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

    public function removalDueAt(): Carbon
    {
        $due = $this->lastActiveAt()->copy()->addDays(self::ABANDONED_AFTER_DAYS);
        if ($this->abandon_warned_at) {
            $due = $due->max($this->abandon_warned_at->copy()->addDays(self::ABANDON_WARNING_DAYS));
        }

        return $due;
    }

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

    public static function pruneAbandonedDaily(): void
    {
        if (Cache::add('prune_abandoned_boards', true, now()->addDay())) {
            static::pruneAbandoned();
            DB::table('board_visit_days')
                ->where('day', '<', now()->subDays(self::VISIT_DAYS_KEPT)->toDateString())
                ->delete();
        }
    }

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

    public function setRecoveryEmail(?string $email): void
    {
        $email = $email !== null ? mb_strtolower(trim($email)) : null;
        $this->recovery_email = ($email === null || $email === '') ? null : $email;
        $this->save();
    }

    public function recoveryEmailMatches(?string $given): bool
    {
        if (blank($this->recovery_email) || ! is_string($given) || $given === '') {
            return false;
        }

        return hash_equals($this->recovery_email, mb_strtolower(trim($given)));
    }

    public function draftOwnerSecretRotation(): array
    {
        $secret = Str::random(40);

        return [$secret, Hash::make($secret)];
    }

    /** Visitor days needed in the last 30 for a board to count as used (the ambassador reward). */
    public const USED_MIN_DAYS = 3;

    /** How far back visitor days are kept: the reward window, with room to spare. */
    public const VISIT_DAYS_KEPT = 90;

    public function touchAccess(): void
    {
        $this->noteVisitor();

        if ($this->last_accessed_at?->gt(now()->subHour())) {
            return;
        }

        $this->last_accessed_at = now();
        // Opened again: any "about to be removed" warning no longer applies.
        $this->abandon_warned_at = null;

        $this->timestamps = false;
        $this->saveQuietly();
        $this->timestamps = true;
    }

    /**
     * Marks today as a day someone other than the owner used this board.
     * The owner's own visits still keep it from being removed (touchAccess),
     * but never count it as used by the class.
     */
    private function noteVisitor(): void
    {
        if ($this->sessionIsOwner()) {
            return;
        }

        $today = now()->toDateString();
        $seen = "ws_seen_{$this->id}";
        if (session($seen) === $today) {
            return;
        }

        DB::table('board_visit_days')->insertOrIgnore(['workspace_id' => $this->id, 'day' => $today]);
        session([$seen => $today]);
    }

    /** Slug from the name, with a numeric suffix if it collides. */
    public static function uniqueSlug(string $name): string
    {
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

    public function ownerSessionValue(): string
    {
        return hash('sha256', 'owner|'.$this->owner_secret_hash);
    }

    /** Whether this session holds owner access under the current secret. */
    public function sessionIsOwner(): bool
    {
        $held = session($this->ownerSessionKey());

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
