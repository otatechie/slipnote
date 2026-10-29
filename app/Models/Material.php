<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    public const SECTIONS = [
        'notes' => 'Notes',
        'slides' => 'Slides',
        'past_papers' => 'Past papers',
    ];

    /** Pinned files per section: a few lead it, or nothing does. */
    public const MAX_PINNED_PER_SECTION = 3;

    protected $fillable = [
        'section',
        'title',
        'original_filename',
        'stored_path',
        'uploader_name',
        'manage_token',
        'download_token',
        'file_size',
        'content_hash',
        'by_owner',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'by_owner' => 'boolean',
        'pinned_at' => 'datetime',
    ];

    public function downloadUrl(): ?string
    {
        if (! filled($this->download_token)) {
            return null;
        }

        return route('material.download', ['token' => $this->download_token]);
    }

    /** Removes the file and the row: the uploader's Undo, the owner's Delete, bulk delete. */
    public function discard(): void
    {
        Storage::disk('local')->delete($this->stored_path);
        $this->delete();
    }

    public function manageUrl(): ?string
    {
        if (! filled($this->manage_token)) {
            return null;
        }

        return route('material.destroy', [
            'material' => $this->id,
            'token' => $this->manage_token,
        ]);
    }

    public const PREVIEWABLE = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp'];

    public function isPreviewable(): bool
    {
        $ext = strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));

        return in_array($ext, self::PREVIEWABLE, true);
    }

    public function previewUrl(): ?string
    {
        if (! filled($this->download_token) || ! $this->isPreviewable()) {
            return null;
        }

        return route('material.download', ['token' => $this->download_token, 'view' => 1]);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function fileType(): string
    {
        $ext = strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'doc',
            'ppt', 'pptx' => 'ppt',
            'png', 'jpg', 'jpeg' => 'image',
            default => 'file',
        };
    }

    public function displayName(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        $base = pathinfo($this->original_filename, PATHINFO_FILENAME);

        return $base !== '' ? $base : $this->original_filename;
    }

    public function downloadName(): string
    {
        $ext = pathinfo((string) $this->stored_path, PATHINFO_EXTENSION)
            ?: pathinfo($this->original_filename, PATHINFO_EXTENSION);
        $base = filled($this->title) ? $this->title : pathinfo($this->original_filename, PATHINFO_FILENAME);
        if ($base === '') {
            $base = $this->original_filename;
        }
        $name = self::scrubName($ext !== '' ? "{$base}.{$ext}" : $base);

        $code = self::scrubName((string) $this->course?->code);
        $squash = fn (string $v) => mb_strtolower(preg_replace('/\s+/u', '', $v));
        if ($code === '' || mb_strlen($code) > 16 || str_contains($squash($name), $squash($code))) {
            return $name;
        }

        return "{$code} - {$name}";
    }

    private static function scrubName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1f\x7f\/\\:*?"<>|]+/u', ' ', $name);

        return trim(preg_replace('/\s+/u', ' ', $clean));
    }

    public function fileTypeLabel(): string
    {
        $ext = strtoupper(pathinfo($this->original_filename, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : 'FILE';
    }
}
