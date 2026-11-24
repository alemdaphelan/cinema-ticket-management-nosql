<?php

namespace App\Http\Controllers;

use App\Events\ActivityOccurred;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    /**
     * Quét QR Code & Soát vé.
     */
    public function scanTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $qrData = json_decode($validated['qr_code'], true);

        if (! is_array($qrData)
            || ! isset($qrData['order_id'], $qrData['code'])
            || ! is_numeric($qrData['order_id'])
            || ! is_string($qrData['code'])
            || $qrData['code'] === '') {
            return response()->json(['message' => 'Mã QR không hợp lệ.'], 422);
        }

        $order = Order::with(['show.movie', 'show.room', 'seats.seat', 'snacks.snack', 'user'])
            ->find((int) $qrData['order_id']);

        if (! $order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        $issuedQrData = json_decode((string) $order->qr_code, true);
        $issuedCode = is_array($issuedQrData) && isset($issuedQrData['code']) && is_string($issuedQrData['code'])
            ? $issuedQrData['code']
            : null;

        if (! is_array($issuedQrData)
            || ! isset($issuedQrData['order_id'])
            || (int) $issuedQrData['order_id'] !== (int) $order->id
            || $issuedCode === null
            || ! hash_equals($issuedCode, $qrData['code'])) {
            return response()->json(['message' => 'Mã QR không khớp với vé đã phát hành.'], 422);
        }

        if ($order->status !== 'paid') {
            return $this->ticketStateError($order);
        }

        // Chỉ một yêu cầu có thể đổi trạng thái paid -> completed. Nếu hai máy quét
        // cùng lúc, yêu cầu thua cuộc nhận 409 thay vì cùng báo thành công.
        $claimed = Order::query()
            ->whereKey($order->id)
            ->where('status', 'paid')
            ->update(['status' => 'completed']);

        if ($claimed !== 1) {
            return $this->ticketStateError($order->fresh());
        }

        ActivityOccurred::dispatch(
            'TICKET_SCANNED',
            (int) $order->user_id,
            (int) $order->show->movie_id,
            (int) $order->id,
            ['staff_id' => $request->user()?->id],
            'staff',
            'order',
            (string) $order->id,
        );

        return response()->json([
            'message' => 'Soát vé thành công! Vé đã được đánh dấu "Đã sử dụng".',
            'data' => $order->fresh(['show.movie', 'show.room', 'seats.seat', 'snacks.snack']),
        ]);
    }

    private function ticketStateError(Order $order): JsonResponse
    {
        if ($order->status === 'completed') {
            return response()->json([
                'message' => 'Vé này đã được sử dụng trước đó.',
                'data' => $order,
            ], 409);
        }

        return response()->json([
            'message' => 'Vé chưa được thanh toán hoặc đã bị hủy.',
            'data' => $order,
        ], 422);
    }
}
