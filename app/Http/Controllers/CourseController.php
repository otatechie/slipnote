<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Material;
use App\Services\TelegramNotifier;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseController extends Controller
{
    private function workspace()
    {
        return app(Tenancy::class)->current();
    }

    private function isOwner(): bool
    {
        return session($this->workspace()->ownerSessionKey()) === true;
    }

    public function show(Request $request, string $workspaceSlug, string $slug)
    {
        $workspace = $this->workspace();

        // Handle ?owner= URL param
        $given = $request->query('owner');
        if ($workspace->verifyOwner(is_string($given) ? $given : null)) {
            session([$workspace->ownerSessionKey() => true]);

            return redirect()->route('course.show', ['workspace' => $workspace->slug, 'slug' => $slug]);
        }

        $course = Course::where('slug', $slug)->firstOrFail();

        $search = trim($request->input('search', ''));
        $sort = $request->input('sort', 'newest');
        $activeSection = $request->input('section', '');

        $query = $course->materials();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")->orWhere('original_filename', 'like', "%{$search}%");
            });
        }
        if ($activeSection !== '' && isset(Material::SECTIONS[$activeSection])) {
            $query->where('section', $activeSection);
        }
        match ($sort) {
            'oldest' => $query->oldest(),
            'az' => $query->orderByRaw('COALESCE(title, original_filename) collate nocase asc'),
            default => $query->latest(),
        };

        $sectionCounts = $course->materials()
            ->selectRaw('section, count(*) as total')
            ->groupBy('section')
            ->pluck('total', 'section');

        $materials = $query->get()->map(fn ($m) => [
            'id' => $m->id,
            'section' => $m->section,
            'displayName' => $m->displayName(),
            'original_filename' => $m->original_filename,
            'fileTypeLabel' => $m->fileTypeLabel(),
            'uploader_name' => $m->uploader_name,
            'created_at_human' => $m->created_at->diffForHumans(),
            'download_url' => route('material.download', $m),
            'delete_url' => route('material.destroy', ['material' => $m->id, 'token' => 'owner']),
            'manage_url' => $m->manageUrl(),
            'title' => $m->title,
        ]);

        return Inertia::render('CoursePage', [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'course' => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title, 'slug' => $course->slug],
            'isOwner' => $this->isOwner(),
            'storageFull' => $workspace->storageFull(),
            'passphraseNeeded' => filled($workspace->upload_passphrase) && session($workspace->uploadUnlockKey()) !== true,
            'sections' => Material::SECTIONS,
            'sectionCounts' => $sectionCounts,
            'materials' => $materials,
            'resultCount' => $course->materials()->count(),
            'search' => $search,
            'sort' => $sort,
            'activeSection' => $activeSection,
        ]);
    }

    public function exitOwner(Request $request)
    {
        $workspace = $this->workspace();
        session()->forget($workspace->ownerSessionKey());

        return back();
    }

    public function upload(Request $request, string $workspaceSlug, string $slug)
    {
        $workspace = $this->workspace();
        $course = Course::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'section' => 'required|in:'.implode(',', array_keys(Material::SECTIONS)),
            'title' => 'nullable|string|max:120',
            'uploaderName' => 'nullable|string|max:60',
            'passphrase' => 'nullable|string',
            'file' => 'required|file|max:10240|mimes:pdf,docx,pptx,png,jpg,jpeg',
        ]);

        $free = @disk_free_space(storage_path('app/public')) ?: PHP_INT_MAX;
        if ($free < (int) config('noteshare.min_free_disk_bytes')) {
            return back()->withErrors(['file' => 'The site is at capacity — please try again later.']);
        }

        $incoming = (int) $request->file('file')->getSize();
        if ($incoming > $workspace->storageRemaining()) {
            return back()->withErrors(['file' => 'This board is full — ask the owner to delete old files.']);
        }

        if (filled($workspace->upload_passphrase) && session($workspace->uploadUnlockKey()) !== true) {
            if (! $workspace->uploadPassphraseMatches($data['passphrase'] ?? null)) {
                return back()->withErrors(['passphrase' => "That passphrase isn't right — ask your course rep."]);
            }
            session([$workspace->uploadUnlockKey() => true]);
        }

        $storedPath = $request->file('file')->store('materials', 'public');

        $material = $course->materials()->create([
            'section' => $data['section'],
            'title' => isset($data['title']) ? strip_tags($data['title']) : null,
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'stored_path' => $storedPath,
            'uploader_name' => isset($data['uploaderName']) ? strip_tags($data['uploaderName']) : null,
            'manage_token' => \Illuminate\Support\Str::random(40),
            'file_size' => $incoming,
        ]);

        dispatch(fn () => app(TelegramNotifier::class)->notifyUpload($material))->afterResponse();

        return back()->with([
            'uploaded' => 'File added.',
            'manageUrl' => $material->manageUrl(),
        ]);
    }
}
