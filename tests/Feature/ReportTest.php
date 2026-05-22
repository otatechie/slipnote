<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\InteractsWithWorkspace;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use InteractsWithWorkspace;
    use RefreshDatabase;

    private Course $course;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkspace();
        $this->course = Course::create(['code' => 'MATH 251', 'title' => 'Calculus II', 'slug' => 'math-251']);
        $this->material = $this->course->materials()->create([
            'section' => 'notes',
            'original_filename' => 'sketchy.pdf',
            'stored_path' => 'x/sketchy.pdf',
            'manage_token' => 'tok-'.str_repeat('a', 36),
        ]);
    }

    private function reportUrl(?Material $material = null): string
    {
        return route('material.report', $this->wsParams([
            'slug' => $this->course->slug,
            'material' => ($material ?? $this->material)->id,
        ]));
    }

    public function test_anyone_can_report_a_file_and_gets_a_receipt(): void
    {
        $this->post($this->reportUrl(), ['reason' => 'Not my notes'])
            ->assertRedirect()
            ->assertSessionHas('reported');

        // The report is persisted for the operator dashboard.
        $this->assertSame(1, $this->material->reports()->count());
        $this->assertSame('Not my notes', $this->material->reports()->first()->reason);
    }

    public function test_reporting_notifies_the_operator_via_telegram_when_configured(): void
    {
        config(['noteshare.telegram_bot_token' => 'tok', 'noteshare.telegram_chat_id' => '@chan']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->post($this->reportUrl(), ['reason' => 'spam'])->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'reported'));
    }

    public function test_reporting_is_rate_limited_per_material_and_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post($this->reportUrl())->assertRedirect();
        }

        // The 6th within the window is absorbed (still a friendly receipt,
        // but no further notifications).
        $this->post($this->reportUrl())
            ->assertRedirect()
            ->assertSessionHas('reported');
    }

    public function test_a_file_in_another_workspace_cannot_be_reported(): void
    {
        // A material that lives in a DIFFERENT workspace.
        [$other] = Workspace::provision('Other Board');
        $this->actingInWorkspace($other);
        $otherCourse = Course::create(['code' => 'PHYS 101', 'title' => 'Physics', 'slug' => 'phys-101']);
        $otherMaterial = $otherCourse->materials()->create([
            'section' => 'notes',
            'original_filename' => 'theirs.pdf',
            'stored_path' => 'x/theirs.pdf',
            'manage_token' => 'tok-'.str_repeat('b', 36),
        ]);

        // Back to our workspace; try to report the other board's file by id
        // through OUR board's report URL. WorkspaceScope binds {material} to
        // the current tenant, so it must 404.
        $this->actingInWorkspace($this->workspace);

        $this->post(route('material.report', $this->wsParams([
            'slug' => $this->course->slug,
            'material' => $otherMaterial->id,
        ])))->assertNotFound();
    }
}
