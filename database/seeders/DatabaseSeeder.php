<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * v1 has exactly one course. Change these three values to your
     * real course when you know it, then re-run: php artisan db:seed
     */
    public function run(): void
    {
        Course::firstOrCreate(
            ['slug' => 'math-251'],
            ['code' => 'MATH 251', 'title' => 'Calculus II'],
        );
    }
}
