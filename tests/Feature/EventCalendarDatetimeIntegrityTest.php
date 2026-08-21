<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\EventCalendar;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EventCalendarDatetimeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'Event calendar test owner',
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
            'is_owner' => true,
        ]);

        $this->admin = Admin::create([
            'name' => 'Event calendar test owner',
            'username' => 'event-calendar-test-owner',
            'email' => 'event-calendar-test-owner@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => Hash::make('test-password'),
            'must_change_password' => false,
        ]);
    }

    public function test_store_persists_the_complete_required_date_range_in_one_insert(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->from(route('event_calendar.index'))
            ->post(route('event_calendar.store'), [
                'title' => 'Community workshop',
                'description' => 'A practical session for volunteers.',
                'start_date' => '2026-09-15T09:30',
                'end_date' => '2026-09-15T11:00',
            ])
            ->assertRedirect(route('event_calendar.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_calendars', [
            'title' => 'Community workshop',
            'start_date' => '2026-09-15 09:30:00',
            'end_date' => '2026-09-15 11:00:00',
            'language' => 'en',
            'status' => 0,
        ]);
    }

    public function test_store_rejects_missing_invalid_or_reversed_date_ranges(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->from(route('event_calendar.index'))
            ->post(route('event_calendar.store'), [
                'title' => 'Missing end',
                'start_date' => '2026-09-15T09:30',
            ])
            ->assertRedirect(route('event_calendar.index'))
            ->assertSessionHasErrors('end_date');

        $this->actingAs($this->admin, 'admin')
            ->from(route('event_calendar.index'))
            ->post(route('event_calendar.store'), [
                'title' => 'Backwards event',
                'start_date' => '2026-09-15T11:00',
                'end_date' => '2026-09-15T09:30',
            ])
            ->assertRedirect(route('event_calendar.index'))
            ->assertSessionHasErrors('end_date');

        $this->actingAs($this->admin, 'admin')
            ->from(route('event_calendar.index'))
            ->post(route('event_calendar.store'), [
                'title' => 'Ambiguous date',
                'start_date' => 'tomorrow morning',
                'end_date' => '2026-09-15T11:00',
            ])
            ->assertRedirect(route('event_calendar.index'))
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('event_calendars', 0);
    }

    public function test_edit_returns_values_compatible_with_datetime_local_inputs(): void
    {
        $event = $this->event();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('event_calendar.edit', $event->id))
            ->assertOk()
            ->assertJsonPath('data.start_date', '2026-09-15T09:30')
            ->assertJsonPath('data.end_date', '2026-09-15T11:00');
    }

    public function test_update_replaces_both_dates_and_rejects_an_empty_end_instead_of_keeping_it_silently(): void
    {
        $event = $this->event();

        $this->actingAs($this->admin, 'admin')
            ->from(route('event_calendar.index'))
            ->put(route('event_calendar.update'), [
                'id' => $event->id,
                'title' => 'Updated community workshop',
                'start_date' => '2026-09-16T13:15',
                'end_date' => '2026-09-16T15:45',
            ])
            ->assertRedirect(route('event_calendar.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_calendars', [
            'id' => $event->id,
            'title' => 'Updated community workshop',
            'start_date' => '2026-09-16 13:15:00',
            'end_date' => '2026-09-16 15:45:00',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->from(route('event_calendar.index'))
            ->put(route('event_calendar.update'), [
                'id' => $event->id,
                'title' => 'Invalid partial update',
                'start_date' => '2026-09-17T08:00',
                'end_date' => '',
            ])
            ->assertRedirect(route('event_calendar.index'))
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseHas('event_calendars', [
            'id' => $event->id,
            'title' => 'Updated community workshop',
            'start_date' => '2026-09-16 13:15:00',
            'end_date' => '2026-09-16 15:45:00',
        ]);
    }

    private function event(): EventCalendar
    {
        return EventCalendar::create([
            'title' => 'Community workshop',
            'description' => 'A practical session for volunteers.',
            'start_date' => '2026-09-15 09:30:00',
            'end_date' => '2026-09-15 11:00:00',
            'language' => 'en',
            'status' => 0,
        ]);
    }
}
