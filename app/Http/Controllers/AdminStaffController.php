<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkShiftAssignment;
use App\Services\SimpleXlsxExporter;
use App\Services\StaffShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStaffController extends Controller
{
    public function __construct(
        private StaffShiftService $shiftService,
        private SimpleXlsxExporter $xlsxExporter,
    ) {}

    public function schedulePage(): View
    {
        return view('admin.staff.schedule');
    }

    public function attendancePage(): View
    {
        $staffList = User::query()
            ->where('role', 'staff')
            ->orderBy('employee_code')
            ->orderBy('name')
            ->get(['id', 'employee_code', 'name', 'staff_position']);

        return view('admin.staff.attendance', [
            'staffList' => $staffList,
        ]);
    }

    public function accountsPage(): View
    {
        return view('admin.staff.accounts');
    }

    public function schedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'shift_number' => 'nullable|integer|in:1,2,3',
        ]);

        $refDate = ! empty($validated['date'])
            ? CarbonImmutable::parse($validated['date'], StaffShiftService::TIMEZONE)
            : CarbonImmutable::now(StaffShiftService::TIMEZONE);

        $shiftFilter = isset($validated['shift_number']) ? (int) $validated['shift_number'] : null;

        return response()->json([
            'data' => $this->shiftService->getWeeklySchedule($refDate, null, $shiftFilter),
        ]);
    }

    public function alerts(): JsonResponse
    {
        return response()->json([
            'data' => $this->shiftService->getUnacknowledgedAlerts(),
        ]);
    }

    public function acknowledgeAlerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignment_ids' => 'nullable|array',
            'assignment_ids.*' => 'integer|exists:work_shift_assignments,id',
        ]);

        $query = WorkShiftAssignment::query()
            ->where('is_violation', true)
            ->whereNull('admin_alert_acknowledged_at');

        if (! empty($validated['assignment_ids'])) {
            $query->whereIn('id', $validated['assignment_ids']);
        }

        $updated = $query->update([
            'admin_alert_acknowledged_at' => now(),
        ]);

        return response()->json([
            'message' => "Đã xác nhận {$updated} cảnh báo vi phạm.",
            'acknowledged_count' => $updated,
        ]);
    }

    public function recordAttendance(Request $request, int $assignmentId): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:check_in,check_out,mark_absent,reset',
            'method' => 'nullable|in:manual,camera_ai',
            'confirm_early' => 'nullable|boolean',
        ]);

        $assignment = WorkShiftAssignment::with(['shift', 'user'])->findOrFail($assignmentId);
        $method = $validated['method'] ?? 'manual';

        if ($validated['action'] === 'check_in') {
            $updated = $this->shiftService->checkIn($assignment, $method);
            $message = 'Đã chấm công vào ca cho '.$updated->user?->name.'.';
        } elseif ($validated['action'] === 'check_out') {
            $updated = $this->shiftService->checkOut(
                $assignment,
                $method,
                null,
                (bool) ($validated['confirm_early'] ?? true)
            );
            $message = 'Đã chấm công kết thúc ca cho '.$updated->user?->name.'.';
        } elseif ($validated['action'] === 'mark_absent') {
            $assignment->update([
                'check_in_at' => null,
                'check_out_at' => null,
                'attendance_status' => 'absent',
                'is_violation' => true,
                'violation_reason' => 'Admin đánh dấu vắng mặt không phép',
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'worked_minutes' => 0,
                'admin_alert_acknowledged_at' => null,
            ]);
            $updated = $assignment->fresh(['shift', 'user']);
            $message = 'Đã ghi nhận vắng mặt cho '.$updated->user?->name.'.';
        } else {
            $assignment->update([
                'check_in_at' => null,
                'check_out_at' => null,
                'check_in_method' => null,
                'check_out_method' => null,
                'check_in_confidence' => null,
                'attendance_status' => 'pending',
                'is_violation' => false,
                'violation_reason' => null,
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'worked_minutes' => 0,
                'admin_alert_acknowledged_at' => null,
            ]);
            $updated = $assignment->fresh(['shift', 'user']);
            $message = 'Đã đặt lại trạng thái chấm công cho '.$updated->user?->name.'.';
        }

        return response()->json([
            'message' => $message,
            'data' => $this->shiftService->serializeAssignment($updated),
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $filters = $request->only([
            'period_type',
            'date',
            'week',
            'month',
            'year',
            'user_id',
            'search',
            'status',
            'shift_number',
        ]);

        $baseQuery = $this->shiftService->buildAttendanceQuery($filters);

        $allMatching = (clone $baseQuery)->get();
        $summary = [
            'total' => $allMatching->count(),
            'valid' => $allMatching->whereIn('attendance_status', ['on_time', 'completed'])->count(),
            'violation' => $allMatching->where('is_violation', true)->count(),
            'absent' => $allMatching->where('attendance_status', 'absent')->count(),
            'total_hours' => round($allMatching->sum('worked_minutes') / 60, 2),
        ];

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $total = $summary['total'];
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($lastPage, (int) $request->input('page', 1)));
        $paginated = $baseQuery->paginate($perPage, ['*'], 'page', $page);
        $items = collect($paginated->items())
            ->map(fn (WorkShiftAssignment $a) => $this->shiftService->serializeAssignment($a))
            ->values()
            ->all();

        [$fromDate, $toDate] = $this->shiftService->resolvePeriodBounds($filters);

        return response()->json([
            'data' => $items,
            'summary' => $summary,
            'period' => [
                'type' => $filters['period_type'] ?? 'month',
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
            ],
        ]);
    }

    public function exportAttendance(Request $request): StreamedResponse
    {
        $filters = $request->only([
            'period_type',
            'date',
            'week',
            'month',
            'year',
            'user_id',
            'search',
            'status',
            'shift_number',
        ]);

        $records = $this->shiftService->buildAttendanceQuery($filters)->get();

        $headers = [
            'Mã NV',
            'Họ và tên',
            'Vị trí',
            'Ngày làm',
            'Ca làm',
            'Giờ quy định',
            'Giờ vào ca',
            'PT chấm vào',
            'Giờ ra ca',
            'PT chấm ra',
            'Số giờ làm',
            'Trạng thái',
            'Vi phạm',
            'Chi tiết vi phạm',
        ];

        $rows = [];
        foreach ($records as $record) {
            $s = $this->shiftService->serializeAssignment($record);
            $rows[] = [
                $s['employee_code'],
                $s['employee_name'],
                $s['position_label'],
                $s['shift_display_date'],
                $s['shift_name'],
                $s['shift_time'],
                $s['check_in_display'] ?: '—',
                $this->methodLabel($s['check_in_method']),
                $s['check_out_display'] ?: '—',
                $this->methodLabel($s['check_out_method']),
                $s['worked_hours'],
                $s['attendance_status_label'],
                $s['is_violation'] ? 'Có' : 'Không',
                $s['violation_reason'] ?: '—',
            ];
        }

        $filenameBase = 'bang-cham-cong-'.CarbonImmutable::now(StaffShiftService::TIMEZONE)->format('Ymd_His');

        return $this->xlsxExporter->download($filenameBase, 'Bang cham cong', $headers, $rows);
    }

    public function accounts(Request $request): JsonResponse
    {
        $query = User::query()->where('role', 'staff');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('position') && $request->input('position') !== 'all') {
            $query->where('staff_position', $request->input('position'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('account_status', $request->input('status'));
        }

        $staff = $query->orderBy('employee_code')->orderBy('id')->get();
        $allStaff = User::query()->where('role', 'staff')->get(['id', 'account_status']);

        return response()->json([
            'data' => $staff->map(fn (User $u) => $this->serializeStaffAccount($u))->values()->all(),
            'summary' => [
                'total' => $allStaff->count(),
                'active' => $allStaff->where('account_status', 'active')->count(),
                'locked' => $allStaff->where('account_status', 'locked')->count(),
                'inactive' => $allStaff->where('account_status', 'inactive')->count(),
            ],
        ]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_code' => 'nullable|string|max:20|unique:users,employee_code',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'nullable|string|min:4|max:100',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'citizen_id' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'staff_position' => 'required|in:ticket_checker,cashier,snack_prep',
            'hourly_rate' => 'nullable|integer|min:15000|max:500000',
            'account_status' => 'nullable|in:active,locked,inactive',
            'hired_at' => 'nullable|date',
        ]);

        $employeeCode = ! empty($validated['employee_code'])
            ? strtoupper(trim($validated['employee_code']))
            : $this->generateNextEmployeeCode();

        $plainPassword = ! empty($validated['password'])
            ? $validated['password']
            : $employeeCode;

        $staff = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($plainPassword),
            'role' => 'staff',
            'employee_code' => $employeeCode,
            'phone' => $validated['phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? 'other',
            'citizen_id' => $validated['citizen_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'staff_position' => $validated['staff_position'],
            'hourly_rate' => $validated['hourly_rate'] ?? 35000,
            'account_status' => $validated['account_status'] ?? 'active',
            'hired_at' => $validated['hired_at'] ?? now()->toDateString(),
            'display_password' => $plainPassword,
        ]);

        return response()->json([
            'message' => "Đã tạo tài khoản nhân viên {$staff->name} ({$staff->employee_code}).",
            'data' => $this->serializeStaffAccount($staff),
        ], 201);
    }

    public function updateAccount(Request $request, int $id): JsonResponse
    {
        $staff = User::where('role', 'staff')->findOrFail($id);

        $validated = $request->validate([
            'employee_code' => ['required', 'string', 'max:20', Rule::unique('users', 'employee_code')->ignore($staff->id)],
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'password' => 'nullable|string|min:4|max:100',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'citizen_id' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'staff_position' => 'required|in:ticket_checker,cashier,snack_prep',
            'hourly_rate' => 'nullable|integer|min:15000|max:500000',
            'account_status' => 'required|in:active,locked,inactive',
            'hired_at' => 'nullable|date',
        ]);

        $payload = [
            'employee_code' => strtoupper(trim($validated['employee_code'])),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? 'other',
            'citizen_id' => $validated['citizen_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'staff_position' => $validated['staff_position'],
            'hourly_rate' => $validated['hourly_rate'] ?? $staff->hourly_rate,
            'account_status' => $validated['account_status'],
            'hired_at' => $validated['hired_at'] ?? $staff->hired_at,
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
            $payload['display_password'] = $validated['password'];
        }

        $staff->update($payload);

        return response()->json([
            'message' => "Đã cập nhật thông tin nhân viên {$staff->name}.",
            'data' => $this->serializeStaffAccount($staff->fresh()),
        ]);
    }

    public function resetPassword(int $id): JsonResponse
    {
        $staff = User::where('role', 'staff')->findOrFail($id);
        $defaultPassword = $staff->employee_code ?: ('NV'.str_pad((string) $staff->id, 3, '0', STR_PAD_LEFT));

        $staff->update([
            'employee_code' => $defaultPassword,
            'password' => Hash::make($defaultPassword),
            'display_password' => $defaultPassword,
        ]);

        return response()->json([
            'message' => "Đã reset mật khẩu của {$staff->name} về mặc định là mã nhân viên: {$defaultPassword}",
            'default_password' => $defaultPassword,
            'data' => $this->serializeStaffAccount($staff->fresh()),
        ]);
    }

    public function updateAccountStatus(Request $request, int $id): JsonResponse
    {
        $staff = User::where('role', 'staff')->findOrFail($id);
        $validated = $request->validate([
            'account_status' => 'required|in:active,locked,inactive',
        ]);

        $staff->update(['account_status' => $validated['account_status']]);

        $statusLabel = match ($validated['account_status']) {
            'locked' => 'Đã khóa',
            'inactive' => 'Ngừng làm việc',
            default => 'Đang hoạt động',
        };

        return response()->json([
            'message' => "Đã chuyển trạng thái tài khoản {$staff->name} sang «{$statusLabel}».",
            'data' => $this->serializeStaffAccount($staff->fresh()),
        ]);
    }

    private function generateNextEmployeeCode(): string
    {
        $maxNum = User::query()
            ->whereNotNull('employee_code')
            ->pluck('employee_code')
            ->map(fn (string $code) => preg_match('/^NV(\d+)$/i', $code, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'NV'.str_pad((string) ($maxNum + 1), 3, '0', STR_PAD_LEFT);
    }

    private function serializeStaffAccount(User $staff): array
    {
        $statusLabel = match ($staff->account_status) {
            'locked' => 'Đang khóa',
            'inactive' => 'Nghỉ việc',
            default => 'Đang hoạt động',
        };

        $genderLabel = match ($staff->gender) {
            'male' => 'Nam',
            'female' => 'Nữ',
            default => 'Khác',
        };

        $storedPassword = null;
        try {
            $storedPassword = $staff->display_password;
        } catch (\Throwable) {
            $storedPassword = null;
        }

        return [
            'id' => $staff->id,
            'employee_code' => $staff->employee_code ?: ('NV'.str_pad((string) $staff->id, 3, '0', STR_PAD_LEFT)),
            'name' => $staff->name,
            'email' => $staff->email,
            'display_password' => $storedPassword ?: ($staff->employee_code ?: '******'),
            'phone' => $staff->phone,
            'date_of_birth' => $staff->date_of_birth?->format('Y-m-d'),
            'date_of_birth_display' => $staff->date_of_birth?->format('d/m/Y'),
            'gender' => $staff->gender ?? 'other',
            'gender_label' => $genderLabel,
            'citizen_id' => $staff->citizen_id,
            'address' => $staff->address,
            'staff_position' => $staff->staff_position ?? 'ticket_checker',
            'position_label' => $staff->positionLabel(),
            'hourly_rate' => (int) ($staff->hourly_rate ?: 35000),
            'account_status' => $staff->account_status ?? 'active',
            'account_status_label' => $statusLabel,
            'hired_at' => $staff->hired_at?->format('Y-m-d'),
            'hired_at_display' => $staff->hired_at?->format('d/m/Y'),
        ];
    }

    private function methodLabel(?string $method): string
    {
        return match ($method) {
            'camera_ai' => 'Camera AI',
            'manual' => 'Thủ công',
            default => '—',
        };
    }
}
