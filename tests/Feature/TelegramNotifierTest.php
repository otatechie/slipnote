<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

class TelegramNotifierTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        Storage::fake('public');
        $this->course = Course::create([
            'code' => 'PHYS 101',
            'title' => 'Intro Physics',
            'slug' => 'phys-101',
        ]);
    }

    public function test_it_does_nothing_when_not_configured(): void
    {
        config(['noteshare.telegram_bot_token' => null, 'noteshare.telegram_chat_id' => null]);
        Http::fake();

        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'a.pdf',
            'stored_path' => 'x/a.pdf',
        ]);

        app(TelegramNotifier::class)->notifyUpload($material);

        Http::assertNothingSent();
    }

    public function test_it_does_nothing_when_only_one_value_is_set(): void
    {
        config(['noteshare.telegram_bot_token' => 'tok', 'noteshare.telegram_chat_id' => '']);
        Http::fake();

        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'a.pdf',
            'stored_path' => 'x/a.pdf',
        ]);

        app(TelegramNotifier::class)->notifyUpload($material);

        Http::assertNothingSent();
    }

    public function test_message_headlines_the_title_when_one_is_given(): void
    {
        config(['noteshare.telegram_bot_token' => 'tok', 'noteshare.telegram_chat_id' => '@chan']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $material = $this->course->materials()->create([
            'section' => 'slides',
            'title' => 'Week 7 — integration by parts',
            'original_filename' => 'week3.pptx',
            'uploader_name' => 'Sam',
            'stored_path' => 'x/week3.pptx',
        ]);

        app(TelegramNotifier::class)->notifyUpload($material);

        Http::assertSent(function ($request) {
            $text = $request['text'];

            return str_contains($request->url(), '/bottok/sendMessage')
                && $request['chat_id'] === '@chan'
                // Title is the bolded headline (highest scent).
                && str_contains($text, '<b>Week 7 — integration by parts</b>')
                // Course + section on the metadata line (no timestamp —
                // Telegram stamps the message itself).
                && str_contains($text, 'New in <b>PHYS 101</b> · Slides')
                // Filename + uploader demoted to the detail line.
                && str_contains($text, 'week3.pptx')
                && str_contains($text, 'Sam')
                // Link label is honest about where it goes.
                && str_contains($text, 'Browse PHYS 101 →');
        });
    }

    public function test_message_headlines_the_filename_when_there_is_no_title(): void
    {
        config(['noteshare.telegram_bot_token' => 'tok', 'noteshare.telegram_chat_id' => '@chan']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'lecture8.pdf',
            'stored_path' => 'x/lecture8.pdf',
        ]);

        app(TelegramNotifier::class)->notifyUpload($material);

        Http::assertSent(function ($request) {
            $text = $request['text'];

            // No title → filename is the headline; no detail line at all.
            return str_contains($text, '<b>lecture8.pdf</b>')
                && str_contains($text, 'New in <b>PHYS 101</b> · Notes')
                && ! str_contains($text, 'uploaded by')
                && str_contains($text, 'Browse PHYS 101 →');
        });
    }

    public function test_it_escapes_hostile_uploader_controlled_text_for_telegram(): void
    {
        config(['noteshare.telegram_bot_token' => 'tok', 'noteshare.telegram_chat_id' => '@chan']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        // original_filename is attacker-chosen (anonymous upload, no accounts).
        // A raw <b>/<a> here would either malform Telegram's HTML and make it
        // reject the call, or inject formatting. It must come out inert.
        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => '<a href="evil">pwn</a> & <b>x</b>.pdf',
            'uploader_name' => 'Mallory <script>',
            'stored_path' => 'x/p.pdf',
        ]);

        app(TelegramNotifier::class)->notifyUpload($material);

        Http::assertSent(function ($request) {
            $text = $request['text'];

            // The only tags present must be ours; the hostile ones are escaped.
            return str_contains($text, '&lt;a href=&quot;evil&quot;&gt;pwn&lt;/a&gt; &amp; &lt;b&gt;x&lt;/b&gt;.pdf') === false
                // Telegram doesn't decode &quot;, so attribute quotes stay literal.
                && str_contains($text, '&lt;a href="evil"&gt;pwn&lt;/a&gt; &amp; &lt;b&gt;x&lt;/b&gt;.pdf')
                && str_contains($text, 'Mallory &lt;script&gt;')
                // Our own intended markup is still intact.
                && str_contains($text, '<b>')
                && str_contains($text, '<a href="http');
        });
    }

    public function test_a_telegram_failure_never_breaks_the_notifier(): void
    {
        config(['noteshare.telegram_bot_token' => 'tok', 'noteshare.telegram_chat_id' => '@chan']);
        Http::fake(['api.telegram.org/*' => Http::response('nope', 500)]);

        $material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'a.pdf',
            'stored_path' => 'x/a.pdf',
        ]);

        // Must not throw despite the 500.
        app(TelegramNotifier::class)->notifyUpload($material);

        $this->assertTrue(true);
    }

    public function test_uploading_still_succeeds_with_telegram_configured(): void
    {
        config(['noteshare.telegram_bot_token' => 'tok', 'noteshare.telegram_chat_id' => '@chan']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $file = UploadedFile::fake()->create('lecture.pdf', 100, 'application/pdf');

        Livewire::test('course-page', ['slug' => $this->course->slug])
            ->set('section', 'notes')
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $this->course->materials()->count());
    }
}
