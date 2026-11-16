<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderSnack;
use App\Models\Snack;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_manages_snacks_and_deletion_preserves_old_order_items(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $id = $this->postJson('/api/admin/snacks', [
            'name' => 'Combo thử nghiệm',
            'price' => 45000,
            'image_url' => '/images/placeholder.png',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');

        $this->getJson('/api/snacks')->assertJsonPath('data.0.id', $id);
        $this->putJson("/api/admin/snacks/{$id}", [
            'name' => 'Combo lớn',
            'price' => 55000,
            'image_url' => null,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.name', 'Combo lớn')->assertJsonPath('data.is_active', false);
        $this->getJson('/api/snacks')->assertJsonPath('data', []);
        $this->getJson('/api/admin/snacks')->assertJsonPath('data.0.name', 'Combo lớn');

        $item = OrderSnack::factory()->for(Snack::findOrFail($id))->create(['price' => 45000]);
        $this->deleteJson("/api/admin/snacks/{$id}")->assertNoContent();
        $this->getJson('/api/admin/snacks')->assertJsonPath('data', []);
        $this->assertSoftDeleted('snacks', ['id' => $id]);
        $this->assertDatabaseHas('order_snacks', ['id' => $item->id, 'price' => 45000]);
        $this->assertSame($id, $item->fresh()->snack->id);
    }

    public function test_admin_manages_vouchers_and_deletion_preserves_order_voucher(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $id = $this->postJson('/api/admin/vouchers', [
            'code' => 'TEST20',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'min_order_value' => 100000,
            'max_uses_per_user' => 2,
            'valid_from' => '2026-11-20T10:00:00+07:00',
            'valid_to' => '2026-12-20T10:00:00+07:00',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.valid_from', '2026-11-20T03:00:00.000000Z')->json('data.id');

        $this->putJson("/api/admin/vouchers/{$id}", [
            'code' => 'TEST20',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.discount_type', 'fixed')->assertJsonPath('data.is_active', false);
        $this->getJson('/api/admin/vouchers')->assertJsonPath('data.0.code', 'TEST20');

        $order = Order::factory()->for(Voucher::findOrFail($id))->create(['total_amount' => 80000]);
        $this->deleteJson("/api/admin/vouchers/{$id}")->assertNoContent();
        $this->getJson('/api/admin/vouchers')->assertJsonPath('data', []);
        $this->assertSoftDeleted('vouchers', ['id' => $id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'voucher_id' => $id, 'total_amount' => 80000]);
        $this->assertSame('TEST20', $order->fresh()->voucher->code);
    }

    public function test_voucher_partial_updates_cannot_create_invalid_dates_or_percentages(): void
    {
        $voucher = Voucher::factory()->create([
            'discount_type' => 'percent',
            'discount_value' => 20,
            'valid_from' => '2026-11-20 04:00:00',
            'valid_to' => '2026-11-25 04:00:00',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->putJson("/api/admin/vouchers/{$voucher->id}", ['discount_value' => 101])
            ->assertUnprocessable()->assertJsonValidationErrors('discount_value');
        $this->putJson("/api/admin/vouchers/{$voucher->id}", ['valid_to' => '2026-11-20T10:00:00+07:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('valid_to');
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id, 'discount_value' => 20, 'valid_to' => '2026-11-25 04:00:00',
        ]);
    }
}
