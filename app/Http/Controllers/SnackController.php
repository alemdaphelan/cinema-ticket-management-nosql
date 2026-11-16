<?php

namespace App\Http\Controllers;

use App\Models\Snack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SnackController extends Controller
{
    /**
     * [Public] Danh sách bắp nước active.
     */
    public function publicIndex(): JsonResponse
    {
        $snacks = Snack::where('is_active', true)->get();

        return response()->json(['data' => $snacks]);
    }

    /**
     * [Admin] Danh sách tất cả bắp nước.
     */
    public function index(): JsonResponse
    {
        $snacks = Snack::orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $snacks]);
    }

    /**
     * [Admin] Thêm combo bắp nước.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'image_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $snack = Snack::create($validated);

        return response()->json(['data' => $snack->refresh(), 'message' => 'Thêm bắp nước thành công!'], 201);
    }

    /**
     * [Admin] Cập nhật bắp nước.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $snack = Snack::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0|max:99999999.99',
            'image_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $snack->update($validated);

        return response()->json(['data' => $snack, 'message' => 'Cập nhật thành công!']);
    }

    /**
     * [Admin] Xóa bắp nước.
     */
    public function destroy(int $id): JsonResponse
    {
        $snack = Snack::findOrFail($id);
        $snack->delete();

        return response()->json(null, 204);
    }
}
