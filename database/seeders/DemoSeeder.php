<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Material;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Local demo boards for looking at the UI in every state that matters: a busy
 * board (search, sort, many files, every section), a small one with an empty
 * course, a board with no courses, a long board name, and a board near its
 * storage cap. Re-runnable: it deletes its own boards (and their files) first.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * PDFs and images are real files, so preview and download work. Office files
 * are placeholder bytes, so they download but won't open. file_size is a
 * realistic display size, not the placeholder's size on disk.
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
    ];

    public function run(): void
    {
        $links = [];

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

            $links[] = [$name, url('/'.$ws->slug), url('/'.$ws->slug).'?owner='.$secret];
        }

        $this->command?->newLine();
        foreach ($links as [$name, $share, $owner]) {
            $this->command?->line("<info>{$name}</info>");
            $this->command?->line("  share: {$share}");
            $this->command?->line("  owner: {$owner}");
        }
    }

    private function deleteExisting(string $name): void
    {
        $existing = Workspace::where('slug', Str::slug($name))->first();
        if (! $existing) {
            return;
        }

        Material::whereIn('course_id', $existing->courses()->select('id'))
            ->pluck('stored_path')
            ->each(fn ($path) => Storage::disk('local')->delete($path));

        // Courses and materials go with it (cascadeOnDelete).
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
