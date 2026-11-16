<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Voucher::latest()->orderByDesc('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $voucher = Voucher::create($this->validateVoucher($request));

        return response()->json(['data' => $voucher->refresh(), 'message' => 'Tạo voucher thành công!'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $voucher = DB::transaction(function () use ($request, $id): Voucher {
            $voucher = Voucher::query()->lockForUpdate()->findOrFail($id);
            $voucher->update($this->validateVoucher($request, $voucher));

            return $voucher;
        }, 3);

        return response()->json(['data' => $voucher->refresh(), 'message' => 'Cập nhật voucher thành công!']);
    }

    public function destroy(int $id): JsonResponse
    {
        Voucher::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateVoucher(Request $request, ?Voucher $voucher = null): array
    {
        $required = $voucher ? 'sometimes|required' : 'required';
        $validated = $request->validate([
            'code' => [...explode('|', $required), 'string', 'max:50', Rule::unique('vouchers', 'code')->ignore($voucher?->id)],
            'discount_value' => $required.'|numeric|min:0|max:99999999.99',
            'discount_type' => $required.'|in:percent,fixed',
            'min_order_value' => $required.'|numeric|min:0|max:99999999.99',
            'max_uses_per_user' => $required.'|integer|min:1|max:2147483647',
            'valid_from' => $required.'|date',
            'valid_to' => $required.'|date',
            'is_active' => 'boolean',
        ]);

        $type = $validated['discount_type'] ?? $voucher?->discount_type;
        $amount = $validated['discount_value'] ?? $voucher?->discount_value;
        if ($type === 'percent' && $amount > 100) {
            throw ValidationException::withMessages(['discount_value' => 'Mức giảm theo phần trăm không được vượt quá 100%.']);
        }

        foreach (['valid_from', 'valid_to'] as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = CarbonImmutable::parse($validated[$field])->setTimezone(config('app.timezone'));
            }
        }

        $from = $validated['valid_from'] ?? $voucher?->valid_from;
        $to = $validated['valid_to'] ?? $voucher?->valid_to;
        if ($from && $to && $to->lte($from)) {
            throw ValidationException::withMessages(['valid_to' => 'Thời điểm hết hạn phải sau thời điểm bắt đầu.']);
        }

        return $validated;
    }
}
