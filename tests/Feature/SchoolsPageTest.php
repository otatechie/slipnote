<?php

namespace Tests\Feature;

use App\Models\Suggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchoolsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    /** A form opened $secondsAgo, as a person would send it. */
    private function form(array $fields = [], int $secondsAgo = 30): array
    {
        return $fields + [
            'started' => Crypt::encryptString((string) (time() - $secondsAgo)),
            'school' => 'St. Mary’s Basic School',
            'contact' => '+233 20 000 0000',
            'name' => 'Mrs Asante, Head of English',
            'classes' => 12,
            'message' => 'We have three Grade 5 classes on boards already.',
        ];
    }

    public function test_the_page_explains_schools_and_carries_a_fresh_form(): void
    {
        $this->get('/schools')->assertOk()
            ->assertSee('For schools')
            ->assertSee('Become a pilot school')
            // The offer is a public promise; changing it is a business decision, not a copy edit.
            ->assertSee('Pilot schools get a lower price for life.')
            ->assertSee('Replying doesn’t commit you to anything')
            ->assertSee('Each teacher manages their own class’s board')
            ->assertSee('The school stays in control when staff leave or lose a link')
            ->assertSee('name="started"', false)
            ->assertSee('name="website"', false);
    }

    public function test_under_13s_are_allowed_only_through_a_locked_teacher_board(): void
    {
        $this->get('/schools')->assertOk()->assertSee('Teaching children under 13?');
        $this->get('/terms')->assertOk()->assertSee('A board used by children under 13 must be run by a teacher, with uploads locked');
        $this->get('/privacy')->assertOk()
            ->assertSee('Children under 13 may use it only through a board a teacher runs with uploads locked')
            ->assertDontSee('higher or further education');
    }

    public function test_a_school_s_request_lands_with_the_suggestions(): void
    {
        $this->post('/schools', $this->form())
            ->assertRedirect(route('schools').'#ask')
            ->assertSessionHas('sent', true);

        $s = Suggestion::sole();
        $this->assertSame('/schools', $s->page);
        $this->assertSame('+233 20 000 0000', $s->reply_to);
        $this->assertStringContainsString('School: St. Mary’s Basic School', $s->message);
        $this->assertStringContainsString('From: Mrs Asante, Head of English', $s->message);
        $this->assertStringContainsString('Classes: 12', $s->message);

        $this->get('/schools')->assertSee('Thanks. We’ll be in touch soon.');
    }

    public function test_bots_are_told_it_worked_and_nothing_is_kept(): void
    {
        // The hidden field, a form sent faster than anyone types, and a forged or missing timestamp.
        foreach ([$this->form(['website' => 'http://spam.example']), $this->form([], 1),
            $this->form(['started' => 'not-a-token']), array_diff_key($this->form(), ['started' => 1])] as $input) {
            $this->post('/schools', $input)->assertRedirect(route('schools').'#ask')->assertSessionHas('sent', true);
        }

        $this->assertSame(0, Suggestion::count());
    }

    public function test_a_school_and_a_way_to_reply_are_needed(): void
    {
        $this->post('/schools', $this->form(['school' => '', 'contact' => '']))
            ->assertSessionHasErrors(['school', 'contact']);
        $this->assertSame(0, Suggestion::count());
    }

    public function test_link_stuffed_messages_are_refused(): void
    {
        $this->post('/schools', $this->form(['message' => 'http://a.example http://b.example http://c.example']))
            ->assertSessionHasErrors('message');
        $this->assertSame(0, Suggestion::count());
    }

    public function test_one_sender_gets_three_an_hour(): void
    {
        foreach (range(1, 3) as $i) {
            $this->post('/schools', $this->form(['school' => "School {$i}"]))->assertSessionHasNoErrors();
        }
        $this->post('/schools', $this->form(['school' => 'School 4']))->assertSessionHasErrors('school');

        $this->assertSame(3, Suggestion::count());
    }

    public function test_html_is_stripped_before_it_is_kept(): void
    {
        $this->post('/schools', $this->form(['school' => '<b>Bold</b> Academy', 'message' => '<script>x</script>Hi']));

        $this->assertStringNotContainsString('<', Suggestion::sole()->message);
    }
}
