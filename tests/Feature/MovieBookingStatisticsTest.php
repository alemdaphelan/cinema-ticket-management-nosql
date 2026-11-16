<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Order;
use App\Models\Room;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MovieBookingStatisticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rankings_follow_the_period_and_include_ties_and_movies_with_zero_bookings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $alpha = Movie::factory()->create(['title' => 'Alpha']);
        $bravo = Movie::factory()->create(['title' => 'Bravo']);
        $zero = Movie::factory()->create(['title' => 'Zero']);

        $alphaShows = Show::factory()->for($alpha)->count(2)->create(['start_time' => '2026-11-16 14:00:00']);
        $bravoShow = Show::factory()->for($bravo)->create(['start_time' => '2026-11-19 14:00:00']);
        $zeroShow = Show::factory()->for($zero)->create(['start_time' => '2026-11-16 14:00:00']);
        Show::factory()->create(['start_time' => '2026-11-19 14:00:00']);

        foreach ([
            [$alphaShows[0], 'paid', '2026-11-16 02:00:00'],
            [$alphaShows[1], 'completed', '2026-11-16 03:00:00'],
            [$bravoShow, 'paid', '2026-11-16 02:30:00'],
            [$bravoShow, 'paid', '2026-11-16 03:30:00'],
            [$zeroShow, 'pending', '2026-11-16 01:00:00'],
            [$zeroShow, 'cancelled', '2026-11-16 02:00:00'],
            [$zeroShow, 'paid', '2026-11-15 04:00:00'],
        ] as [$show, $status, $createdAt]) {
            Order::factory()->for($show)->create(['status' => $status, 'created_at' => $createdAt]);
        }

        $this->actingAs($admin)
            ->getJson('/api/admin/statistics?period_type=day&date=2026-11-16')
            ->assertOk()
            ->assertJsonPath('data.filtered.total_tickets', 4)
            ->assertJsonPath('data.movie_bookings.most', [
                ['movie_id' => $alpha->id, 'title' => 'Alpha', 'booking_count' => 2],
                ['movie_id' => $bravo->id, 'title' => 'Bravo', 'booking_count' => 2],
            ])
            ->assertJsonPath('data.movie_bookings.least', [
                ['movie_id' => $zero->id, 'title' => 'Zero', 'booking_count' => 0],
            ]);

        $this->getJson('/api/admin/statistics?period_type=custom&from_date=2026-11-15&to_date=2026-11-16')
            ->assertOk()
            ->assertJsonPath('data.movie_bookings.least.0.booking_count', 1);
    }

    public function test_period_without_orders_or_shows_returns_empty_rankings(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/admin/statistics?period_type=day&date=2026-11-16')
            ->assertOk()
            ->assertJsonPath('data.movie_bookings', ['most' => [], 'least' => []]);
    }

    public function test_dashboard_paginates_ranked_ties_independently_for_the_selected_period(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $room = Room::factory()->create();
        $date = '2026-11-16';
        $zeroIds = [];
        for ($index = 0; $index < 23; $index++) {
            $movie = Movie::factory()->create(['title' => sprintf('Phim %02d', $index)]);
            Show::factory()->for($room)->for($movie)->create(['start_time' => $date.' 14:00:00']);
            $zeroIds[] = $movie->id;
        }
        $popular = Movie::factory()->create(['title' => 'Phim có lượt đặt']);
        $show = Show::factory()->for($room)->for($popular)->create(['start_time' => $date.' 15:00:00']);
        Order::factory()->for($show)->create(['status' => 'paid', 'created_at' => $date.' 09:00:00']);
        Show::factory()->for($room)->create(['start_time' => '2026-11-17 15:00:00']);

        $params = 'period_type=day&date='.$date;
        $first = $this->getJson('/api/admin/statistics?paginate_movie_bookings=1&'.$params)
            ->assertOk()
            ->assertJsonPath('data.movie_bookings.most.items.0.movie_id', $popular->id)
            ->assertJsonPath('data.movie_bookings.most.next_page', null)
            ->assertJsonPath('data.movie_bookings.least.next_page', 2)
            ->assertJsonCount(10, 'data.movie_bookings.least.items');
        $this->assertSame(array_slice($zeroIds, 0, 10), array_column($first->json('data.movie_bookings.least.items'), 'movie_id'));

        foreach ([2 => 3, 3 => null] as $page => $next) {
            $response = $this->getJson('/api/admin/statistics/movie-bookings?ranking=least&page='.$page.'&'.$params)
                ->assertOk()->assertJsonPath('data.next_page', $next);
            $this->assertSame(array_slice($zeroIds, ($page - 1) * 10, 10), array_column($response->json('data.items'), 'movie_id'));
        }

        $this->getJson('/api/admin/statistics/movie-bookings?ranking=least&period_type=day&date=2026-11-18')
            ->assertOk()->assertJsonPath('data', ['items' => [], 'next_page' => null]);
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->getJson('/api/admin/statistics/movie-bookings?ranking=least')->assertForbidden();
    }
}
