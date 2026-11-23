<?php

namespace Database\Seeders;

use App\Models\Ambassador;
use App\Models\Course;
use App\Models\Material;
use App\Models\Suggestion;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Local demo boards for looking at the UI in every state that matters: a busy
 * board (search, sort, many files, every section), a small one with an empty
 * course, a board with no courses, a long board name, and a board near its
 * storage cap, plus a long class board shaped like real homework collection
 * (dozens of phone photos per section, so sections fold). Re-runnable: it deletes its own boards (and their files) first.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * PDFs and images are real files, so preview and download work. Office files
 * are placeholder bytes, so they download but won't open. file_size is a
 * realistic display size, not the placeholder's size on disk.
 *
 * It also fills every tab of the operator dashboard: reports (serious and
 * ordinary, several on one file), thirty days of visits by country and source,
 * suggestions sent from the boards and from the site, and ambassadors with
 * boards credited to them.
 */
class DemoSeeder extends Seeder
{
    private const BOARDS = [
        'Nursing Level 200' => [
            ['NUR 201', 'Adult health nursing', [
                ['notes', 'Week 1 — Fluid and electrolyte balance', 'week-1-fluids.pdf', 'Ama', 2_400_000, '-2 hours'],
                ['notes', 'Week 2 — Cardiovascular assessment', 'week-2-cardio.pdf', 'Kofi', 3_100_000, '-1 day'],
                ['notes', 'Week 3 — Respiratory care (handwritten, scanned)', 'IMG_20261003_0912.jpg', 'Esi', 1_800_000, '-3 days'],
                ['notes', null, 'Week4_Renal_Notes_FINAL_final_v2 (1).pdf', null, 4_200_000, '-5 days'],
                ['slides', 'Lecture 1 — Introduction to adult health', 'lecture-01-intro.pptx', 'Dr. Mensah', 8_900_000, '-2 weeks'],
                ['slides', 'Lecture 2 — Shock and its management', 'lecture-02-shock.pdf', 'Dr. Mensah', 6_400_000, '-10 days'],
                ['slides', 'Lecture 3 — Diabetes mellitus', 'lecture-03-diabetes.pdf', 'Dr. Mensah', 5_700_000, '-6 days'],
                ['past_papers', '2025 mid-semester exam', 'nur201-midsem-2025.pdf', 'Yaw', 900_000, '-1 month'],
                ['past_papers', '2024 end of semester exam with marking scheme', 'nur201-final-2024.pdf', null, 1_300_000, '-2 months'],
            ]],
            ['NUR 203', 'Pharmacology for nurses', [
                ['notes', 'Drug calculations cheat sheet', 'drug-calcs.pdf', 'Abena', 300_000, '-4 hours'],
                ['slides', 'Antibiotics overview', 'antibiotics.pdf', 'Dr. Owusu', 7_200_000, '-2 days'],
                ['past_papers', '2025 quiz 1', 'quiz-1-2025.jpg', 'Kwame', 600_000, '-3 weeks'],
            ]],
            ['NUR 205', 'Community health nursing', [
                ['notes', 'Home visit checklist', 'home-visit-checklist.docx', 'Efua', 150_000, '-1 week'],
            ]],
            ['BIO 210', 'Microbiology and parasitology', [
                ['slides', 'Bacterial morphology', 'bacteria.pdf', 'Dr. Asante', 9_800_000, '-3 days'],
                ['slides', 'Parasites of medical importance', 'parasites.pptx', 'Dr. Asante', 12_500_000, '-1 day'],
            ]],
            ['PSY 201', 'Psychology for health professionals', []],
            ['ENG 201', 'Communication skills', [
                ['past_papers', '2023 exam', 'eng201-2023.pdf', null, 700_000, '-1 year'],
            ]],
        ],
        'Business Admin Level 100' => [
            ['BUS 101', 'Introduction to business', [
                ['notes', 'Chapter 1 summary', 'chapter-1.pdf', 'Nana', 450_000, '-6 hours'],
                ['slides', 'Week 1 slides', 'week-1.pdf', 'Mr. Boateng', 2_200_000, '-2 days'],
            ]],
            ['ACC 101', 'Principles of accounting', []],
        ],
        'Empty Demo Board' => [],
        'Bachelor of Science in Computer Engineering, Level 300 Evening Cohort' => [
            ['CPEN 301', 'Signals and systems', [
                ['notes', 'Fourier series — worked examples from the tutorial session on Thursday evening', 'fourier.pdf', 'Selorm', 1_100_000, '-1 day'],
            ]],
        ],
        'Near Full Demo Board' => [
            ['MED 101', 'Anatomy atlas', [
                ['slides', 'Full anatomy atlas, all chapters', 'atlas.pdf', 'Dr. Addo', 470_000_000, '-1 week'],
                ['notes', 'Upper limb notes', 'upper-limb.pdf', 'Akua', 1_500_000, '-2 days'],
            ]],
        ],
        'Archived Demo Board' => [
            ['HIS 101', 'History of Ghana', [
                ['notes', 'Week 1 — Pre-colonial states', 'his101-week-1.pdf', 'Yaa', 800_000, '-5 months'],
                ['past_papers', '2024 end of semester exam', 'his101-2024.pdf', null, 600_000, '-6 months'],
            ]],
        ],
    ];

