<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

/**
 * Page data goes into a <script> block. "<!--<script>" in a filename or a
 * search would end that block early and blank the page for every visitor.
 */
class PageDataEscapingTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Storage::fake('local');
        Course::create(['code' => 'CS 101', 'title' => 'Intro', 'slug' => 'cs-101']);
    }

    public function test_a_search_with_html_in_it_is_escaped_in_the_page_data(): void
    {
        $html = $this->get(route('course.show', $this->wsParams(['slug' => 'cs-101', 'search' => '<!--<script>'])))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('<!--<script>', $html);
        $this->assertStringContainsString('\\u003C!--\\u003Cscript\\u003E', $html);
    }

    public function test_angle_brackets_are_dropped_from_filenames(): void
    {
        $this->post(route('course.upload', $this->wsParams(['slug' => 'cs-101'])), [
            'section' => 'notes',
            'files' => [UploadedFile::fake()->create('<!--<script>notes.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $this->assertSame('!--scriptnotes.pdf', Material::first()->original_filename);
    }
}
