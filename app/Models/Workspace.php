<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
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
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
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
     * Rotate the owner secret: generate a new one, store only its hash,
     * return the new plaintext (to email). The previous owner link stops
     * working — by design (a recovered/lost link should die). Preserves the
     * guarantee that the secret itself is never stored, only its hash.
     */
    public function rotateOwnerSecret(): string
    {
        $secret = Str::random(40);
        $this->owner_secret_hash = Hash::make($secret);
        $this->save();

        return $secret;
    }

    /** Slug from the name, with a numeric suffix if it collides. */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $n = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    /** Per-workspace session keys so unlocking one never unlocks another. */
    public function ownerSessionKey(): string
    {
        return "ws_owner_{$this->id}";
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
}