    /** Name, ref slug, campus, phone, network, retired. The people are invented. */
    private const AMBASSADORS = [
        ['Ama Kyei', 'ama-k', 'University of Ghana, Legon', '0244 015 882', 'mtn', false],
        ['Kwesi Boadu', 'kwesi-b', 'KNUST, Kumasi', '0205 441 207', 'telecel', false],
        ['Fatima Suleman', 'fatima-s', 'UDS, Tamale', '0277 309 614', 'airteltigo', false],
        ['Jojo Mensah', 'jojo-m', 'UCC, Cape Coast', null, null, true],
    ];

    /**
     * What the Boards tab shows beside each board. 'poster-qr' is a ref with
     * no ambassador behind it, so the Ambassadors tab shows an unknown row.
     */
    private const BOARD_EXTRAS = [
        'Nursing Level 200' => [
            'created' => '-7 weeks', 'opened' => '-20 minutes', 'ref' => 'ama-k', 'email' => 'nur200.rep@example.com',
            'note' => 'Your board is one of the busiest on SlipNote. If Level 300 wants one too, just make it from the Start page.',
            'noteAt' => '-4 days', 'noteSeen' => '-3 days',
        ],
        'Business Admin Level 100' => ['created' => '-3 weeks', 'opened' => '-1 day', 'ref' => 'kwesi-b'],
        'Empty Demo Board' => ['created' => '-5 days', 'ref' => 'kwesi-b'],
        'Bachelor of Science in Computer Engineering, Level 300 Evening Cohort' => [
            'created' => '-10 days', 'opened' => '-2 days', 'ref' => 'poster-qr',
            'note' => 'Two files on this board were reported as copied from a textbook. Please check them.',
            'noteAt' => '-1 day',
        ],
        'Near Full Demo Board' => ['created' => '-2 weeks', 'opened' => '-6 hours', 'email' => 'med101@example.com'],
        'Archived Demo Board' => ['created' => '-7 months', 'opened' => '-4 months', 'ref' => 'jojo-m', 'archived' => '-3 weeks'],
        'English Year 8 Demo Board' => ['created' => '-10 weeks', 'opened' => '-2 hours', 'ref' => 'fatima-s', 'email' => 'owusu.eng@example.com'],
    ];

