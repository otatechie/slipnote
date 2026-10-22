<?php

namespace App\Http\Controllers;

use App\Models\Ambassador;
use App\Models\BlockedUpload;
use App\Models\Material;
use App\Models\Report;
use App\Models\Suggestion;
use App\Models\Workspace;
use App\Support\ClientKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Site-operator moderation dashboard — the kill-switch for abuse. Lists every
 * reported file across all workspaces and lets the operator remove or dismiss
 * them. Abuse-report notifications link here.
 *
 * Security posture:
 *  - Disabled entirely unless OPERATOR_SECRET is configured.
 *  - Login is a POST; the secret is held in the session (never in a URL),
 *    checked timing-safe, and rate-limited.
 *  - Destructive actions (remove / dismiss / undo) are POSTs, CSRF-protected,
 *    behind the operator-session gate.
 */
class OperatorController extends Controller
{
    private const UNDO_SESSION = 'operator_undo';

    /** Long enough to catch the wrong row, short enough the trash doesn't linger. */
    private const UNDO_TTL_SECONDS = 300;

    private function enabled(): bool
    {
        return filled(config('noteshare.operator_secret'));
    }

    /**
     * Session carries a fingerprint of the secret it was authenticated with.
     * Verifying it against the CURRENT secret means rotating OPERATOR_SECRET
     * immediately invalidates every existing operator session (as the
     * deployment guidance promises) — not just on expiry/manual logout.
     */
    private function secretFingerprint(): string
    {
        return hash('sha256', (string) config('noteshare.operator_secret'));
    }

    private function authed(): bool
    {
        return is_string(session('operator_fp'))
            && hash_equals($this->secretFingerprint(), session('operator_fp'));
    }

    private const TABS = ['reported', 'boards', 'visitors', 'suggestions', 'ambassadors'];

    /** Reported is the default: action before browsing. */
    private function tab(Request $request): string
    {
        $tab = $request->query('tab');

        return in_array($tab, self::TABS, true) ? $tab : 'reported';
    }

    private function dashboardRedirect(Request $request, ?string $tab = null): RedirectResponse
    {
        $tab ??= $this->tab($request);
        $params = $tab === 'reported' ? [] : ['tab' => $tab];

        return redirect()->route('operator.dashboard', $params);
    }

    /**
     * The boards list the request came from, if it was the boards tab on this
     * site, else the tab's first page. Only the list's own parameters are
     * kept, so a crafted Referer can't send the redirect anywhere else.
     */
    private function boardsListUrl(Request $request): string
    {
        parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $from);
        $page = (int) ($from['page'] ?? 1);
        $sort = is_string($from['sort'] ?? null) && isset(self::BOARD_SORTS[$from['sort']]) ? $from['sort'] : 'newest';

