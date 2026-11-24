<?php

namespace Tests\Feature;

use App\Events\ActivityOccurred;
use App\Models\Order;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderAuthorizationAndTicketScanTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_customer_only_sees_owned_order_while_staff_and_admin_can_inspect_any_order(): void
    {
        $owner = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::factory()->for($owner)->create();

        $this->actingAs($owner)->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        $this->actingAs($otherCustomer)->getJson("/api/orders/{$order->id}")
            ->assertNotFound();

        $this->actingAs($staff)->getJson("/api/staff/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        $this->actingAs($admin)->getJson("/api/staff/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_ticket_scan_requires_the_secret_code_from_the_issued_qr(): void
    {
        Event::fake([ActivityOccurred::class]);

        $staff = User::factory()->create(['role' => 'staff']);
        $show = Show::factory()->create();
        $order = Order::factory()->for($show)->create([
            'status' => 'paid',
            'qr_code' => null,
        ]);

        $qrCode = json_encode([
            'order_id' => $order->id,
            'code' => 'CINEMA-VALID123',
        ], JSON_THROW_ON_ERROR);

        $order->update(['qr_code' => $qrCode]);

        $forgedQrCode = json_encode([
            'order_id' => $order->id,
            'code' => 'CINEMA-FORGED99',
        ], JSON_THROW_ON_ERROR);

        $this->actingAs($staff)->postJson('/api/staff/tickets/scan', ['qr_code' => $forgedQrCode])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Mã QR không khớp với vé đã phát hành.');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);

        $this->postJson('/api/staff/tickets/scan', ['qr_code' => $qrCode])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'completed');

        $this->postJson('/api/staff/tickets/scan', ['qr_code' => $qrCode])
            ->assertConflict()
            ->assertJsonPath('message', 'Vé này đã được sử dụng trước đó.');

        Event::assertDispatchedTimes(ActivityOccurred::class, 1);
    }
}