    /** Board, course code, file, reason as the report form sends it, when. */
    private const REPORTS = [
        ['Nursing Level 200', 'NUR 201', 'lecture-02-shock.pdf', 'Copyright — shouldn\'t be shared — This is Dr. Mensah’s own slide deck. He asked us not to share it outside class.', '-35 minutes'],
        ['Nursing Level 200', 'NUR 201', 'lecture-02-shock.pdf', 'Copyright — shouldn\'t be shared', '-3 hours'],
        ['Nursing Level 200', 'NUR 201', 'IMG_20261003_0912.jpg', 'Inappropriate or offensive — A patient’s name and face are on the second page.', '-6 hours'],
        ['Nursing Level 200', 'NUR 201', 'Week4_Renal_Notes_FINAL_final_v2 (1).pdf', 'Not my notes / wrong file — This is last year’s version, the course outline changed.', '-1 day'],
        ['Nursing Level 200', 'NUR 201', 'Week4_Renal_Notes_FINAL_final_v2 (1).pdf', 'Not my notes / wrong file', '-2 days'],
        ['Nursing Level 200', 'NUR 201', 'Week4_Renal_Notes_FINAL_final_v2 (1).pdf', 'Other — Same as the week 3 file, uploaded twice.', '-2 days'],
        ['Nursing Level 200', 'NUR 203', 'antibiotics.pdf', 'Spam', '-4 days'],
        ['Nursing Level 200', 'BIO 210', 'parasites.pptx', 'Copyright — shouldn\'t be shared — Every slide is a scan from the published textbook.', '-2 days'],
        ['Business Admin Level 100', 'BUS 101', 'chapter-1.pdf', 'Not my notes / wrong file', '-3 days'],
        ['English Year 8 Demo Board', 'ENG 108', 'week-3-grammar.pdf', 'Other — It won’t open on my phone, it downloads as 0 KB.', '-8 hours'],
        ['Bachelor of Science in Computer Engineering, Level 300 Evening Cohort', 'CPEN 301', 'fourier.pdf', 'Copyright — shouldn\'t be shared — Copied from the Oppenheim textbook solutions.', '-1 day'],
    ];

    /**
     * Message, reply-to, where it was sent from, when. "Where" is a board name
     * (with "|course-slug" for a course page) or a site path.
     */
    private const SUGGESTIONS = [
        ['Can you add a way to see only this week’s uploads? Scrolling past old notes on my phone takes forever.', null, 'Nursing Level 200', '-5 hours'],
        ['The pharmacology slides open sideways on my phone. Everything else is fine.', 'abena.o@example.com', 'Nursing Level 200|nur-203', '-2 days'],
        ['Please can we rename a course after creating it? I typed NUR 2O1 with a letter O.', 'kofi.mensah@example.com', 'Nursing Level 200', '-9 days'],
        ['Thank you for this. Our class WhatsApp was losing files after two days, now everything is in one place.', null, 'Business Admin Level 100', '-1 day'],
        ['Is there a limit on how many past papers we can put? We have about 60.', '0551 203 774', 'Business Admin Level 100|bus-101', '-12 days'],
        ['Downloading 40 photos one by one is slow. A download-all button would help a lot.', null, 'English Year 8 Demo Board|eng-108', '-4 hours'],
        ['Can parents see the homework photos? Some of them asked me before I shared the link.', 'owusu.eng@example.com', 'English Year 8 Demo Board', '-3 days'],
        ['Some students upload the same page twice. Maybe tell them it’s already there.', null, 'English Year 8 Demo Board|eng-108', '-16 days'],
        ['The board link is too long for a WhatsApp status. Can we have a short one?', null, 'Bachelor of Science in Computer Engineering, Level 300 Evening Cohort', '-6 days'],
        ['Does this work on slow data? Our hostel network is bad in the evening.', null, '/', '-1 day'],
        ['I lost my owner link when I changed phones. How do I get it back?', 'selorm.k@example.com', '/start', '-2 days'],
        ['Please add a dark mode, we read at night.', null, '/', '-8 days'],
        ["School: St. Mary’s Senior High, Accra\nFrom: Grace Adjei\nClasses: 12\n\nWe’d like to try this for the science department next term. Can each class have its own board?", '0244 118 203', '/schools', '-5 days'],
        ['Who can see the files? I want to be sure only my class has the link.', 'rep.cpen300@example.com', '/', '-20 days'],
    ];

    /** Arrivals on a weekday, then country and source weights. Weekends run at half. */
    private const AUDIENCES = [
        'Nursing Level 200' => [46,
            ['GH' => 78, 'NG' => 6, '' => 8, 'GB' => 4, 'US' => 4],
            ['whatsapp' => 52, 'direct' => 22, 'google' => 8, 'ambassador' => 6, 'telegram' => 5, 'moodle.ug.edu.gh' => 7]],
        'English Year 8 Demo Board' => [31,
            ['SA' => 62, 'GH' => 22, 'AE' => 6, '' => 10],
            ['whatsapp' => 64, 'direct' => 26, 'ambassador' => 6, 'instagram' => 4]],
        'Business Admin Level 100' => [9,
            ['GH' => 85, '' => 15],
            ['whatsapp' => 60, 'direct' => 30, 'ambassador' => 10]],
        'Bachelor of Science in Computer Engineering, Level 300 Evening Cohort' => [6,
            ['GH' => 70, 'NG' => 20, '' => 10],
            ['whatsapp' => 45, 'telegram' => 30, 'direct' => 25]],
        'Near Full Demo Board' => [3,
            ['GH' => 90, '' => 10],
            ['direct' => 70, 'whatsapp' => 30]],
    ];

