<?php

namespace Tests\Feature;

use App\Models\Suggestion;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_suggestion_is_stored_with_the_page_path_only(): void
    {
        Http::fake();

        $this->from('/start')
            ->post('/suggestions', [
                'message' => 'Let us pin a file in each course',
                'reply_to' => 'ama@example.com',
                'page' => '/nursing-level-200?owner=secret-value',
            ])
            ->assertRedirect('/start')
            ->assertSessionHasNoErrors();

        $s = Suggestion::sole();
        $this->assertSame('Let us pin a file in each course', $s->message);
        $this->assertSame('ama@example.com', $s->reply_to);
        // The owner secret in the query string must never be kept.
        $this->assertSame('/nursing-level-200', $s->page);
    }

    public function test_an_empty_message_is_rejected(): void
    {
        $this->post('/suggestions', ['message' => ''])->assertSessionHasErrors('message');
        $this->assertSame(0, Suggestion::count());
    }

    public function test_the_spam_trap_drops_the_message_silently(): void
    {
        $this->post('/suggestions', ['message' => 'Buy cheap things now', 'website' => 'http://spam.example'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Suggestion::count());
    }

    public function test_one_source_is_limited_to_five_an_hour(): void
    {
        Http::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/suggestions', ['message' => "Idea number {$i}"])->assertSessionHasNoErrors();
        }

        $this->post('/suggestions', ['message' => 'One more idea'])->assertSessionHasErrors('message');
        $this->assertSame(5, Suggestion::count());
    }

    public function test_telegram_is_pinged_when_configured(): void
    {
        config(['noteshare.telegram_bot_token' => 'tok', 'noteshare.telegram_chat_id' => '42']);
        Http::fake();

        $this->post('/suggestions', ['message' => 'Dark mode for the QR code']);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.telegram.org/bottok/sendMessage')
            && str_contains($req['text'], 'Dark mode for the QR code'));
    }

    public function test_the_operator_sees_and_deletes_suggestions(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        $s = Suggestion::create(['message' => 'Offline mode please', 'reply_to' => '0241234567', 'page' => '/start']);
        $session = ['operator_fp' => hash('sha256', 'op-secret')];

        $this->withSession($session)
            ->get(route('operator.dashboard', ['tab' => 'suggestions']))
            ->assertOk()
            ->assertSee('Offline mode please')
            ->assertSee('0241234567');

        $this->withSession($session)
            ->post(route('operator.suggestions.delete', $s->id))
            ->assertRedirect(route('operator.dashboard', ['tab' => 'suggestions']));

        $this->assertSame(0, Suggestion::count());
    }

    public function test_the_operator_sees_which_board_a_suggestion_came_from_and_can_reply(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        [$a] = Workspace::provision('Nursing Level 200');
        [$b] = Workspace::provision('Other Board');
        $a->courses()->create(['code' => 'NUR 201', 'title' => 'Adult health', 'slug' => 'nur-201']);
        $b->courses()->create(['code' => 'BIO 210', 'title' => 'Microbiology', 'slug' => 'bio-210']);

        Suggestion::create(['message' => 'From a course page', 'reply_to' => 'ama@example.com', 'page' => '/'.$a->slug.'/c/nur-201']);
        // The course slug belongs to the other board: the board is named, never the other board's course.
        Suggestion::create(['message' => 'Course from elsewhere', 'page' => '/'.$a->slug.'/c/bio-210']);
        Suggestion::create(['message' => 'From the start page', 'reply_to' => '0241 234 567', 'page' => '/start']);
        Suggestion::create(['message' => 'From a deleted board', 'reply_to' => 'my insta @kofi', 'page' => '/gone-board-abc123']);

        $html = $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard', ['tab' => 'suggestions']))
            ->assertOk()
            ->assertSee('Nursing Level 200, NUR 201')
            ->assertSee('href="'.route('course.show', ['workspace' => $a->slug, 'slug' => 'nur-201']).'"', false)
            ->assertSee('href="mailto:ama@example.com"', false)
            ->assertSee('from the Start page')
            ->assertSee('href="tel:0241234567"', false)
            ->assertSee('from /gone-board-abc123')
            ->assertSee('my insta @kofi')
            ->assertDontSee('BIO 210')
            ->getContent();

        // The board's own page link, for the suggestion whose course didn't match.
        $this->assertStringContainsString('href="'.route('courses.index', ['workspace' => $a->slug]).'"', $html);
    }

    public function test_a_deleted_suggestion_can_be_undone(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        $s = Suggestion::create(['message' => 'Dark mode please', 'reply_to' => '0241234567', 'page' => '/start']);
        $session = ['operator_fp' => hash('sha256', 'op-secret')];

        $this->withSession($session)->post(route('operator.suggestions.delete', $s->id));
        $this->get(route('operator.dashboard', ['tab' => 'suggestions']))->assertSee('Undo');

        $this->post(route('operator.undo'))
            ->assertRedirect(route('operator.dashboard', ['tab' => 'suggestions']));

        $back = Suggestion::sole();
        $this->assertSame([$s->id, 'Dark mode please', '0241234567', '/start'], [$back->id, $back->message, $back->reply_to, $back->page]);
        $this->assertEquals($s->created_at, $back->created_at);
    }

    public function test_only_the_operator_can_delete_a_suggestion(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        $s = Suggestion::create(['message' => 'Keep me']);

        $this->post(route('operator.suggestions.delete', $s->id))->assertForbidden();
        $this->assertSame(1, Suggestion::count());
    }

    public function test_the_operator_leaves_a_note_only_the_owner_sees(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        [$ws] = Workspace::provision('Heavy Users');

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->post(route('operator.boards.note', $ws->id), ['note' => 'Could we ask you two questions?'])
            ->assertRedirect(route('operator.dashboard', ['tab' => 'boards']));

        // A classmate opening the board doesn't count as the owner seeing it.
        $this->get('/'.$ws->slug)->assertInertia(fn ($page) => $page->where('operatorNote', null));
        $this->assertNull($ws->fresh()->operator_note_seen_at);
        $operator = fn () => $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard', ['tab' => 'boards', 'empty' => 1]));
        $operator()->assertSee('note left')->assertDontSee('note seen');

        // The owner sees it, and the operator sees that they did.
        $this->withSession([$ws->ownerSessionKey() => $ws->ownerSessionValue()])
            ->get('/'.$ws->slug)
            ->assertInertia(fn ($page) => $page->where('operatorNote.text', 'Could we ask you two questions?'));
        $this->assertNotNull($ws->fresh()->operator_note_seen_at);
        $operator()->assertSee('note seen')->assertDontSee('note left');

        // A new note starts over.
        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->post(route('operator.boards.note', $ws->id), ['note' => 'One more thing.']);
        $this->assertNull($ws->fresh()->operator_note_seen_at);
        $operator()->assertSee('note left');
    }

    public function test_an_empty_note_removes_it_and_only_the_operator_can_set_one(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        [$ws] = Workspace::provision('Heavy Users');
        $ws->forceFill(['operator_note' => 'Hi', 'operator_note_at' => now()])->save();

        $this->post(route('operator.boards.note', $ws->id), ['note' => 'Hijacked'])->assertForbidden();

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->post(route('operator.boards.note', $ws->id), ['note' => '']);

        $this->assertNull($ws->fresh()->operator_note);
    }

    public function test_the_boards_tab_finds_a_board_by_name(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        Workspace::provision('Heavy Users');
        Workspace::provision('Somebody Else');

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard', ['tab' => 'boards', 'q' => 'heavy']))
            ->assertOk()
            ->assertSee('Heavy Users')
            ->assertDontSee('Somebody Else');
    }
}