        return route('operator.dashboard', self::boardParams(
            is_string($from['q'] ?? null) ? trim($from['q']) : '',
            $sort,
            ($from['empty'] ?? null) === '1',
            $page > 1 ? ['page' => $page] : [],
        ));
    }

    /** The dashboard (or the login form when not authenticated). */
    public function dashboard(Request $request)
    {
        abort_unless($this->enabled(), 404);

        if (! $this->authed()) {
            return view('operator.login');
        }

        $undo = $this->activeUndo();

        // Reported files, most-reported first. Capped: the queue is worked
        // worst-first and items leave as they're removed/dismissed, so the tail
        // past 50 never needs to be on screen — but an abuse spike must not try
        // to render thousands of rows at once. The count note flags any overflow.
        $reportedTotal = Material::whereHas('reports')->count();
        $materials = Material::query()
            ->whereHas('reports')
            ->withCount('reports')
            ->with(['reports' => fn ($q) => $q->latest()->limit(20), 'course.workspace'])
            ->orderByDesc('reports_count')
            ->limit(50)
            ->get();

        // Usage stats: is anyone actually using this thing?
        $stats = [
            'workspaces' => Workspace::count(),
            'workspaces_week' => Workspace::where('created_at', '>=', now()->subDays(7))->count(),
            'files' => Material::count(),
            'files_week' => Material::where('created_at', '>=', now()->subDays(7))->count(),
            'storage_mb' => round(Material::sum('file_size') / 1_048_576, 1),
            // A board is "active" if anyone opened or downloaded from it this
            // week — not whether it received files. An archive that gets no
            // uploads but serves past papers every exam week is alive.
            'active_week' => Workspace::where('last_accessed_at', '>=', now()->subDays(7))->count(),
            // Until some board has been visited, "0 active" means "not measured
            // yet", not "nobody came". The tile says which.
            'tracking_started' => Workspace::whereNotNull('last_accessed_at')->exists(),
            // Created and never filled — the drop-off worth watching.
            'empty_boards' => Workspace::whereDoesntHave('materials')->count(),
        ];

        // Boards newest first, 20 to a page, with how much they hold and when
        // they were last touched — a board with files but no recent access is a
        // dead one. Paged, not capped: past the first page sat boards the
        // operator could only reach by guessing a name to search for.
        $boardQuery = trim((string) $request->query('q', ''));
        $boardSort = self::boardSort($request);
        // Boards with no files are most of the list (made and left), so they
        // are hidden unless asked for. Never deleted: their owners' links
        // still work, and Privacy promises boards aren't removed. A search
        // finds any board: searching means looking for one in particular.
        $showEmpty = $request->query('empty') === '1';
        $visitsSince = now()->subDays(self::VISITOR_DAYS - 1)->toDateString();

        $recent = Workspace::query()
            ->select('workspaces.*')
            // Arrivals in the visitor window: "most visited" is the best
            // measure of a board a class actually uses.
            ->selectSub(fn ($q) => $q->from('visit_tallies')
                ->selectRaw('COALESCE(SUM(arrivals), 0)')
                ->whereColumn('visit_tallies.workspace_id', 'workspaces.id')
                ->where('day', '>=', $visitsSince), 'recent_arrivals')
            ->when($boardQuery !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$boardQuery}%")
                ->orWhere('slug', 'like', '%'.Str::slug($boardQuery).'%')))
            ->when($boardQuery === '' && ! $showEmpty, fn ($q) => $q->whereHas('materials'))
            ->withCount([
                'courses',
                'materials',
                // Files on this board currently carrying reports. A board with
                // several is a repeat offender — a different problem from one
                // bad file, and the per-file queue can't show it.
                'materials as reported_count' => fn ($q) => $q->whereHas('reports'),
            ])
            // Bytes, not just file count: the abuse that never gets reported is
            // someone using a board as free file hosting, and three files can be
            // three hundred megabytes.
            ->withSum('materials', 'file_size')
            // Newest: who just signed up. Most visited: what classes use.
            // Recently opened: alive vs abandoned. Largest: storage, where
            // abuse (free file hosting) shows and never gets reported.
            ->when($boardSort === 'visited', fn ($q) => $q->orderByDesc('recent_arrivals'))
            ->when($boardSort === 'opened', fn ($q) => $q->orderByRaw('last_accessed_at IS NULL')->orderByDesc('last_accessed_at'))
            ->when($boardSort === 'largest', fn ($q) => $q->orderByDesc('materials_sum_file_size'))
            ->latest()
            ->latest('id')
            // Page links keep the tab, search and sort, not ?note= (the note
            // form belongs to the board it was opened from).
            ->paginate(20)
            ->appends(self::boardParams($boardQuery, $boardSort, $showEmpty));

        return view('operator.dashboard', [
            'materials' => $materials,
            'reportedTotal' => $reportedTotal,
            'stats' => $stats,
            'recent' => $recent,
            'boardQuery' => $boardQuery,
            'boardSort' => $boardSort,
            'showEmpty' => $showEmpty,
            'boardSorts' => self::BOARD_SORTS,
            'tab' => $this->tab($request),
            'undo' => $undo,
            'ambassadors' => $ambassadors = Ambassador::query()->orderBy('name')->get(),
            'ambassadorRows' => $this->ambassadorRows($ambassadors),
            'visitors' => $this->tab($request) === 'visitors' ? $this->visitorStats() : null,
            // Newest first; the tab badge counts only this week's, so it reads
            // as "new since you last looked" for an operator who checks weekly.
            'suggestions' => $this->tab($request) === 'suggestions' ? Suggestion::latest()->limit(100)->get() : collect(),
            'suggestionsWeek' => Suggestion::where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }

    /** The window the Visitors tab covers. Long enough to span a teaching block. */
    private const VISITOR_DAYS = 30;

    /** Boards tab sorts: key => label. The first is the default. */
    private const BOARD_SORTS = [
        'newest' => 'Newest',
        'visited' => 'Most visited',
        'opened' => 'Recently opened',
        'largest' => 'Largest',
    ];

    private static function boardSort(Request $request): string
    {
        $sort = $request->query('sort');

        return is_string($sort) && isset(self::BOARD_SORTS[$sort]) ? $sort : 'newest';
    }

    /** Query string for the boards list: tab, plus search, sort and "show empty" when set. */
    private static function boardParams(string $q, string $sort, bool $showEmpty = false, array $extra = []): array
    {
        return array_filter(['tab' => 'boards', 'q' => $q, 'sort' => $sort === 'newest' ? null : $sort, 'empty' => $showEmpty ? 1 : null] + $extra);
    }

    /**
     * Where arrivals came from over the window: by country, by source, and
     * the boards that drew them. Built from the daily tallies only, which
     * hold counts and nothing about any visitor.
     *
     * @return array<string, mixed>
     */
    private function visitorStats(): array
    {
        $since = now()->subDays(self::VISITOR_DAYS - 1)->toDateString();
        $base = fn () => DB::table('visit_tallies')->where('day', '>=', $since);

        $sum = fn (string $column, int $limit) => $base()
            ->select($column, DB::raw('SUM(arrivals) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $boards = $base()
            ->where('workspace_id', '>', 0)
            ->select('workspace_id', DB::raw('SUM(arrivals) as total'))
            ->groupBy('workspace_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Each listed board's leading country: "which board is the one used
        // from Saudi Arabia" is exactly the question this tab exists for.
        $countriesByBoard = $base()
            ->whereIn('workspace_id', $boards->pluck('workspace_id'))
            ->select('workspace_id', 'country', DB::raw('SUM(arrivals) as total'))
            ->groupBy('workspace_id', 'country')
            ->get()
            ->groupBy('workspace_id')
            ->map(fn ($rows) => $rows->sortByDesc('total')->values()->map(fn ($r) => $r->country)->all());

        $names = Workspace::query()->whereIn('id', $boards->pluck('workspace_id'))->get(['id', 'name', 'slug'])->keyBy('id');

        return [
            'days' => self::VISITOR_DAYS,
            'total' => (int) $base()->sum('arrivals'),
            'first_day' => DB::table('visit_tallies')->min('day'),
            'countries' => $sum('country', 15)->map(fn ($r) => [
                'code' => $r->country,
                'label' => self::countryLabel($r->country),
                'total' => (int) $r->total,
            ]),
            // IP-address "sources" recorded before VisitTally folded them are
            // folded here too: they are the site's own address, not a source.
            'sources' => $sum('source', 1000)
                ->groupBy(fn ($r) => filter_var($r->source, FILTER_VALIDATE_IP) ? 'other' : $r->source)
                ->map(fn ($rows, $source) => ['source' => $source, 'total' => (int) $rows->sum('total')])
                ->sortByDesc('total')
                ->take(12)
                ->values(),
            'boards' => $boards->map(fn ($r) => [
                'workspace' => $names->get($r->workspace_id),
                'total' => (int) $r->total,
                'countries' => array_map(
                    fn ($c) => self::countryLabel($c),
                    array_slice($countriesByBoard->get($r->workspace_id, []), 0, 3),
                ),
            ])->filter(fn ($r) => $r['workspace'] !== null)->values(),
        ];
    }

    /** "🇸🇦 Saudi Arabia" from "SA"; '' is the honest "Unknown". */
    private static function countryLabel(string $code): string
    {
        if ($code === '') {
            return 'Unknown';
        }

        $flag = implode('', array_map(
            fn ($ch) => mb_chr(0x1F1E6 + ord($ch) - ord('A')),
            str_split($code),
        ));
        $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$code, 'en') : $code;

        return $flag.' '.($name !== '' ? $name : $code);
    }

    /**
     * One row per ambassador with their numbers attached, so "who do I pay"
     * and "what's their number" are answered in the same place. Order is
     * the reward order: active ambassadors by live boards, then refs nobody
     * owns (worth a look), then retired ones with their history kept.
     *
     * @param  Collection<int, Ambassador>  $ambassadors
     * @return Collection<int, array{ambassador: ?Ambassador, slug: string, boards: int, seeded: int, active: int}>
     */
    private function ambassadorRows($ambassadors)
    {
        $stats = $this->referrerStats()->keyBy('slug');
        $zero = ['boards' => 0, 'seeded' => 0, 'active' => 0];

        // Due = used boards x the configured rate. Computed, never stored:
        // the payment record is the operator's own MoMo history.
        $rate = (int) config('noteshare.ambassador_reward');
        $withDue = fn (array $row) => $row + ['due' => $row['active'] * $rate];

        $rows = $ambassadors->map(fn (Ambassador $a) => $withDue(['ambassador' => $a, 'slug' => $a->slug]
            + ($stats->get($a->slug) ?? $zero)));

        $unknown = $stats
            ->reject(fn ($row) => $ambassadors->contains('slug', $row['slug']))
            ->map(fn ($row) => $withDue(['ambassador' => null] + $row));

        $byLive = fn ($r) => [-$r['active'], -$r['seeded'], -$r['boards'], $r['ambassador']?->name ?? $r['slug']];

        return $rows->reject(fn ($r) => $r['ambassador']->isRetired())->sortBy($byLive)->values()
            ->concat($unknown->sortBy($byLive)->values())
            ->concat($rows->filter(fn ($r) => $r['ambassador']->isRetired())->sortBy(fn ($r) => $r['ambassador']->name)->values());
    }

    /**
     * Per ref slug: boards created, seeded (has a file), used this month.
     * Only the last one is paid on -- and it is a 30-day window, not 7,
     * because payouts are monthly and a class that opened its board on the
     * 3rd and the 20th is live even if you look on the 28th. Slugs with no
     * matching ambassador are shown too: a typo, or someone guessing.
     *
     * @return Collection<int, array{slug:string, boards:int, seeded:int, active:int}>
     */
    private function referrerStats()
    {
        $monthAgo = now()->subDays(30);

        return Workspace::query()
            ->whereNotNull('referrer')
            ->withCount('materials')
            ->get(['id', 'referrer', 'last_accessed_at'])
            ->groupBy('referrer')
            ->map(fn ($boards, $slug) => [
                'slug' => $slug,
                'boards' => $boards->count(),
                'seeded' => $boards->where('materials_count', '>', 0)->count(),
                // Used means used as a board, so it must HAVE something: opened
                // recently AND holding at least one file. Without the file test
                // an empty board someone opens once a month counts and gets
                // paid, which is the sign-up farming the rule exists to stop.
                // It also makes the three columns nest: created >= with files
                // >= used.
                'active' => $boards->filter(fn ($w) => $w->materials_count > 0
                    && $w->last_accessed_at?->gte($monthAgo))->count(),
            ])
            ->sortByDesc('active')
            ->values();
    }

    /** Add an ambassador: a name, a unique ref slug, and where to send the reward. */
    public function storeAmbassador(Request $request)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        $request->merge(['slug' => strtolower(trim((string) $request->input('slug', '')))]);

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'slug' => ['required', 'string', 'max:40', 'regex:'.Ambassador::SLUG_PATTERN, 'unique:ambassadors,slug'],
            'campus' => 'nullable|string|max:80',
            'phone' => 'nullable|string|max:30',
            'network' => 'nullable|in:'.implode(',', array_keys(Ambassador::NETWORKS)),
        ], [
            'slug.regex' => 'Lowercase letters, digits and hyphens only, e.g. knust-kwame.',
            'slug.unique' => 'That ref is already taken. Never reuse one — a retired ambassador\'s boards still carry it.',
        ]);

        $ambassador = Ambassador::create([
            'name' => strip_tags($data['name']),
            'slug' => $data['slug'],
            'campus' => isset($data['campus']) ? strip_tags($data['campus']) : null,
            'phone' => $data['phone'] ?? null,
            'network' => $data['network'] ?? null,
        ]);

        return $this->dashboardRedirect($request, 'ambassadors')
            ->with('done', "{$ambassador->name} added. Copy their link or the invite message below.");
    }

    /** Retire: keeps the history, and the slug is never handed out again. */
    public function retireAmbassador(Request $request, Ambassador $ambassador)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        $ambassador->forceFill(['retired_at' => now()])->save();

        return $this->dashboardRedirect($request, 'ambassadors')
            ->with('done', "{$ambassador->name} retired. Their boards keep the ref; it won't be reused.");
    }

    /** Enter the operator secret (timing-safe, rate-limited). */
    public function login(Request $request)
    {
        abort_unless($this->enabled(), 404);

        $key = 'operator_login:'.ClientKey::of($request);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['secret' => 'Too many attempts. Try again in a few minutes.']);
        }

        $given = (string) $request->input('secret', '');
        if ($given !== '' && hash_equals((string) config('noteshare.operator_secret'), $given)) {
            RateLimiter::clear($key);
            $request->session()->regenerate(); // anti-fixation on privilege change
            // Bind the session to THIS secret value; rotating it logs the
            // session out (authed() compares against the current secret).
            session(['operator_fp' => $this->secretFingerprint()]);

            return redirect()->route('operator.dashboard');
        }

        RateLimiter::hit($key, 600);

        return back()->withErrors(['secret' => "That operator secret isn't right."]);
    }

    public function logout(Request $request)
    {
        // Logging out commits whatever was waiting on Undo — the file stays
        // gone, the trash copy is dropped, the session no longer holds it.
        $this->commitUndo();
        // Drop the whole session, not just the flag: a logout should leave
        // nothing for a later visitor on the same browser to inherit, and a
        // fresh id + CSRF token mirrors the regenerate() done at login.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('operator.dashboard');
    }

    /**
     * Take the file down and blocklist the bytes so the same file can't come
     * back. The bytes sit in a short-lived trash path so Undo can put them
     * back; after the window (or logout) the trash is dropped.
     */
    public function remove(Request $request, Material $material)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        $this->commitUndo();

        $reports = $this->snapshotReports($material);
        $attributes = $material->getAttributes();
        $hash = null;
        $trashPath = null;
        $createdBlock = false;

        if (Storage::disk('local')->exists($material->stored_path)) {
            $hash = hash('sha256', Storage::disk('local')->get($material->stored_path));
            $trashPath = 'operator-undo/'.$material->id.'-'.Str::random(12);
            if (! Storage::disk('local')->copy($material->stored_path, $trashPath)) {
                $trashPath = null;
            }

            $createdBlock = BlockedUpload::query()
                ->where('content_hash', $hash)
                ->doesntExist();
            BlockedUpload::firstOrCreate(['content_hash' => $hash]);
        }

        Storage::disk('local')->delete($material->stored_path);
        $material->delete();

        $this->stashUndo([
            'action' => 'remove',
            'label' => $attributes['title']
                ?: pathinfo((string) $attributes['original_filename'], PATHINFO_FILENAME),
            'material' => $attributes,
            'reports' => $reports,
            'hash' => $hash,
            'trash_path' => $trashPath,
            'created_block' => $createdBlock,
        ]);

        return $this->dashboardRedirect($request)->with('done', 'File removed. Same bytes can’t be re-uploaded.');
    }

    /** Clear a file's reports without deleting it (a false alarm). */
    public function dismiss(Request $request, Material $material)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        $this->commitUndo();

        $this->stashUndo([
            'action' => 'dismiss',
            'label' => $material->displayName(),
            'material_id' => $material->id,
            'reports' => $this->snapshotReports($material),
        ]);

        $material->reports()->delete();

        return $this->dashboardRedirect($request)->with('done', 'Reports dismissed.');
    }

    /**
     * Leave (or clear, with an empty note) a message for one board's owner.
     * It shows only in owner mode on that board, and they reply through the
     * suggestion form, so the operator never needs the owner's contact.
     */
    public function saveNote(Request $request, Workspace $board)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        // Back to the same list (page, search, sort), which the note dialog
        // opened over; on a validation error the page reopens that dialog.
        $back = $this->boardsListUrl($request);
        $validator = validator($request->all(), ['note' => 'nullable|string|max:500']);
        if ($validator->fails()) {
            return redirect($back)->withErrors($validator)->withInput()->with('noteFor', $board->id);
        }
        $note = trim(strip_tags((string) $request->input('note', '')));

        $board->forceFill([
            'operator_note' => $note !== '' ? $note : null,
            'operator_note_at' => $note !== '' ? now() : null,
        ])->save();

        return redirect($back)
            ->with('done', $note !== '' ? "Note left for the owner of “{$board->name}”." : 'Note removed.');
    }

    /** Delete a suggestion: read and dealt with, or its sender asked for it gone. */
    public function deleteSuggestion(Request $request, Suggestion $suggestion)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        $suggestion->delete();

        return $this->dashboardRedirect($request, 'suggestions')->with('done', 'Suggestion deleted.');
    }

    /** Reverse the last dismiss or remove, if the window hasn't closed. */
    public function undo(Request $request)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        $undo = $this->activeUndo();
        if ($undo === null) {
            return $this->dashboardRedirect($request)
                ->withErrors(['undo' => 'Nothing left to undo.']);
        }

        if ($undo['action'] === 'dismiss') {
            $this->restoreDismiss($undo);
        } elseif ($undo['action'] === 'remove') {
            $this->restoreRemove($undo);
        }

        session()->forget(self::UNDO_SESSION);

        return $this->dashboardRedirect($request)->with('done', 'Undone.');
    }

    /**
     * @return list<array{reason: ?string, reporter_ip: ?string, created_at: ?string}>
     */
    private function snapshotReports(Material $material): array
    {
        return $material->reports()->get()->map(fn (Report $report) => [
            'reason' => $report->reason,
            'reporter_ip' => $report->reporter_ip,
            'created_at' => $report->created_at?->toDateTimeString(),
        ])->all();
    }

    /** @param  array<string, mixed>  $payload */
    private function stashUndo(array $payload): void
    {
        $payload['expires_at'] = now()->addSeconds(self::UNDO_TTL_SECONDS)->getTimestamp();
        session([self::UNDO_SESSION => $payload]);
    }

    /** @return array<string, mixed>|null */
    private function activeUndo(): ?array
    {
        $undo = session(self::UNDO_SESSION);
        if (! is_array($undo) || ! isset($undo['expires_at'], $undo['action'])) {
            return null;
        }

        if ((int) $undo['expires_at'] < now()->getTimestamp()) {
            $this->commitUndo();

            return null;
        }

        return $undo;
    }

    /** Drop a pending undo without restoring — the action stands. */
    private function commitUndo(): void
    {
        $undo = session(self::UNDO_SESSION);
        if (is_array($undo) && is_string($undo['trash_path'] ?? null)) {
            Storage::disk('local')->delete($undo['trash_path']);
        }

        session()->forget(self::UNDO_SESSION);
    }

    /** @param  array<string, mixed>  $undo */
    private function restoreDismiss(array $undo): void
    {
        $material = Material::find($undo['material_id'] ?? null);
        if ($material === null) {
            return;
        }

        $this->restoreReports($material, $undo['reports'] ?? []);
    }

    /** @param  array<string, mixed>  $undo */
    private function restoreRemove(array $undo): void
    {
        $attrs = $undo['material'] ?? null;
        if (! is_array($attrs) || ! isset($attrs['id'])) {
            return;
        }

        if (Material::query()->whereKey($attrs['id'])->exists()) {
            return;
        }

        $trash = is_string($undo['trash_path'] ?? null) ? $undo['trash_path'] : null;
        $storedPath = $attrs['stored_path'] ?? null;
        if (is_string($trash) && is_string($storedPath) && Storage::disk('local')->exists($trash)) {
            Storage::disk('local')->move($trash, $storedPath);
        }

        $material = new Material;
        $material->timestamps = false;
        $material->forceFill($attrs);
        $material->save();

        $this->restoreReports($material, $undo['reports'] ?? []);

        if (! empty($undo['created_block']) && is_string($undo['hash'] ?? null)) {
            BlockedUpload::query()->where('content_hash', $undo['hash'])->delete();
        }
    }

    /**
     * @param  list<array{reason?: ?string, reporter_ip?: ?string, created_at?: ?string}>  $rows
     */
    private function restoreReports(Material $material, array $rows): void
    {
        foreach ($rows as $row) {
            $report = $material->reports()->make([
                'reason' => $row['reason'] ?? null,
                'reporter_ip' => $row['reporter_ip'] ?? null,
            ]);
            if (! empty($row['created_at'])) {
                $report->created_at = $row['created_at'];
            }
            $report->save();
        }
    }
}
