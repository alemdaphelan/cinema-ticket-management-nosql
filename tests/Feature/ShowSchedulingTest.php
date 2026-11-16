<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Room;
use App\Models\Show;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ShowSchedulingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_store_rejects_duplicate_time_and_cleanup_overlap_but_allows_boundary_and_other_rooms(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $movie = Movie::factory()->create(['duration_minutes' => 120]);
        $room = Room::factory()->create();
        $other = Room::factory()->create();
        $show = ['movie_id' => $movie->id, 'room_id' => $room->id, 'price' => 85000];

        $this->postJson('/api/admin/shows', $show + ['start_time' => '2026-11-20T18:00:00+07:00'])
            ->assertCreated()
            ->assertJsonPath('data.start_time', '2026-11-20T11:00:00.000000Z')
            ->assertJsonPath('data.end_time', '2026-11-20T13:00:00.000000Z');
        foreach (['2026-11-20T11:00:00Z', '2026-11-20T20:29:00+07:00', '2026-11-20T15:31:00+07:00'] as $conflict) {
            $this->postJson('/api/admin/shows', $show + ['start_time' => $conflict])
                ->assertUnprocessable()->assertJsonValidationErrors('start_time');
        }

        $this->postJson('/api/admin/shows', $show + ['start_time' => '2026-11-20T20:30:00+07:00'])->assertCreated();
        $this->postJson('/api/admin/shows', [...$show, 'room_id' => $other->id, 'start_time' => '2026-11-20T18:00:00+07:00'])
            ->assertCreated();
        $this->assertDatabaseCount('shows', 3);
    }

    public function test_room_preview_and_store_use_saved_end_times_across_midnight(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $movie = Movie::factory()->create(['duration_minutes' => 60]);
        $room = Room::factory()->create();
        $existing = Show::factory()->for($movie)->for($room)->create([
            'start_time' => '2026-11-20 16:00:00',
            'end_time' => '2026-11-20 19:00:00',
        ]);
        $nextDay = Show::factory()->for($movie)->for($room)->create([
            'start_time' => '2026-11-21 17:30:00',
            'end_time' => '2026-11-21 18:30:00',
        ]);

        $this->getJson("/api/admin/shows/room-schedule?room_id={$room->id}&date=2026-11-21&movie_id={$movie->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $existing->id)
            ->assertJsonPath('data.0.raw_clean_end', '2026-11-20T19:30:00.000000Z')
            ->assertJsonPath('data.0.in_day', true)
            ->assertJsonPath('data.1.id', $nextDay->id)
            ->assertJsonPath('data.1.in_day', false);
        $payload = ['movie_id' => $movie->id, 'room_id' => $room->id, 'price' => 85000];
        $this->postJson('/api/admin/shows', $payload + ['start_time' => '2026-11-21T02:29:00+07:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->postJson('/api/admin/shows', $payload + ['start_time' => '2026-11-21T02:30:00+07:00'])
            ->assertCreated();
    }

    public function test_update_checks_the_destination_room_and_preserves_stored_end_on_price_edit(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $movie = Movie::factory()->create(['duration_minutes' => 60]);
        $slot = ['start_time' => '2026-11-20 11:00:00', 'end_time' => '2026-11-20 13:00:00'];
        $occupied = Show::factory()->for($movie)->create($slot);
        $other = Show::factory()->for($movie)->create($slot);

        $this->putJson("/api/admin/shows/{$other->id}", ['room_id' => $occupied->room_id])
            ->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->putJson("/api/admin/shows/{$other->id}", ['price' => 95000])
            ->assertOk()->assertJsonPath('data.end_time', '2026-11-20T13:00:00.000000Z');
        $this->assertDatabaseHas('shows', ['id' => $other->id, 'room_id' => $other->room_id, 'price' => 95000]);
    }

    public function test_demo_scheduler_does_not_generate_overlapping_shows(): void
    {
        $this->travelTo(now()->startOfSecond());
        $room = Room::factory()->create();
        Movie::factory()->count(5)->create(['duration_minutes' => 140, 'status' => 'showing']);

        $this->artisan('app:update-demo-shows')->assertSuccessful();
        $shows = $room->shows()->orderBy('start_time')->get();

        $this->assertGreaterThan(7, $shows->count());
        foreach ($shows->sliding(2) as $pair) {
            [$previous, $next] = $pair->values()->all();
            $this->assertTrue($previous->end_time->copy()->addMinutes(30)->lte($next->start_time));
        }
    }

    public function test_admin_can_combine_time_room_and_movie_filters_in_local_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21T00:15:00+07:00'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $movie = Movie::factory()->create();
        $room = Room::factory()->create();
        $utcStart = ['start_time' => '2026-11-20 17:00:30', 'end_time' => '2026-11-20 19:00:00'];

        $match = Show::factory()->for($movie)->for($room)->create($utcStart);
        $otherRoom = Show::factory()->for($movie)->create($utcStart);
        $otherMovie = Show::factory()->for($room)->create($utcStart);
        $yesterday = Show::factory()->for($movie)->for($room)->create([
            'start_time' => '2026-11-20 16:59:59', 'end_time' => '2026-11-20 18:59:59',
        ]);
        $later = Show::factory()->for($movie)->for($room)->create([
            'start_time' => '2026-11-21 01:00:00', 'end_time' => '2026-11-21 03:00:00',
        ]);
        $tomorrow = Show::factory()->for($movie)->for($room)->create([
            'start_time' => '2026-11-21 17:00:00', 'end_time' => '2026-11-21 19:00:00',
        ]);

        $this->get(route('admin.shows'))->assertOk()
            ->assertSee('data-show-id="'.$match->id.'"', false)
            ->assertDontSee('data-show-id="'.$yesterday->id.'"', false)
            ->assertDontSee('data-show-id="'.$tomorrow->id.'"', false);

        $params = ['room_id' => $room->id, 'movie_id' => $movie->id];
        $filtered = $this->get(route('admin.shows', $params + [
            'period' => 'custom', 'from' => '2026-11-21T00:00', 'to' => '2026-11-21T00:00',
        ]))->assertOk()->assertSee('21/11/2026');
        $this->assertSame([$match->id], $filtered->viewData('shows')->pluck('id')->all());
        $filtered->assertDontSee('data-show-id="'.$otherRoom->id.'"', false)
            ->assertDontSee('data-show-id="'.$otherMovie->id.'"', false);

        $upcoming = $this->get(route('admin.shows', $params + ['period' => 'upcoming']))->assertOk();
        $this->assertSame([$later->id, $tomorrow->id], $upcoming->viewData('shows')->pluck('id')->all());
        $this->get(route('admin.shows', $params + [
            'period' => 'custom', 'from' => '2026-11-21T08:00', 'to' => '2026-11-21T00:00',
        ]))->assertOk()->assertSee('Mốc kết thúc phải bằng hoặc sau mốc bắt đầu.');
    }

    public function test_admin_show_pagination_limits_rows_and_preserves_filters(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $movie = Movie::factory()->create();
        $room = Room::factory()->create();
        $shows = Show::factory()->for($movie)->for($room)->count(23)->create([
            'start_time' => '2026-11-20 11:00:00', 'end_time' => '2026-11-20 13:00:00',
        ]);
        Show::factory()->for($movie)->create();
        $params = ['period' => 'all', 'room_id' => $room->id, 'movie_id' => $movie->id];

        $first = $this->get(route('admin.shows', $params))->assertOk()->viewData('shows');
        $this->assertCount(20, $first);
        $this->assertSame(23, $first->total());
        parse_str(parse_url($first->nextPageUrl(), PHP_URL_QUERY), $nextQuery);
        $this->assertEquals($params + ['page' => 2], $nextQuery);

        $second = $this->get(route('admin.shows', $params + ['page' => 2]))->assertOk()->viewData('shows');
        $this->assertSame($shows->take(3)->pluck('id')->reverse()->values()->all(), $second->pluck('id')->all());
    }
}
