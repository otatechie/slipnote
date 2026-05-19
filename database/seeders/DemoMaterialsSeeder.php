<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fills the demo course with a realistic semester's worth of materials so
 * the UI/UX can be tested at expected volume. Idempotent: wipes and
 * regenerates the demo course's materials each run.
 *
 *   php artisan db:seed --class=DemoMaterialsSeeder
 */
class DemoMaterialsSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::firstOrCreate(
            ['slug' => 'math-251'],
            ['code' => 'MATH 251', 'title' => 'Calculus II'],
        );

        // Clean slate for the demo course (files + rows).
        foreach ($course->materials as $old) {
            Storage::disk('public')->delete($old->stored_path);
        }
        $course->materials()->delete();

        $uploaders = ['Alex', 'Sam', 'Priya', 'Jordan', 'Mei', 'Diego', 'Hana', null, null];

        // [section, count, title builder, extension pool]
        $plan = [
            ['slides', 20, fn ($i) => 'Lecture '.$i.' — '.$this->topic($i), ['pdf', 'pptx', 'pptx']],
            ['notes', 30, fn ($i) => $this->topic($i).' notes', ['pdf', 'docx', 'pdf', 'jpg']],
            ['past_papers', 8, fn ($i) => 'Past paper '.(2018 + $i % 7).($i % 2 ? ' — solutions' : ''), ['pdf']],
            ['announcements', 3, fn ($i) => 'Announcement: '.['Midterm date set', 'Office hours moved', 'Assignment 3 extended'][$i - 1], ['pdf']],
        ];

        $now = now();

        foreach ($plan as [$section, $count, $titleFor, $exts]) {
            for ($i = 1; $i <= $count; $i++) {
                $ext = $exts[($i - 1) % count($exts)];
                $title = $titleFor($i);

                $filename = Str::slug($title).'.'.$ext;
                $storedPath = 'materials/'.Str::random(40).'.'.$ext;

                Storage::disk('public')->put($storedPath, "demo file: {$title}");

                $material = $course->materials()->create([
                    'section' => $section,
                    'title' => $i % 4 === 0 ? null : $title, // some have no title (filename-only)
                    'original_filename' => $filename,
                    'stored_path' => $storedPath,
                    'uploader_name' => $uploaders[array_rand($uploaders)],
                ]);

                // Spread created_at across a ~15-week semester, newest first.
                $material->forceFill([
                    'created_at' => $now->copy()->subDays(rand(0, 105))->subHours(rand(0, 23)),
                ])->save();
            }
        }

        $this->command->info("Seeded {$course->materials()->count()} demo materials for {$course->code}.");
    }

    private function topic(int $i): string
    {
        $topics = [
            'Limits', 'Continuity', 'Derivatives', 'Chain Rule', 'Implicit Differentiation',
            'Related Rates', 'Optimization', 'Mean Value Theorem', 'L\'Hôpital', 'Antiderivatives',
            'Riemann Sums', 'Definite Integrals', 'FTC', 'u-Substitution', 'Integration by Parts',
            'Trig Integrals', 'Partial Fractions', 'Improper Integrals', 'Sequences', 'Series',
        ];

        return $topics[($i - 1) % count($topics)];
    }
}
