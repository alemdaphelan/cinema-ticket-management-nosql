<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TicketHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_history_loads_ten_owned_orders_and_continues_without_duplicates(): void
    {
        $user = User::factory()->create();
        $show = Show::factory()->create();
        $sameDay = Order::factory()->for($user)->for($show)->count(12)->create([
            'status' => 'paid',
            'created_at' => '2026-11-16 03:00:00',
        ]);
        $older = Order::factory()->for($user)->for($show)->create([
            'status' => 'completed',
            'created_at' => '2026-11-14 03:00:00',
        ]);
        Order::factory()->for($show)->create([
            'status' => 'paid',
            'created_at' => '2026-11-16 07:00:00',
        ]);

        $sortedIds = $sameDay->pluck('id')->reverse()->values()->all();

        $first = $this->actingAs($user)->getJson('/api/orders/history')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.show.movie.id', $show->movie_id);
        $this->assertSame(array_slice($sortedIds, 0, 10), array_column($first->json('data'), 'id'));

        Order::factory()->for($user)->for($show)->create([
            'status' => 'paid',
            'created_at' => '2026-11-16 09:00:00',
        ]);

        $second = $this->getJson('/api/orders/history?'.http_build_query(['cursor' => $first->json('next_cursor')]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('next_cursor', null);
        $this->assertSame([...array_slice($sortedIds, 10), $older->id], array_column($second->json('data'), 'id'));
    }

    public function test_empty_history_returns_no_cursor(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/orders/history')
            ->assertOk()
            ->assertExactJson(['data' => [], 'next_cursor' => null]);
    }

    public function test_a_historical_order_without_a_show_is_still_accessible_to_its_owner(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create([
            'show_id' => null,
            'status' => 'completed',
        ]);

        $this->actingAs($user)->getJson('/api/orders/history')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.show', null);

        $this->get("/booking/payment/{$order->id}")->assertRedirect("/booking/success/{$order->id}");
        $this->get("/booking/success/{$order->id}")->assertOk();
    }
}