    public function run(): void
    {
        $links = [];
        $boards = [];

        foreach (self::BOARDS as $name => $courses) {
            $this->deleteExisting($name);

            [$ws, $secret] = Workspace::provision($name);

            foreach (array_values($courses) as $i => [$code, $title, $files]) {
                /** @var Course $course */
                $course = $ws->courses()->create([
                    'code' => $code,
                    'title' => $title,
                    'slug' => Str::slug($code),
                    'position' => $i + 1,
                ]);

                foreach ($files as [$section, $fileTitle, $filename, $uploader, $size, $ago]) {
                    $this->addFile($course, $section, $fileTitle, $filename, $uploader, $size, $ago);
                }
            }

            $boards[$name] = $ws;
            $links[] = [$name, url('/'.$ws->slug), url('/'.$ws->slug).'?owner='.$secret];
        }

        [$ws, $links[]] = $this->longClassBoard();
        $boards[$ws->name] = $ws;

        $this->operatorData($boards);

        $this->command?->newLine();
        foreach ($links as [$name, $share, $owner]) {
            $this->command?->line("<info>{$name}</info>");
            $this->command?->line("  share: {$share}");
            $this->command?->line("  owner: {$owner}");
        }
        $this->command?->newLine();
        $this->command?->line('<info>Operator dashboard</info> '.url('/operator').' (OPERATOR_SECRET from .env)');
    }

    /**
     * A class collecting homework: students photograph their pages and upload
     * them one assignment at a time, so a section runs to dozens of rows.
     * The names are invented.
     */
    private function longClassBoard(): array
    {
        $name = 'English Year 8 Demo Board';
        $this->deleteExisting($name);
        [$ws, $secret] = Workspace::provision($name);
        mt_srand(8);

        $students = ['Kwabena Asare', 'Layla Haddad', 'Yusuf Al-Amin', 'Akosua Frimpong', 'Omar Khalil',
            'Efua Mensima', 'Hamza Saleh', 'Nadia Boateng', 'Kojo Antwi', 'Mariam Yousef', 'سارة أحمد', 'علي حسن'];
        $tasks = ['Homework — present continuous tense', 'Classwork — reading comprehension', 'Notebook work and worksheets',
            'Homework — past simple questions', 'Writing task — my best friend'];

        $english = $ws->courses()->create(['code' => 'ENG 108', 'title' => 'English, Year 8', 'slug' => 'eng-108', 'position' => 1]);
        $img = 3380;
        $hours = 2;
        foreach ($students as $student) {
            foreach (array_rand(array_flip($tasks), 2) as $task) {
                $hours += mt_rand(1, 9);
                if (mt_rand(0, 1)) {
                    // A scanned PDF, named after the student.
                    $this->addFile($english, 'notes', "{$student} — {$task}", "{$student}.pdf", null, mt_rand(600_000, 21_000_000), "-{$hours} hours");
                } else {
                    // Page photos straight from the phone, untitled.
                    foreach (range(1, mt_rand(2, 5)) as $page) {
                        $this->addFile($english, 'notes', null, 'IMG_'.($img++).'.jpg', null, mt_rand(2_600_000, 4_200_000), "-{$hours} hours");
                    }
                }
            }
        }
        foreach (range(1, 8) as $week) {
            $this->addFile($english, 'slides', "Week {$week} — grammar lesson", "week-{$week}-grammar.pdf", 'Mrs. Owusu', mt_rand(1_500_000, 9_000_000), '-'.(70 - $week * 7).' days');
        }
        foreach (range(2014, 2025) as $year) {
            foreach (['Term 1', 'Term 2'] as $term) {
                $this->addFile($english, 'past_papers', "{$year} {$term} exam", "eng8-{$year}-".Str::slug($term).'.pdf', null, mt_rand(300_000, 1_400_000), '-'.(2026 - $year).' years');
            }
        }

        $maths = $ws->courses()->create(['code' => 'MATH 108', 'title' => 'Mathematics, Year 8', 'slug' => 'math-108', 'position' => 2]);
        foreach (range(1, 12) as $i) {
            $this->addFile($maths, 'notes', "Exercise {$i} solutions", "exercise-{$i}.pdf", 'Mr. Darko', mt_rand(200_000, 2_000_000), "-{$i} days");
        }
        $ws->courses()->create(['code' => 'SCI 108', 'title' => 'Integrated science, Year 8', 'slug' => 'sci-108', 'position' => 3]);

        return [$ws, [$name, url('/'.$ws->slug), url('/'.$ws->slug).'?owner='.$secret]];
    }

