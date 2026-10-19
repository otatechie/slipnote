<?php

namespace Tests\Feature;

use App\Models\Suggestion;
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
        [$ws] = \App\Models\Workspace::provision('Heavy Users');

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->post(route('operator.boards.note', $ws->id), ['note' => 'Could we ask you two questions?'])
            ->assertRedirect(route('operator.dashboard', ['tab' => 'boards']));

        // The owner sees it ...
        $this->withSession([$ws->ownerSessionKey() => true])
            ->get('/heavy-users')
            ->assertInertia(fn ($page) => $page->where('operatorNote.text', 'Could we ask you two questions?'));

        // ... a classmate does not.
        $this->flushSession();
        $this->get('/heavy-users')->assertInertia(fn ($page) => $page->where('operatorNote', null));
    }

    public function test_an_empty_note_removes_it_and_only_the_operator_can_set_one(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        [$ws] = \App\Models\Workspace::provision('Heavy Users');
        $ws->forceFill(['operator_note' => 'Hi', 'operator_note_at' => now()])->save();

        $this->post(route('operator.boards.note', $ws->id), ['note' => 'Hijacked'])->assertForbidden();

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->post(route('operator.boards.note', $ws->id), ['note' => '']);

        $this->assertNull($ws->fresh()->operator_note);
    }

    public function test_the_boards_tab_finds_a_board_by_name(): void
    {
        config(['noteshare.operator_secret' => 'op-secret']);
        \App\Models\Workspace::provision('Heavy Users');
        \App\Models\Workspace::provision('Somebody Else');

        $this->withSession(['operator_fp' => hash('sha256', 'op-secret')])
            ->get(route('operator.dashboard', ['tab' => 'boards', 'q' => 'heavy']))
            ->assertOk()
            ->assertSee('Heavy Users')
            ->assertDontSee('Somebody Else');
    }
}
