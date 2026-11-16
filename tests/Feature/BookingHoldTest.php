<?php

namespace Tests\Feature;

use App\Jobs\ReleaseSeatJob;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Show;
use App\Models\Snack;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BookingHoldService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BookingHoldTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_three_expired_holds_release_seats_and_block_booking_for_fifteen_minutes(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $show = Show::factory()->create();
        $seat = Seat::factory()->for($show->room)->create(['type' => 'standard']);
        $payload = ['show_id' => $show->id, 'seat_ids' => [$seat->id]];
        $this->actingAs($user);

        foreach ([2, 1, 0] as $remaining) {
            $orderId = $this->postJson('/api/orders/hold', $payload)
                ->assertCreated()
                ->json('data.order_id');

            $this->getJson("/api/shows/{$show->id}/seats")
                ->assertJsonPath('data.user_held_seat_ids', [$seat->id]);

            $this->travel(10)->minutes();

            if ($remaining === 2) {
                (new ReleaseSeatJob($orderId))->handle(app(BookingHoldService::class));
            }

            $expired = $this->postJson("/api/orders/{$orderId}/expire");
            $this->assertDatabaseHas('orders', [
                'id' => $orderId,
                'status' => 'cancelled',
                'cancel_reason' => 'hold_expired',
            ]);
            $this->getJson("/api/shows/{$show->id}/seats")
                ->assertJsonPath('data.user_held_seat_ids', [])
                ->assertJsonPath('data.seats.0.status', 'available');

            if ($remaining === 0) {
                $expired->assertTooManyRequests()->assertJsonPath('code', 'booking_blocked');
                $this->postJson('/api/orders/hold', $payload)
                    ->assertTooManyRequests()
                    ->assertJsonPath('code', 'booking_blocked');
                break;
            }

            $expired->assertConflict()
                ->assertJsonPath('code', 'hold_expired')
                ->assertJsonPath('data.remaining_attempts', $remaining);
            $this->postJson("/api/orders/{$orderId}/expire")
                ->assertConflict()
                ->assertJsonPath('data.remaining_attempts', $remaining);
            $this->postJson('/api/orders/hold', $payload + ['order_id' => $orderId])
                ->assertConflict()
                ->assertJsonPath('code', 'hold_expired');
        }

        $this->get(route('booking.seats', $show->id))
            ->assertRedirect(route('home'))
            ->assertSessionHas('booking_notice.title', 'Tạm khóa đặt vé');
        $this->get(route('booking.snacks', $orderId))->assertRedirect(route('home'));
        $this->get(route('booking.payment', $orderId))->assertRedirect(route('home'));
        $this->get(route('booking.history'))->assertOk();
        $this->assertDatabaseCount('orders', 3);

        $this->travel(14)->minutes();
        $this->postJson('/api/orders/hold', $payload)->assertTooManyRequests();
        $this->travel(1)->minutes();
        $newOrderId = $this->postJson('/api/orders/hold', $payload)->assertCreated()->json('data.order_id');
        $this->travel(10)->minutes();
        $this->postJson("/api/orders/{$newOrderId}/expire")
            ->assertConflict()
            ->assertJsonPath('data.remaining_attempts', 2);
    }

    public function test_reopening_expired_checkout_redirects_to_seat_selection_with_a_notice(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create([
            'status' => 'pending',
            'hold_expires_at' => now()->subSecond(),
        ]);

        $this->actingAs($user);
        $this->get(route('booking.payment', $order))
            ->assertRedirect(route('booking.seats', $order->show_id))
            ->assertSessionHas('booking_notice.title', 'Hết thời gian giữ ghế');
        $this->get(route('booking.snacks', $order))
            ->assertRedirect(route('booking.seats', $order->show_id))
            ->assertSessionHas('booking_notice.title', 'Hết thời gian giữ ghế');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'expired_booking_holds' => 1]);
    }

    public function test_active_hold_can_be_replaced_and_cancelled_without_an_expiration_strike(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $show = Show::factory()->create();
        $seat = Seat::factory()->for($show->room)->create(['type' => 'standard']);
        $payload = ['show_id' => $show->id, 'seat_ids' => [$seat->id]];
        $this->actingAs($user);

        $first = $this->postJson('/api/orders/hold', $payload)->assertCreated()->json('data');
        $this->travel(2)->minutes();
        $this->postJson("/api/orders/{$first['order_id']}/expire")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $replacement = $this->postJson('/api/orders/hold', $payload + ['order_id' => $first['order_id']])
            ->assertCreated()
            ->assertJsonPath('data.order_id', $first['order_id'])
            ->assertJsonPath('data.hold_expires_at', $first['hold_expires_at'])
            ->json('data.order_id');

        $this->postJson("/api/orders/{$replacement}/cancel", ['reason' => 'change_seats'])->assertOk();
        $this->travel(8)->minutes();

        (new ReleaseSeatJob($first['order_id']))->handle(app(BookingHoldService::class));
        (new ReleaseSeatJob($replacement))->handle(app(BookingHoldService::class));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'expired_booking_holds' => 0]);
        $this->postJson('/api/orders/hold', $payload)->assertCreated();
    }

    public function test_changing_seats_from_payment_preserves_the_order_and_snacks(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $show = Show::factory()->create(['price' => 80000]);
        $standard = Seat::factory()->for($show->room)->create(['type' => 'standard', 'row_label' => 'A', 'seat_number' => 1]);
        $vip = Seat::factory()->for($show->room)->create(['type' => 'vip', 'row_label' => 'A', 'seat_number' => 2]);
        $snack = Snack::factory()->create(['price' => 25000, 'is_active' => true]);
        $voucher = Voucher::factory()->create([
            'discount_type' => 'percent', 'discount_value' => 10, 'min_order_value' => 120000,
            'valid_from' => now()->subHour(), 'valid_to' => now()->addHour(), 'is_active' => true,
        ]);
        $this->actingAs($user);

        $hold = $this->postJson('/api/orders/hold', ['show_id' => $show->id, 'seat_ids' => [$standard->id]])
            ->assertCreated()->json('data');
        $orderId = $hold['order_id'];
        $this->putJson("/api/orders/{$orderId}/snacks", [
            'snacks' => [['snack_id' => $snack->id, 'quantity' => 2]],
            'continue_to_payment' => false,
        ])->assertOk();
        $this->get(route('booking.seats', $show->id))->assertOk()
            ->assertViewHas('checkoutReady', false)->assertDontSee('data-booking-nav="payment"', false);
        $this->putJson("/api/orders/{$orderId}/snacks", ['snacks' => [['snack_id' => $snack->id, 'quantity' => 2]]])->assertOk();
        $snackLineId = Order::findOrFail($orderId)->snacks()->sole()->id;
        $this->postJson("/api/orders/{$orderId}/voucher", ['voucher_code' => $voucher->code])->assertOk();
        $this->putJson("/api/orders/{$orderId}/payment-method", ['payment_method' => 'momo'])
            ->assertOk()->assertJsonPath('data.payment_method', 'momo');

        $this->get(route('booking.payment', $orderId))->assertOk()
            ->assertSee('data-booking-nav="seats"', false)
            ->assertSee('data-booking-nav="snacks"', false)
            ->assertSee('value="momo" checked', false)
            ->assertSee($voucher->code);
        $this->get(route('booking.seats', $show->id))
            ->assertOk()->assertViewHas('checkoutReady', true)
            ->assertSee('Giữ ghế & Tiếp tục')
            ->assertSee('data-booking-nav="snacks"', false)
            ->assertSee('data-booking-nav="payment"', false);

        $this->travel(2)->minutes();
        $this->postJson('/api/orders/hold', ['show_id' => $show->id, 'order_id' => $orderId, 'seat_ids' => [$vip->id]])
            ->assertCreated()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.hold_expires_at', $hold['hold_expires_at'])
            ->assertJsonPath('data.total_amount', '153000.00');

        $this->getJson("/api/orders/{$orderId}")->assertOk()
            ->assertJsonPath('data.voucher_id', $voucher->id)
            ->assertJsonPath('data.seats.0.seat_id', $vip->id)
            ->assertJsonPath('data.snacks.0.id', $snackLineId)
            ->assertJsonPath('data.snacks.0.quantity', 2)
            ->assertJsonPath('data.checkout_payment_method', 'momo');
        $this->getJson("/api/shows/{$show->id}/seats")->assertJsonPath('data.user_held_seat_ids', [$vip->id]);
        $this->assertDatabaseCount('orders', 1);

        $this->putJson("/api/orders/{$orderId}/snacks", ['snacks' => [['snack_id' => $snack->id, 'quantity' => 1]]])
            ->assertOk()->assertJsonPath('data.total_amount', '130500.00');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'checkout_payment_method' => 'momo', 'voucher_id' => $voucher->id]);
        $this->postJson('/api/orders/hold', ['show_id' => $show->id, 'order_id' => $orderId, 'seat_ids' => [$standard->id]])
            ->assertCreated()->assertJsonPath('data.total_amount', '105000.00');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'voucher_id' => null]);
    }

    public function test_conflicting_seat_edit_keeps_the_same_hold_and_snacks(): void
    {
        $show = Show::factory()->create();
        $user = User::factory()->create();
        $seat = Seat::factory()->for($show->room)->create(['type' => 'standard', 'seat_number' => 1]);
        $taken = Seat::factory()->for($show->room)->create(['type' => 'standard', 'seat_number' => 2]);
        $otherOrder = Order::factory()->for($show)->create(['status' => 'paid']);
        $otherOrder->seats()->create(['seat_id' => $taken->id, 'price' => $show->price]);
        $snack = Snack::factory()->create(['is_active' => true]);

        $hold = $this->actingAs($user)->postJson('/api/orders/hold', ['show_id' => $show->id, 'seat_ids' => [$seat->id]])
            ->assertCreated()->json('data');
        $orderId = $hold['order_id'];
        $this->putJson("/api/orders/{$orderId}/snacks", ['snacks' => [['snack_id' => $snack->id, 'quantity' => 1]]])->assertOk();

        $this->postJson('/api/orders/hold', ['show_id' => $show->id, 'order_id' => $orderId, 'seat_ids' => [$taken->id]])
            ->assertConflict();
        $this->getJson("/api/orders/{$orderId}")->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.hold_expires_at', $hold['hold_expires_at'])
            ->assertJsonPath('data.seats.0.seat_id', $seat->id)
            ->assertJsonPath('data.snacks.0.snack_id', $snack->id);
    }

    public function test_expired_order_rejects_checkout_actions_without_counting_it_again(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create([
            'status' => 'pending',
            'hold_expires_at' => now()->subSecond(),
            'payment_method' => null,
        ]);
        $this->actingAs($user);

        $this->putJson("/api/orders/{$order->id}/snacks", ['snacks' => []])
            ->assertConflict()->assertJsonPath('code', 'hold_expired');
        $this->postJson("/api/orders/{$order->id}/voucher", ['voucher_code' => 'WELCOME50'])
            ->assertConflict()->assertJsonPath('code', 'hold_expired');
        $this->postJson("/api/orders/{$order->id}/pay", ['payment_method' => 'vnpay'])
            ->assertConflict()->assertJsonPath('code', 'hold_expired');
        $this->putJson("/api/orders/{$order->id}/payment-method", ['payment_method' => 'momo'])
            ->assertConflict()->assertJsonPath('code', 'hold_expired');
        $this->postJson("/api/orders/{$order->id}/cancel", ['reason' => 'change_seats'])
            ->assertConflict()->assertJsonPath('code', 'hold_expired');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'expired_booking_holds' => 1]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'payment_method' => null]);
    }

    public function test_checkout_rejects_cash_and_an_old_cash_preference_falls_back_to_vnpay(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'hold_expires_at' => now()->addMinutes(10),
            'payment_method' => null,
            'checkout_payment_method' => 'cash',
        ]);
        $this->actingAs($order->user)->get(route('booking.payment', $order))
            ->assertOk()->assertSee('value="vnpay" checked', false)
            ->assertSee('value="momo"', false)->assertDontSee('value="cash"', false);

        $this->putJson("/api/orders/{$order->id}/payment-method", ['payment_method' => 'cash'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->postJson("/api/orders/{$order->id}/pay", ['payment_method' => 'cash'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending', 'payment_method' => null]);

        $this->postJson("/api/orders/{$order->id}/pay", ['payment_method' => 'momo'])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid', 'payment_method' => 'momo']);
    }

    public function test_expiration_cannot_be_triggered_for_another_account_and_paid_orders_are_preserved(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'hold_expires_at' => now()->addMinutes(10),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/orders/{$order->id}/expire")->assertNotFound();
        $this->actingAs($order->user)
            ->postJson("/api/orders/{$order->id}/pay", ['payment_method' => 'vnpay'])
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->travel(10)->minutes();
        (new ReleaseSeatJob($order->id))->handle(app(BookingHoldService::class));
        $this->postJson("/api/orders/{$order->id}/expire")
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertDatabaseHas('users', ['id' => $order->user_id, 'expired_booking_holds' => 0]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }
}
