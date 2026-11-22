<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkShiftAssignment;
use App\Services\SimpleXlsxExporter;
use App\Services\StaffShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffWorkspaceController extends Controller
{
    public function __construct(
        private StaffShiftService $shiftService,
        private SimpleXlsxExporter $xlsxExporter,
    ) {}

    public function schedulePage(Request $request): View
    {
        return view('staff.schedule', $this->viewContext($request));
    }

    public function attendancePage(Request $request): View
    {
        return view('staff.attendance', $this->viewContext($request));
    }

    public function payrollPage(Request $request): View
    {
        return view('staff.payroll', $this->viewContext($request));
    }

    public function mySchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'shift_number' => 'nullable|integer|in:1,2,3',
            'staff_id' => 'nullable|integer|exists:users,id',
        ]);

        $staff = $this->resolveTargetStaff($request);
        $refDate = ! empty($validated['date'])
            ? CarbonImmutable::parse($validated['date'], StaffShiftService::TIMEZONE)
            : CarbonImmutable::now(StaffShiftService::TIMEZONE);

        $shiftFilter = isset($validated['shift_number']) ? (int) $validated['shift_number'] : null;
        $data = $this->shiftService->getWeeklySchedule($refDate, $staff->id, $shiftFilter);
        $data['staff'] = [
            'id' => $staff->id,
            'employee_code' => $staff->employee_code,
            'name' => $staff->name,
            'position_label' => $staff->positionLabel(),
        ];

        return response()->json(['data' => $data]);
    }

    public function myTodayAttendance(Request $request): JsonResponse
    {
        $staff = $this->resolveTargetStaff($request);
        $nowVn = CarbonImmutable::now(StaffShiftService::TIMEZONE);
        $nowStorage = $nowVn->setTimezone(config('app.timezone'));

        $this->shiftService->ensureRangeShifts($nowVn->subDays(1), $nowVn->addDays(6));
        $this->shiftService->syncStatusesAndAbsences($nowVn);

        $todayStr = $nowVn->format('Y-m-d');
        $endWindowStr = $nowVn->addDays(2)->format('Y-m-d');

        // Các ca đang diễn ra (bao gồm ca đêm qua) hoặc hôm nay / ngày mai
        $todayAssignments = WorkShiftAssignment::query()
            ->select('work_shift_assignments.*')
            ->join('work_shifts', 'work_shifts.id', '=', 'work_shift_assignments.work_shift_id')
            ->where('work_shift_assignments.user_id', $staff->id)
            ->where(function ($q) use ($todayStr, $endWindowStr, $nowStorage) {
                $q->whereBetween('work_shifts.shift_date', [$todayStr, $endWindowStr])
                    ->orWhere(function ($sub) use ($nowStorage) {
                        $sub->where('work_shifts.starts_at', '<=', $nowStorage)
                            ->where('work_shifts.ends_at', '>', $nowStorage);
                    });
            })
            ->with(['shift', 'user:id,name,email,employee_code,staff_position,hourly_rate'])
            ->orderBy('work_shifts.starts_at')
            ->limit(6)
            ->get()
            ->map(fn (WorkShiftAssignment $a) => $this->shiftService->serializeAssignment($a))
            ->values()
            ->all();

        $recentQuery = WorkShiftAssignment::query()
            ->select('work_shift_assignments.*')
            ->join('work_shifts', 'work_shifts.id', '=', 'work_shift_assignments.work_shift_id')
            ->where('work_shift_assignments.user_id', $staff->id)
            ->where('work_shifts.shift_date', '<=', $todayStr)
            ->with(['shift', 'user:id,name,email,employee_code,staff_position,hourly_rate'])
            ->orderByDesc('work_shifts.shift_date')
            ->orderByDesc('work_shifts.shift_number');

        $recentPerPage = max(1, min(50, (int) $request->input('recent_per_page', 10)));
        $recentTotal = (clone $recentQuery)->count();
        $recentLastPage = max(1, (int) ceil($recentTotal / $recentPerPage));
        $recentPage = max(1, min($recentLastPage, (int) $request->input('recent_page', 1)));
        $recentPaginated = $recentQuery->paginate($recentPerPage, ['*'], 'recent_page', $recentPage);

        $recentAssignments = collect($recentPaginated->items())
            ->map(fn (WorkShiftAssignment $a) => $this->shiftService->serializeAssignment($a))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'staff' => [
                    'id' => $staff->id,
                    'employee_code' => $staff->employee_code,
                    'name' => $staff->name,
                    'position_label' => $staff->positionLabel(),
                ],
                'server_time_iso' => $nowVn->toIso8601String(),
                'server_time_display' => $nowVn->format('H:i:s d/m/Y'),
                'today_assignments' => $todayAssignments,
                'recent_assignments' => $recentAssignments,
                'recent_meta' => [
                    'current_page' => $recentPaginated->currentPage(),
                    'last_page' => $recentPaginated->lastPage(),
                    'per_page' => $recentPaginated->perPage(),
                    'total' => $recentPaginated->total(),
                    'from' => $recentPaginated->firstItem() ?? 0,
                    'to' => $recentPaginated->lastItem() ?? 0,
                ],
            ],
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignment_id' => 'required|integer|exists:work_shift_assignments,id',
            'method' => 'nullable|in:manual,camera_ai',
            'confidence' => 'nullable|numeric|min:0|max:100',
        ]);

        $staff = $this->resolveTargetStaff($request);
        $assignment = WorkShiftAssignment::with(['shift', 'user'])->findOrFail((int) $validated['assignment_id']);

        if ((int) $assignment->user_id !== (int) $staff->id) {
            return response()->json([
                'message' => 'Bạn không thể chấm công cho ca làm của nhân viên khác.',
            ], 403);
        }

        $method = $validated['method'] ?? 'manual';
        $confidence = isset($validated['confidence']) ? (float) $validated['confidence'] : null;

        $updated = $this->shiftService->checkIn($assignment, $method, $confidence);
        $serialized = $this->shiftService->serializeAssignment($updated);

        $msg = $updated->is_violation
            ? 'Đã chấm công bắt đầu ca — ghi nhận đi trễ '.$updated->late_minutes.' phút.'
            : 'Chấm công bắt đầu ca đúng giờ thành công!';

        return response()->json([
            'message' => $msg,
            'data' => $serialized,
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignment_id' => 'required|integer|exists:work_shift_assignments,id',
            'method' => 'nullable|in:manual,camera_ai',
            'confidence' => 'nullable|numeric|min:0|max:100',
            'confirm_early' => 'nullable|boolean',
        ]);

        $staff = $this->resolveTargetStaff($request);
        $assignment = WorkShiftAssignment::with(['shift', 'user'])->findOrFail((int) $validated['assignment_id']);

        if ((int) $assignment->user_id !== (int) $staff->id) {
            return response()->json([
                'message' => 'Bạn không thể chấm công cho ca làm của nhân viên khác.',
            ], 403);
        }

        $method = $validated['method'] ?? 'manual';
        $confidence = isset($validated['confidence']) ? (float) $validated['confidence'] : null;
        $confirmEarly = (bool) ($validated['confirm_early'] ?? false);

        $updated = $this->shiftService->checkOut($assignment, $method, $confidence, $confirmEarly);
        $serialized = $this->shiftService->serializeAssignment($updated);

        $msg = $updated->early_leave_minutes > 0
            ? 'Đã chấm công kết thúc ca — ghi nhận về sớm '.$updated->early_leave_minutes.' phút.'
            : 'Chấm công kết thúc ca thành công!';

        return response()->json([
            'message' => $msg,
            'data' => $serialized,
        ]);
    }

    public function myPayroll(Request $request): JsonResponse
    {
        $staff = $this->resolveTargetStaff($request);
        $filters = $request->only([
            'period_type',
            'date',
            'week',
            'month',
            'year',
            'status',
            'shift_number',
        ]);

        $assignments = $this->shiftService->buildAttendanceQuery($filters, $staff->id)->get();
        $payroll = $this->shiftService->calculatePayroll($assignments, $staff);
        [$fromDate, $toDate] = $this->shiftService->resolvePeriodBounds($filters);

        $allRows = $payroll['rows'];
        $perPage = max(1, min(100, (int) $request->input('per_page', 10)));
        $total = count($allRows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = max(1, min($lastPage, (int) $request->input('page', 1)));
        $offset = ($currentPage - 1) * $perPage;
        $pagedRows = array_slice($allRows, $offset, $perPage);

        $payroll['rows'] = array_values($pagedRows);
        $payroll['meta'] = [
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => $total > 0 ? min($total, $offset + count($pagedRows)) : 0,
        ];
        $payroll['period'] = [
            'type' => $filters['period_type'] ?? 'month',
            'from' => $fromDate,
            'to' => $toDate,
        ];

        return response()->json(['data' => $payroll]);
    }

    public function exportPayroll(Request $request): StreamedResponse
    {
        $staff = $this->resolveTargetStaff($request);
        $filters = $request->only([
            'period_type',
            'date',
            'week',
            'month',
            'year',
            'status',
            'shift_number',
        ]);

        $assignments = $this->shiftService->buildAttendanceQuery($filters, $staff->id)->get();
        $payroll = $this->shiftService->calculatePayroll($assignments, $staff);
        $summary = $payroll['summary'];

        $headers = [
            'Mã NV',
            'Họ và tên',
            'Ngày làm',
            'Ca làm',
            'Giờ quy định',
            'Vị trí',
            'Giờ vào ca',
            'Giờ ra ca',
            'Giờ công',
            'Lương cơ bản',
            'Phụ cấp đêm',
            'Khấu trừ',
            'Thực lãnh',
            'Trạng thái',
            'Ghi chú vi phạm',
        ];

        $rows = [];
        foreach ($payroll['rows'] as $row) {
            $rows[] = [
                $row['employee_code'] ?? '',
                $row['employee_name'] ?? '',
                $row['shift_display_date'] ?? '',
                $row['shift_name'] ?? '',
                $row['shift_time'] ?? '',
                $row['position_label'] ?? '',
                $row['check_in_display'] ?? '',
                $row['check_out_display'] ?? '',
                $row['billable_hours'] ?? 0,
                $row['base_pay'] ?? 0,
                $row['night_bonus'] ?? 0,
                $row['penalty'] ?? 0,
                $row['net_pay'] ?? 0,
                $row['attendance_status_label'] ?? '',
                $row['violation_reason'] ?? '',
            ];
        }

        $rows[] = [
            'TỔNG CỘNG',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $summary['total_hours'] ?? 0,
            $summary['total_base_pay'] ?? 0,
            $summary['total_night_bonus'] ?? 0,
            $summary['total_penalty'] ?? 0,
            $summary['total_net_salary'] ?? 0,
            '',
            '',
        ];

        $code = preg_replace('/[^a-z0-9_-]/i', '', $staff->employee_code ?: ('nv'.$staff->id)) ?: ('nv'.$staff->id);
        $filenameBase = 'cham-cong-luong-'.strtolower($code).'-'.CarbonImmutable::now(StaffShiftService::TIMEZONE)->format('Ymd_His');

        return $this->xlsxExporter->download($filenameBase, 'Cham cong & Luong', $headers, $rows);
    }

    private function resolveTargetStaff(Request $request): User
    {
        $authUser = $request->user();
        if (! $authUser->isAdmin()) {
            return $authUser;
        }

        $requestedId = $request->input('staff_id');
        if ($requestedId) {
            $candidate = User::where('role', 'staff')->find((int) $requestedId);
            if ($candidate) {
                return $candidate;
            }
        }

        return User::query()
            ->where('role', 'staff')
            ->orderByRaw("CASE WHEN account_status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('employee_code')
            ->orderBy('id')
            ->first() ?? $authUser;
    }

    private function viewContext(Request $request): array
    {
        $authUser = $request->user();
        $selectedStaff = $this->resolveTargetStaff($request);
        $staffList = $authUser->isAdmin()
            ? User::query()->where('role', 'staff')->orderBy('employee_code')->get(['id', 'employee_code', 'name', 'staff_position'])
            : collect();

        return [
            'selectedStaff' => $selectedStaff,
            'staffList' => $staffList,
        ];
    }
}