    /** Everything the operator dashboard shows beyond the boards and their files. */
    private function operatorData(array $boards): void
    {
        mt_srand(2026);

        $this->ambassadors();
        $this->boardExtras($boards);
        $this->reports($boards);
        $this->suggestions($boards);
        $this->visits($boards);
    }

    private function ambassadors(): void
    {
        Ambassador::whereIn('slug', array_column(self::AMBASSADORS, 1))->delete();

        foreach (self::AMBASSADORS as [$name, $slug, $campus, $phone, $network, $retired]) {
            $ambassador = Ambassador::create(compact('name', 'slug', 'campus', 'phone', 'network'));
            if ($retired) {
                $ambassador->forceFill(['retired_at' => now()->subDays(26)])->save();
            }
        }
    }

    private function boardExtras(array $boards): void
    {
        $at = fn (?string $ago) => $ago === null ? null : Carbon::parse($ago);

        foreach (self::BOARD_EXTRAS as $name => $extra) {
            $boards[$name]->forceFill([
                'created_at' => $at($extra['created'] ?? null) ?? now(),
                'last_accessed_at' => $at($extra['opened'] ?? null),
                'referrer' => $extra['ref'] ?? null,
                'recovery_email' => $extra['email'] ?? null,
                'operator_note' => $extra['note'] ?? null,
                'operator_note_at' => $at($extra['noteAt'] ?? null),
                'operator_note_seen_at' => $at($extra['noteSeen'] ?? null),
                'archived_at' => $at($extra['archived'] ?? null),
            ])->save();
        }
    }

    private function reports(array $boards): void
    {
        foreach (self::REPORTS as [$board, $code, $filename, $reason, $ago]) {
            $material = Material::query()
                ->whereHas('course', fn ($q) => $q->where('workspace_id', $boards[$board]->id)->where('code', $code))
                ->where('original_filename', $filename)
                ->firstOrFail();

            $material->reports()->create([
                'reason' => $reason,
                'reporter_ip' => '102.176.'.mt_rand(0, 255).'.'.mt_rand(1, 254),
            ])->forceFill(['created_at' => Carbon::parse($ago)])->save();
        }
    }

    private function suggestions(array $boards): void
    {
        // Board pages went with their boards; the site-wide ones are found by text.
        $site = array_filter(self::SUGGESTIONS, fn ($s) => str_starts_with($s[2], '/'));
        Suggestion::whereIn('message', array_column($site, 0))->delete();

        foreach (self::SUGGESTIONS as [$message, $replyTo, $where, $ago]) {
            if (str_starts_with($where, '/')) {
                $page = $where;
            } else {
                [$board, $course] = array_pad(explode('|', $where, 2), 2, null);
                $page = '/'.$boards[$board]->slug.($course ? '/c/'.$course : '');
            }

            $when = Carbon::parse($ago);
            Suggestion::create(['message' => $message, 'reply_to' => $replyTo, 'page' => $page])
                ->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
        }
    }

