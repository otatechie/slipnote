<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ClientIpFromHeader;
use App\Models\Ambassador;
use App\Models\BlockedUpload;
use App\Models\Material;
use App\Models\Report;
use App\Models\Suggestion;
use App\Models\Workspace;
use App\Services\TelegramNotifier;
use App\Support\ClientKey;
use App\Support\OperatorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OperatorController extends Controller
{
    private const UNDO_SESSION = 'operator_undo';

    /** Long enough to catch the wrong row, short enough the trash doesn't linger. */
    private const UNDO_TTL_SECONDS = 300;

    private function enabled(): bool
    {
        return filled(config('noteshare.operator_secret'));
    }

    private function authed(): bool
    {
        return OperatorSession::isActive();
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

    private function boardsListUrl(Request $request): string
    {
        parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $from);
        $page = (int) ($from['page'] ?? 1);
        $sort = is_string($from['sort'] ?? null) && isset(self::BOARD_SORTS[$from['sort']]) ? $from['sort'] : self::DEFAULT_BOARD_SORT;

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

        Workspace::pruneAbandonedDaily();
        $undo = $this->activeUndo();

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
            // Boards classmates (not the owner) opened or downloaded from this week.
            'active_week' => DB::table('board_visit_days')->where('day', '>=', now()->subDays(6)->toDateString())->distinct()->count('workspace_id'),
            'tracking_started' => DB::table('board_visit_days')->exists(),
            // Created and never filled — the drop-off worth watching.
            'empty_boards' => Workspace::whereDoesntHave('materials')->count(),
        ];

        $boardQuery = trim((string) $request->query('q', ''));
        $boardSort = self::boardSort($request);
        $showEmpty = $request->query('empty') === '1';
        $visitsSince = now()->subDays(self::VISITOR_DAYS - 1)->toDateString();

        $recent = Workspace::query()
            ->select('workspaces.*')
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
                'materials as reported_count' => fn ($q) => $q->whereHas('reports'),
            ])
            ->withSum('materials', 'file_size')
            ->withMax('materials', 'created_at')
            ->when($boardSort === 'visited', fn ($q) => $q->orderByDesc('recent_arrivals')->orderByDesc('materials_count'))
            ->when($boardSort === 'opened', fn ($q) => $q->orderByRaw('last_accessed_at IS NULL')->orderByDesc('last_accessed_at'))
            ->when($boardSort === 'largest', fn ($q) => $q->orderByDesc('materials_sum_file_size'))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->appends(self::boardParams($boardQuery, $boardSort, $showEmpty));
        $this->describeBoards($recent->getCollection(), $visitsSince);

        return view('operator.dashboard', [
            'materials' => $materials,
            'reportedTotal' => $reportedTotal,
            'stats' => $stats,
            'recent' => $recent,
            'boardQuery' => $boardQuery,
            'boardSort' => $boardSort,
            'defaultSort' => self::DEFAULT_BOARD_SORT,
            'showEmpty' => $showEmpty,
            'boardSorts' => self::BOARD_SORTS,
            'tab' => $this->tab($request),
            'undo' => $undo,
            'ambassadors' => $ambassadors = Ambassador::query()->orderBy('name')->get(),
            'ambassadorRows' => $this->ambassadorRows($ambassadors),
            'visitors' => $this->tab($request) === 'visitors' ? $this->visitorStats() : null,
            'suggestions' => $this->tab($request) === 'suggestions' ? Suggestion::latest()->limit(100)->get() : collect(),
            'suggestionsWeek' => Suggestion::where('created_at', '>=', now()->subDays(7))->count(),
            'setupWarnings' => $this->setupWarnings($request),
            'telegramFailed' => (bool) Cache::get(TelegramNotifier::FAILED_KEY),
        ]);
    }

    public function telegramTest(Request $request)
    {
        abort_unless($this->enabled() && $this->authed(), 404);

        $ok = app(TelegramNotifier::class)->notifyTest();

        return $this->dashboardRedirect($request)->with('done', $ok
            ? 'Test message sent. Telegram alerts are working.'
            : 'Telegram refused the test message. Check TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID.');
    }

    /** Deployment settings that quietly hurt boards: each a fix in .env. */
    private function setupWarnings(Request $request): array
    {
        $warnings = [];

        if (in_array(config('mail.default'), ['log', 'array', null], true)) {
            $warnings[] = 'Mail is off (MAIL_MAILER=log). Owners who lose their link can\'t recover it, and empty boards are removed without a warning email.';
        }

        if (blank(config('noteshare.telegram_bot_token')) || blank(config('noteshare.telegram_chat_id'))) {
            $warnings[] = 'Telegram isn\'t set up, so reports only reach the log. Check the Reported tab yourself to keep the '.config('noteshare.takedown_turnaround').' takedown promise.';
        } elseif ($failedAt = Cache::get(TelegramNotifier::FAILED_KEY)) {
            $warnings[] = 'The last Telegram alert failed to send ('.Carbon::parse($failedAt)->diffForHumans().'). Check TELEGRAM_BOT_TOKEN and that the bot is still in the chat; reports may not be reaching you.';
        }

        $proxied = $request->headers->has('X-Forwarded-For') || $request->headers->has('CF-Connecting-IP');
        if ($proxied && ! ClientIpFromHeader::viaTrustedProxy($request)) {
            $warnings[] = 'This request came through a proxy that TRUSTED_PROXIES doesn\'t list. Every visitor shares the proxy\'s address, so one busy class trips the upload and report limits for everyone.';
        } elseif ($request->headers->has('CF-Connecting-IP') && blank(config('noteshare.client_ip_header'))) {
            $warnings[] = 'You\'re behind Cloudflare but CLIENT_IP_HEADER is empty, so rate limits see Cloudflare\'s address. Set CLIENT_IP_HEADER=CF-Connecting-IP.';
        }

        if (app()->isProduction() && config('app.debug')) {
            $warnings[] = 'APP_DEBUG is on in production: an error page shows visitors your code and settings.';
        }

        if ($request->isSecure() && ! config('session.secure')) {
            $warnings[] = 'SESSION_SECURE_COOKIE is off on HTTPS: the session cookie (owner mode included) can be sent over plain http.';
        }

        return $warnings;
    }

    /** Who is behind each board on the page: where its visitors are, and what they've written in. */
    private function describeBoards($boards, string $visitsSince): void
    {
        if ($boards->isEmpty()) {
            return;
        }

        $topCountry = DB::table('visit_tallies')
            ->whereIn('workspace_id', $boards->pluck('id'))
            ->where('day', '>=', $visitsSince)
            ->where('country', '!=', '')
            ->select('workspace_id', 'country', DB::raw('SUM(arrivals) as total'))
            ->groupBy('workspace_id', 'country')
            ->get()
            ->groupBy('workspace_id')
            ->map(fn ($rows) => self::countryLabel($rows->sortByDesc('total')->first()->country));

        // A suggestion sent from the board or any page under it.
        $messages = Suggestion::query()
            ->where(function ($q) use ($boards) {
                foreach ($boards as $ws) {
                    $q->orWhere('page', '/'.$ws->slug)->orWhere('page', 'like', '/'.$ws->slug.'/%');
                }
            })
            ->pluck('page')
            ->countBy(fn ($page) => explode('/', $page)[1] ?? '');

        $boards->each(function (Workspace $ws) use ($topCountry, $messages) {
            $ws->top_country = $topCountry->get($ws->id);
            $ws->message_count = (int) $messages->get($ws->slug, 0);
        });
    }

    /** The window the Visitors tab covers. Long enough to span a teaching block. */
    private const VISITOR_DAYS = 30;

    /** Boards tab sorts: key => label. The first is the default. */
    private const BOARD_SORTS = [
        'visited' => 'Most visited',
        'newest' => 'Newest',
        'opened' => 'Recently opened',
        'largest' => 'Largest',
    ];

    /** The boards that matter are the ones classmates open, so those come first. */
    public const DEFAULT_BOARD_SORT = 'visited';

    private static function boardSort(Request $request): string
    {
        $sort = $request->query('sort');

        return is_string($sort) && isset(self::BOARD_SORTS[$sort]) ? $sort : self::DEFAULT_BOARD_SORT;
    }

    /** Query string for the boards list: tab, plus search, sort and "show empty" when set. */
    private static function boardParams(string $q, string $sort, bool $showEmpty = false, array $extra = []): array
    {
        return array_filter(['tab' => 'boards', 'q' => $q, 'sort' => $sort === self::DEFAULT_BOARD_SORT ? null : $sort, 'empty' => $showEmpty ? 1 : null] + $extra);
    }

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

    private function ambassadorRows($ambassadors)
    {
        $stats = $this->referrerStats()->keyBy('slug');
        $zero = ['boards' => 0, 'seeded' => 0, 'active' => 0];

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

    private function referrerStats()
    {
        $since = now()->subDays(29)->toDateString();

        // Used = files, and people other than the owner on several days. The
        // owner (often the ambassador) opening it alone never pays out.
        return Workspace::query()
            ->whereNotNull('referrer')
            ->select(['id', 'referrer'])
            ->selectSub(fn ($q) => $q->from('board_visit_days')
                ->selectRaw('COUNT(*)')
                ->whereColumn('board_visit_days.workspace_id', 'workspaces.id')
                ->where('day', '>=', $since), 'visitor_days')
            ->withCount('materials')
            ->get()
            ->groupBy('referrer')
            ->map(fn ($boards, $slug) => [
                'slug' => $slug,
                'boards' => $boards->count(),
                'seeded' => $boards->where('materials_count', '>', 0)->count(),
                'active' => $boards->filter(fn ($w) => $w->materials_count > 0
                    && $w->visitor_days >= Workspace::USED_MIN_DAYS)->count(),
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
            session([OperatorSession::KEY => OperatorSession::fingerprint()]);

            return redirect()->route('operator.dashboard');
        }

        RateLimiter::hit($key, 600);

        return back()->withErrors(['secret' => "That operator secret isn't right."]);
    }

    public function logout(Request $request)
    {
        $this->commitUndo();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('operator.dashboard');
    }

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

    public function saveNote(Request $request, Workspace $board)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        $back = $this->boardsListUrl($request);
        $validator = validator($request->all(), ['note' => 'nullable|string|max:500']);
        if ($validator->fails()) {
            return redirect($back)->withErrors($validator)->withInput()->with('noteFor', $board->id);
        }
        $note = trim(strip_tags((string) $request->input('note', '')));

        $board->forceFill([
            'operator_note' => $note !== '' ? $note : null,
            'operator_note_at' => $note !== '' ? now() : null,
            'operator_note_seen_at' => null,
        ])->save();

        return redirect($back)
            ->with('done', $note !== '' ? "Note left for the owner of “{$board->name}”." : 'Note removed.');
    }

    public function deleteBoard(Request $request, Workspace $board)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        $back = $this->boardsListUrl($request);
        $name = $board->name;
        $files = $board->materials()->count();
        $board->purge();

        return redirect($back)->with('done', "Deleted “{$name}”".($files > 0 ? ' and its '.$files.' '.Str::plural('file', $files) : '').'.');
    }

    /** Delete a suggestion: read and dealt with, or its sender asked for it gone. */
    public function deleteSuggestion(Request $request, Suggestion $suggestion)
    {
        abort_unless($this->enabled() && $this->authed(), 403);

        $this->commitUndo();
        $this->stashUndo([
            'action' => 'suggestion',
            'label' => Str::limit($suggestion->message, 30),
            'suggestion' => $suggestion->getAttributes(),
        ]);

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
        } elseif ($undo['action'] === 'suggestion') {
            $this->restoreSuggestion($undo);
        }

        session()->forget(self::UNDO_SESSION);

        return $this->dashboardRedirect($request, $undo['action'] === 'suggestion' ? 'suggestions' : null)->with('done', 'Undone.');
    }

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
    private function restoreSuggestion(array $undo): void
    {
        $attrs = $undo['suggestion'] ?? null;
        if (! is_array($attrs) || ! isset($attrs['id']) || Suggestion::query()->whereKey($attrs['id'])->exists()) {
            return;
        }

        Suggestion::query()->insert($attrs);
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
