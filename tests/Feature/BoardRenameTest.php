<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

class BoardRenameTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
    }

    private function renameUrl(): string
    {
        return route('courses.rename', $this->wsParams());
    }

    public function test_the_owner_renames_the_board_and_its_link_stays(): void
    {
        $slug = $this->workspace->slug;
        $this->unlockOwnerSession();

        $this->post($this->renameUrl(), ['boardName' => '  English C824 — Term 1  '])
            ->assertRedirect()
            ->assertSessionHas('nameSaved');

        $board = $this->workspace->fresh();
        $this->assertSame('English C824 — Term 1', $board->name);
        $this->assertSame($slug, $board->slug);
        $this->get('/'.$slug)->assertOk();
    }

    public function test_a_classmate_cannot_rename_the_board(): void
    {
        $this->post($this->renameUrl(), ['boardName' => 'Hijacked'])->assertForbidden();

        $this->assertSame('Test Workspace', $this->workspace->fresh()->name);
    }

    public function test_a_name_must_have_two_characters_after_tags_are_stripped(): void
    {
        $this->unlockOwnerSession();

        $this->post($this->renameUrl(), ['boardName' => '<b>x</b>'])->assertSessionHasErrors('boardName');
        $this->post($this->renameUrl(), ['boardName' => str_repeat('a', 61)])->assertSessionHasErrors('boardName');

        $this->assertSame('Test Workspace', $this->workspace->fresh()->name);
    }

    public function test_owning_one_board_does_not_rename_another(): void
    {
        [$other] = Workspace::provision('Other Board');
        $this->unlockOwnerSession();

        $this->post(route('courses.rename', ['workspace' => $other->slug]), ['boardName' => 'Taken over'])
            ->assertForbidden();

        $this->assertSame('Other Board', $other->fresh()->name);
    }
}
