<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    /**
     * The sections a material can belong to.
     * Keyed by stored value, value is the human label.
     */
    public const SECTIONS = [
        'notes' => 'Notes',
        'slides' => 'Slides',
        'past_papers' => 'Past Papers',
    ];

    protected $fillable = [
        'section',
        'title',
        'original_filename',
        'stored_path',
        'uploader_name',
        'manage_token',
        'file_size',
        'content_hash',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * The uploader-only URL that can delete this file. Null when the row
     * has no token (seed/legacy rows), in which case it isn't deletable.
     */
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

    /**
     * Extensions safe to serve inline (Content-Disposition: inline) for
     * in-browser preview. Kept narrow — arbitrary uploads served inline
     * (e.g. HTML) would be an XSS vector. The download route enforces this
     * same list before honouring ?view=1.
     */
    public const PREVIEWABLE = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp'];

    public function isPreviewable(): bool
    {
        $ext = strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));

        return in_array($ext, self::PREVIEWABLE, true);
    }

    public function previewUrl(): ?string
    {
        if (! filled($this->manage_token) || ! $this->isPreviewable()) {
            return null;
        }

        return route('material.download', ['token' => $this->manage_token, 'view' => 1]);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * A coarse file-type bucket derived from the filename extension,
     * used to pick a scanning icon in the UI.
     *
     * @return 'pdf'|'doc'|'ppt'|'image'|'file'
     */
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

    /**
     * What to show as the file's name: the uploader's title if given,
     * otherwise the filename with its (now-redundant) extension stripped,
     * since the format is shown separately as a tag. The stored
     * original_filename is untouched so downloads keep the real name.
     */
    public function displayName(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        $base = pathinfo($this->original_filename, PATHINFO_FILENAME);

        return $base !== '' ? $base : $this->original_filename;
    }

    /**
     * Short, unambiguous format label shown next to the file name,
     * e.g. "PDF", "DOCX", "JPG". Falls back to "FILE" when there is
     * no recognisable extension.
     */
    public function fileTypeLabel(): string
    {
        $ext = strtoupper(pathinfo($this->original_filename, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : 'FILE';
    }
}
