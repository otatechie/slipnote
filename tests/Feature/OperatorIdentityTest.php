<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Who runs the site and how soon takedowns are reviewed: both come from
 * config, since SlipNote is self-hostable and no deployment should inherit
 * another's name or promise.
 */
class OperatorIdentityTest extends TestCase
{
    public function test_lecturers_and_terms_state_the_takedown_turnaround(): void
    {
        config(['noteshare.takedown_turnaround' => '3 working days']);

        $this->get('/lecturers')->assertOk()->assertSee('We review it within 3 working days.', false);
        $this->get('/terms')->assertOk()->assertSee('reviews reports within 3 working days', false);
    }

    public function test_the_operator_is_named_when_set(): void
    {
        config(['noteshare.operator_name' => 'Kinetics Worth', 'noteshare.contact_email' => 'hello@example.com']);

        $this->get('/lecturers')->assertOk()
            ->assertSee('Who runs SlipNote')
            ->assertSee('Kinetics Worth, independently')
            ->assertSee('hello@example.com')
            ->assertSee('for classes by Kinetics Worth', false);
    }

    public function test_no_name_is_shown_when_none_is_set(): void
    {
        config(['noteshare.operator_name' => null]);

        $this->get('/lecturers')->assertOk()
            ->assertDontSee('Who runs SlipNote')
            ->assertDontSee('for classes by', false);
    }
}