    /** Thirty days of arrivals, plus the days classmates (not the owner) opened each board. */
    private function visits(array $boards): void
    {
        $tallies = [];
        $visitDays = [];

        foreach (self::AUDIENCES as $name => [$perDay, $countries, $sources]) {
            $id = $boards[$name]->id;
            $rows = $this->tallies($id, $perDay, $countries, $sources);
            array_push($tallies, ...$rows);

            foreach (collect($rows)->groupBy('day') as $day => $dayRows) {
                if ($dayRows->sum('arrivals') >= 2) {
                    $visitDays[] = ['workspace_id' => $id, 'day' => $day];
                }
            }
        }

        foreach (array_chunk($tallies, 200) as $chunk) {
            DB::table('visit_tallies')->insert($chunk);
        }
        foreach (array_chunk($visitDays, 200) as $chunk) {
            DB::table('board_visit_days')->insert($chunk);
        }
    }

    private function tallies(int $workspaceId, int $perDay, array $countries, array $sources): array
    {
        $rows = [];

        foreach (range(0, 29) as $back) {
            $day = now()->subDays($back);
            $arrivals = (int) round($perDay * ($day->isWeekend() ? 0.5 : 1) * mt_rand(55, 145) / 100);

            $counts = [];
            for ($i = 0; $i < $arrivals; $i++) {
                $key = $this->pick($countries).'|'.$this->pick($sources);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }

            foreach ($counts as $key => $count) {
                [$country, $source] = explode('|', $key, 2);
                $rows[] = ['day' => $day->toDateString(), 'workspace_id' => $workspaceId, 'country' => $country, 'source' => $source, 'arrivals' => $count];
            }
        }

        return $rows;
    }

    /** A key from a weight map, e.g. ['GH' => 80, 'NG' => 20]. */
    private function pick(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weights);
    }

    private function deleteExisting(string $name): void
    {
        // By name: board links end in a random code, so the slug isn't known.
        $existing = Workspace::where('name', $name)->first();
        if (! $existing) {
            return;
        }

        Material::whereIn('course_id', $existing->courses()->select('id'))
            ->pluck('stored_path')
            ->each(fn ($path) => Storage::disk('local')->delete($path));

        // Visit counts and suggestions are keyed by id and path, not foreign keys.
        DB::table('visit_tallies')->where('workspace_id', $existing->id)->delete();
        DB::table('board_visit_days')->where('workspace_id', $existing->id)->delete();
        Suggestion::where('page', '/'.$existing->slug)->orWhere('page', 'like', '/'.$existing->slug.'/%')->delete();

        // Courses, materials and their reports go with it (cascadeOnDelete).
        $existing->delete();
    }

    private function addFile(Course $course, string $section, ?string $title, string $filename, ?string $uploader, int $size, string $ago): void
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $label = $title ?? $filename;
        $bytes = match ($ext) {
            'pdf' => $this->pdf($label),
            'jpg', 'jpeg', 'png' => $this->image($label, $ext),
            default => "SlipNote demo placeholder: {$label}\n",
        };

        $path = 'materials/demo-'.Str::random(20).'.'.$ext;
        Storage::disk('local')->put($path, $bytes);

        $when = Carbon::parse($ago);

        $material = $course->materials()->create([
            'section' => $section,
            'title' => $title,
            'original_filename' => $filename,
            'stored_path' => $path,
            'uploader_name' => $uploader,
            'manage_token' => Str::random(40),
            'download_token' => Str::random(40),
            'file_size' => $size,
            'content_hash' => hash('sha256', $bytes),
        ]);

        $material->forceFill(['created_at' => $when, 'updated_at' => $when])->saveQuietly();
    }

    /** A one-page PDF that says what it is, so previews show something. */
    private function pdf(string $text): string
    {
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($text));
        $stream = "BT /F1 18 Tf 72 720 Td ({$safe}) Tj 0 -28 Td /F1 12 Tf (SlipNote demo file) Tj ET";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    /** A labelled image, so the preview shows which file it is. */
    private function image(string $text, string $ext): string
    {
        $img = imagecreatetruecolor(900, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 243, 245, 250));
        $ink = imagecolorallocate($img, 29, 33, 40);
        foreach (str_split(Str::ascii($text), 60) as $i => $line) {
            imagestring($img, 5, 40, 40 + $i * 24, $line, $ink);
        }
        imagestring($img, 3, 40, 560, 'SlipNote demo file', $ink);

        ob_start();
        $ext === 'png' ? imagepng($img) : imagejpeg($img, null, 85);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
