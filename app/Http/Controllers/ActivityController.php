<?php

namespace App\Http\Controllers;

use App\Services\CassandraActivityRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(private CassandraActivityRepository $activities) {}

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'event_type' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $month = $validated['month'] ?? now()->format('Y-m');

        return response()->json([
            'data' => $this->activities->forUser(
                (int) $request->user()->id,
                $month,
                $validated['event_type'] ?? null,
                (int) ($validated['limit'] ?? 50),
            ),
            'partition' => ['user_id' => (int) $request->user()->id, 'month' => $month],
            'allow_filtering' => false,
        ]);
    }
}
