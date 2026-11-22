<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffShiftService
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public const CHECK_IN_EARLY_REQUIRED_MINUTES = 10;

    public const ABSENT_GRACE_MINUTES = 15;

    public const NIGHT_SHIFT_BONUS_RATE = 0.30;

    public const VIOLATION_PENALTY_VND = 50000;

    /**
     * Đảm bảo mỗi ngày trong khoảng [startDate, endDate] đều có đủ 3 ca làm việc và 5 nhân sự/ca.
     */
    public function ensureRangeShifts(CarbonImmutable $startDate, CarbonImmutable $endDate): void
    {
        $startVn = $startDate->setTimezone(self::TIMEZONE)->startOfDay();
        $endVn = $endDate->setTimezone(self::TIMEZONE)->startOfDay();

        $activeStaff = User::query()
            ->where('role', 'staff')
            ->where('account_status', 'active')
            ->orderBy('employee_code')
            ->orderBy('id')
            ->get();

        $pools = [
            'ticket_checker' => $activeStaff->where('staff_position', 'ticket_checker')->values(),
            'cashier' => $activeStaff->where('staff_position', 'cashier')->values(),
            'snack_prep' => $activeStaff->where('staff_position', 'snack_prep')->values(),
        ];

        $storageTz = config('app.timezone');

        DB::transaction(function () use ($startVn, $endVn, $activeStaff, $pools, $storageTz): void {
            for ($day = $startVn; $day->lte($endVn); $day = $day->addDay()) {
                $shiftDateStr = $day->format('Y-m-d');
                $usedToday = [];

                foreach (WorkShift::DEFINITIONS as $shiftNumber => $def) {
                    $dayOffset = (int) ($def['start_day_offset'] ?? 0);
                    $startsAtVn = $day->addDays($dayOffset)->setTime($def['start_hour'], 0, 0);
                    $endsAtVn = $startsAtVn->addHours($def['duration_hours']);

                    $shift = WorkShift::firstOrCreate(
                        [
                            'shift_date' => $shiftDateStr,
                            'shift_number' => $shiftNumber,
                        ],
                        [
                            'name' => $def['name'],
                            'starts_at' => $startsAtVn->setTimezone($storageTz),
                            'ends_at' => $endsAtVn->setTimezone($storageTz),
                            'status' => 'scheduled',
                            'counter_revenue' => 0,
                        ]
                    );

                    if ($activeStaff->isEmpty()) {
                        continue;
                    }

                    // Chỉ tự phân bổ 5 nhân sự khi ca vừa được tạo mới hoặc chưa có nhân sự nào được gán.
                    if (! $shift->wasRecentlyCreated && $shift->assignments()->exists()) {
                        $existingIds = $shift->assignments()->pluck('user_id')->all();
                        foreach ($existingIds as $eid) {
                            $usedToday[(int) $eid] = true;
                        }
                        continue;
                    }

                    $dayRotation = $day->dayOfYear + $shiftNumber;
                    $assignedInShift = [];

                    foreach (WorkShiftAssignment::POSITION_QUOTAS as $position => $quota) {
                        $pool = $pools[$position]->isNotEmpty() ? $pools[$position] : $activeStaff;
                        $count = $pool->count();
                        if ($count === 0) {
                            continue;
                        }

                        $picked = 0;
                        // Vòng 1: ưu tiên nhân viên đúng vị trí chưa làm ca nào trong ngày
                        for ($i = 0; $i < $count && $picked < $quota; $i++) {
                            $candidate = $pool[($dayRotation + $i) % $count];
                            if (isset($assignedInShift[$candidate->id]) || isset($usedToday[$candidate->id])) {
                                continue;
                            }
                            $this->createAssignment($shift, $candidate, $position);
                            $assignedInShift[$candidate->id] = true;
                            $usedToday[$candidate->id] = true;
                            $picked++;
                        }

                        // Vòng 2: fallback từ toàn bộ nhân viên active chưa làm trong ngày hoặc chưa trong ca
                        if ($picked < $quota && $activeStaff->isNotEmpty()) {
                            $allCount = $activeStaff->count();
                            for ($i = 0; $i < $allCount && $picked < $quota; $i++) {
                                $candidate = $activeStaff[($dayRotation + $i) % $allCount];
                                if (isset($assignedInShift[$candidate->id]) || isset($usedToday[$candidate->id])) {
                                    continue;
                                }
                                $this->createAssignment($shift, $candidate, $position);
                                $assignedInShift[$candidate->id] = true;
                                $usedToday[$candidate->id] = true;
                                $picked++;
                            }

                            for ($i = 0; $i < $allCount && $picked < $quota; $i++) {
                                $candidate = $activeStaff[($dayRotation + $i) % $allCount];
                                if (isset($assignedInShift[$candidate->id])) {
                                    continue;
                                }
                                $this->createAssignment($shift, $candidate, $position);
                                $assignedInShift[$candidate->id] = true;
                                $picked++;
                            }
                        }
                    }
                }
            }
        });
    }

    private function createAssignment(WorkShift $shift, User $staff, string $position): void
    {
        WorkShiftAssignment::firstOrCreate(
            [
                'work_shift_id' => $shift->id,
                'user_id' => $staff->id,
            ],
            [
                'position' => $position,
                'attendance_status' => 'pending',
                'is_violation' => false,
            ]
        );
    }

    /**
     * Đồng bộ trạng thái ca làm việc và tự động ghi nhận vắng mặt nếu quá 15p chưa check-in.
     */
    public function syncStatusesAndAbsences(?CarbonImmutable $now = null): void
    {
        $nowStorage = ($now ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(config('app.timezone'));
        $absentCutoff = $nowStorage->subMinutes(self::ABSENT_GRACE_MINUTES);

        WorkShift::query()
            ->where('status', '!=', 'cancelled')
            ->where('ends_at', '<=', $nowStorage)
            ->where('status', '!=', 'completed')
            ->update(['status' => 'completed']);

        WorkShift::query()
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<=', $nowStorage)
            ->where('ends_at', '>', $nowStorage)
            ->where('status', '!=', 'in_progress')
            ->update(['status' => 'in_progress']);

        WorkShift::query()
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '>', $nowStorage)
            ->where('status', '!=', 'scheduled')
            ->update(['status' => 'scheduled']);

        $overduePending = WorkShiftAssignment::query()
            ->whereNull('check_in_at')
            ->where('attendance_status', 'pending')
            ->whereHas('shift', function (Builder $query) use ($absentCutoff) {
                $query->where('status', '!=', 'cancelled')
                    ->where('starts_at', '<=', $absentCutoff);
            })
            ->get();

        foreach ($overduePending as $assignment) {
            $assignment->update([
                'attendance_status' => 'absent',
                'is_violation' => true,
                'violation_reason' => 'Không đi làm / Vắng mặt không chấm công',
            ]);
        }
    }

    /**
     * Lấy dữ liệu lịch tuần (7 ngày x 3 ca) phục vụ cả Admin (Lịch tổng) và Staff (Lịch làm).
     */
    public function getWeeklySchedule(CarbonImmutable $referenceDate, ?int $onlyUserId = null, ?int $shiftNumberFilter = null, bool $ensureShifts = true): array
    {
        $refVn = $referenceDate->setTimezone(self::TIMEZONE);
        $weekStartVn = $refVn->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $weekEndVn = $weekStartVn->addDays(6)->endOfDay();

        if ($ensureShifts && User::query()->where('role', 'staff')->where('account_status', 'active')->exists()) {
            $this->ensureRangeShifts($weekStartVn, $weekEndVn);
        }

        $this->syncStatusesAndAbsences();

        $days = [];
        $dayNames = [
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            7 => 'Chủ nhật',
        ];
        $todayVnStr = CarbonImmutable::now(self::TIMEZONE)->format('Y-m-d');

        for ($i = 0; $i < 7; $i++) {
            $d = $weekStartVn->addDays($i);
            $dateStr = $d->format('Y-m-d');
            $days[] = [
                'date' => $dateStr,
                'display_date' => $d->format('d/m/Y'),
                'day_label' => $dayNames[(int) $d->format('N')],
                'is_today' => $dateStr === $todayVnStr,
            ];
        }

        $shiftsQuery = WorkShift::query()
            ->whereBetween('shift_date', [$weekStartVn->format('Y-m-d'), $weekEndVn->format('Y-m-d')])
            ->when($shiftNumberFilter !== null, fn (Builder $q) => $q->where('shift_number', $shiftNumberFilter))
            ->with(['assignments' => function ($query) {
                $query->with('user:id,name,email,employee_code,staff_position,phone,account_status')
                    ->orderByRaw("CASE position WHEN 'ticket_checker' THEN 1 WHEN 'cashier' THEN 2 WHEN 'snack_prep' THEN 3 ELSE 4 END")
                    ->orderBy('id');
            }])
            ->orderBy('shift_date')
            ->orderBy('shift_number');

        $shifts = $shiftsQuery->get();

        // Tính doanh thu online trong khoảng thời gian của từng ca trong 1 truy vấn
        $onlineOrders = collect();
        if ($shifts->isNotEmpty()) {
            $minStart = $shifts->min('starts_at');
            $maxEnd = $shifts->max('ends_at');
            $onlineOrders = Order::query()
                ->whereIn('status', ['paid', 'completed'])
                ->where('created_at', '>=', $minStart)
                ->where('created_at', '<', $maxEnd)
                ->get(['id', 'total_amount', 'created_at']);
        }

        $matrix = [
            1 => [],
            2 => [],
            3 => [],
        ];

        $weekTotalRevenue = 0.0;
        $weekViolations = 0;

        foreach ($shifts as $shift) {
            $assignments = $shift->assignments;

            if ($onlyUserId !== null) {
                $userAssignments = $assignments->where('user_id', $onlyUserId)->values();
                if ($userAssignments->isEmpty()) {
                    continue;
                }
            } else {
                $userAssignments = $assignments;
            }

            $shiftOrders = $onlineOrders->filter(
                fn (Order $order) => $order->created_at >= $shift->starts_at && $order->created_at < $shift->ends_at
            );
            $onlineRevenue = (float) $shiftOrders->sum('total_amount');
            $counterRevenue = (float) $shift->counter_revenue;
            $totalRevenue = $counterRevenue + $onlineRevenue;

            if ($onlyUserId === null) {
                $weekTotalRevenue += $totalRevenue;
            }

            $violations = $userAssignments->where('is_violation', true)->values();
            $absentCount = $userAssignments->where('attendance_status', 'absent')->count();
            $weekViolations += $violations->count();

            $dateKey = $shift->shift_date->format('Y-m-d');
            $startsVn = $shift->starts_at->copy()->setTimezone(self::TIMEZONE);
            $endsVn = $shift->ends_at->copy()->setTimezone(self::TIMEZONE);

            $matrix[$shift->shift_number][$dateKey] = [
                'id' => $shift->id,
                'shift_date' => $dateKey,
                'display_date' => $shift->shift_date->format('d/m/Y'),
                'shift_number' => $shift->shift_number,
                'name' => $shift->name,
                'time_label' => $startsVn->format('H:i').' - '.$endsVn->format('H:i'),
                'starts_at_iso' => $startsVn->toIso8601String(),
                'ends_at_iso' => $endsVn->toIso8601String(),
                'status' => $shift->status,
                'status_label' => $this->shiftStatusLabel($shift->status),
                'counter_revenue' => $counterRevenue,
                'online_revenue' => $onlineRevenue,
                'total_revenue' => $totalRevenue,
                'online_orders_count' => $shiftOrders->count(),
                'has_violation' => $violations->isNotEmpty(),
                'violation_count' => $violations->count(),
                'absent_count' => $absentCount,
                'total_staff' => $assignments->count(),
                'checked_in_count' => $assignments->whereNotNull('check_in_at')->count(),
                'assignments' => $userAssignments->map(fn (WorkShiftAssignment $a) => $this->serializeAssignment($a))->values()->all(),
                'all_assignments_count' => $assignments->count(),
            ];
        }

        $shiftRows = [];
        foreach (WorkShift::DEFINITIONS as $num => $def) {
            if ($shiftNumberFilter !== null && $shiftNumberFilter !== $num) {
                continue;
            }
            $shiftRows[] = [
                'shift_number' => $num,
                'short_label' => $def['short_label'],
                'name' => $def['name'],
                'time_label' => $def['time_label'],
            ];
        }

        return [
            'week_start' => $weekStartVn->format('Y-m-d'),
            'week_end' => $weekEndVn->format('Y-m-d'),
            'reference_date' => $refVn->format('Y-m-d'),
            'prev_week_date' => $weekStartVn->subWeek()->format('Y-m-d'),
            'next_week_date' => $weekStartVn->addWeek()->format('Y-m-d'),
            'today_date' => $todayVnStr,
            'days' => $days,
            'shift_rows' => $shiftRows,
            'matrix' => $matrix,
            'summary' => [
                'week_total_revenue' => $weekTotalRevenue,
                'week_violations' => $weekViolations,
            ],
            'unacknowledged_alerts' => $onlyUserId === null ? $this->getUnacknowledgedAlerts() : [],
        ];
    }

    /**
     * Lấy danh sách cảnh báo vi phạm / vắng mặt chưa xác nhận cho popup Admin.
     */
    public function getUnacknowledgedAlerts(): array
    {
        $this->syncStatusesAndAbsences();

        return WorkShiftAssignment::query()
            ->where('is_violation', true)
            ->whereNull('admin_alert_acknowledged_at')
            ->with(['shift', 'user:id,name,email,employee_code,phone,staff_position'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (WorkShiftAssignment $a) {
                $startsVn = $a->shift?->starts_at?->copy()->setTimezone(self::TIMEZONE);
                $endsVn = $a->shift?->ends_at?->copy()->setTimezone(self::TIMEZONE);

                return [
                    'assignment_id' => $a->id,
                    'user_id' => $a->user_id,
                    'employee_code' => $a->user?->employee_code,
                    'employee_name' => $a->user?->name,
                    'phone' => $a->user?->phone,
                    'position' => $a->position,
                    'position_label' => $a->positionLabel(),
                    'shift_id' => $a->work_shift_id,
                    'shift_name' => $a->shift?->name,
                    'shift_date' => $a->shift?->shift_date?->format('d/m/Y'),
                    'shift_time' => $startsVn && $endsVn ? $startsVn->format('H:i').' - '.$endsVn->format('H:i') : '',
                    'attendance_status' => $a->attendance_status,
                    'status_label' => $this->attendanceStatusLabel($a->attendance_status),
                    'violation_reason' => $a->violation_reason,
                    'late_minutes' => $a->late_minutes,
                    'early_leave_minutes' => $a->early_leave_minutes,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Chấm công bắt đầu ca. Quy định: phải chấm công trước 10 phút khi ca bắt đầu.
     */
    public function checkIn(
        WorkShiftAssignment $assignment,
        string $method = 'manual',
        ?float $confidence = null,
        ?CarbonImmutable $now = null
    ): WorkShiftAssignment {
        $assignment->loadMissing('shift');
        $shift = $assignment->shift;
        $nowStorage = ($now ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(config('app.timezone'));

        if ($assignment->check_in_at !== null) {
            throw ValidationException::withMessages([
                'check_in' => ['Ca làm này đã được chấm công bắt đầu trước đó.'],
            ]);
        }

        if ($nowStorage->gt($shift->ends_at)) {
            throw ValidationException::withMessages([
                'check_in' => ['Ca làm việc đã kết thúc, không thể chấm công vào ca.'],
            ]);
        }

        $deadline = CarbonImmutable::instance($shift->starts_at)->subMinutes(self::CHECK_IN_EARLY_REQUIRED_MINUTES);
        $isLate = $nowStorage->gt($deadline);
        $lateMinutes = $isLate ? (int) max(1, ceil($deadline->diffInSeconds($nowStorage) / 60)) : 0;

        $assignment->fill([
            'check_in_at' => $nowStorage,
            'check_in_method' => $method,
            'check_in_confidence' => $confidence,
            'late_minutes' => $lateMinutes,
            'attendance_status' => $isLate ? 'late' : 'on_time',
            'is_violation' => $isLate,
            'violation_reason' => $isLate
                ? "Chấm công vào ca trễ {$lateMinutes} phút — quy định phải trước 10 phút khi ca bắt đầu"
                : null,
            'admin_alert_acknowledged_at' => $isLate ? null : $assignment->admin_alert_acknowledged_at,
        ])->save();

        return $assignment->fresh(['shift', 'user']);
    }

    /**
     * Chấm công kết thúc ca. Quy định: không được chấm công sớm hơn giờ kết thúc ca.
     */
    public function checkOut(
        WorkShiftAssignment $assignment,
        string $method = 'manual',
        ?float $confidence = null,
        bool $confirmEarly = false,
        ?CarbonImmutable $now = null
    ): WorkShiftAssignment {
        $assignment->loadMissing('shift');
        $shift = $assignment->shift;
        $nowStorage = ($now ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(config('app.timezone'));

        if ($assignment->check_in_at === null) {
            throw ValidationException::withMessages([
                'check_out' => ['Bạn chưa chấm công bắt đầu ca nên không thể chấm công kết thúc ca.'],
            ]);
        }

        if ($assignment->check_out_at !== null) {
            throw ValidationException::withMessages([
                'check_out' => ['Ca làm này đã được chấm công kết thúc trước đó.'],
            ]);
        }

        $endsAt = CarbonImmutable::instance($shift->ends_at);
        $isEarly = $nowStorage->lt($endsAt);

        if ($isEarly && ! $confirmEarly) {
            $endTimeVn = $endsAt->setTimezone(self::TIMEZONE)->format('H:i d/m/Y');
            throw ValidationException::withMessages([
                'early_checkout' => [
                    "Không được chấm công kết thúc ca sớm hơn giờ kết thúc quy định lúc {$endTimeVn}. Nếu tiếp tục xác nhận ra ca sớm, hệ thống sẽ ghi nhận vi phạm gửi Admin!",
                ],
            ]);
        }

        $earlyMinutes = $isEarly ? (int) max(1, ceil($nowStorage->diffInSeconds($endsAt) / 60)) : 0;
        $checkInAt = CarbonImmutable::instance($assignment->check_in_at);
        $workedMinutes = $nowStorage->gt($checkInAt) ? (int) $checkInAt->diffInMinutes($nowStorage) : 0;

        $wasLate = $assignment->late_minutes > 0;
        $status = match (true) {
            $wasLate && $isEarly => 'late_and_early_leave',
            $isEarly => 'early_leave',
            $wasLate => 'late',
            default => 'completed',
        };

        $reasons = array_filter([
            $assignment->violation_reason,
            $isEarly ? "Chấm công ra ca sớm {$earlyMinutes} phút trước giờ kết thúc ca" : null,
        ]);

        $isViolation = $wasLate || $isEarly;

        $assignment->fill([
            'check_out_at' => $nowStorage,
            'check_out_method' => $method,
            'check_in_confidence' => $assignment->check_in_confidence ?? $confidence,
            'early_leave_minutes' => $earlyMinutes,
            'worked_minutes' => $workedMinutes,
            'attendance_status' => $status,
            'is_violation' => $isViolation,
            'violation_reason' => $reasons ? implode(' | ', array_unique($reasons)) : null,
            'admin_alert_acknowledged_at' => $isViolation ? null : $assignment->admin_alert_acknowledged_at,
        ])->save();

        return $assignment->fresh(['shift', 'user']);
    }

    /**
     * Xây dựng query bảng chấm công có filter theo tuần / tháng / năm / ngày / tên nhân viên / trạng thái.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildAttendanceQuery(array $filters, ?int $onlyUserId = null): Builder
    {
        $this->syncStatusesAndAbsences();

        $query = WorkShiftAssignment::query()
            ->select('work_shift_assignments.*')
            ->join('work_shifts', 'work_shifts.id', '=', 'work_shift_assignments.work_shift_id')
            ->join('users', 'users.id', '=', 'work_shift_assignments.user_id')
            ->with(['shift', 'user:id,name,email,employee_code,staff_position,hourly_rate,phone']);

        if ($onlyUserId !== null) {
            $query->where('work_shift_assignments.user_id', $onlyUserId);
        } elseif (! empty($filters['user_id'])) {
            $query->where('work_shift_assignments.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.employee_code', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['shift_number'])) {
            $query->where('work_shifts.shift_number', (int) $filters['shift_number']);
        }

        $status = $filters['status'] ?? 'all';
        if ($status !== 'all' && $status !== '') {
            if ($status === 'violation') {
                $query->where('work_shift_assignments.is_violation', true);
            } elseif ($status === 'valid') {
                $query->whereIn('work_shift_assignments.attendance_status', ['on_time', 'completed']);
            } else {
                $query->where('work_shift_assignments.attendance_status', $status);
            }
        }

        [$fromDate, $toDate] = $this->resolvePeriodBounds($filters);
        if ($fromDate !== null && $toDate !== null) {
            $query->whereBetween('work_shifts.shift_date', [$fromDate, $toDate]);
        }

        return $query->orderByDesc('work_shifts.shift_date')
            ->orderByDesc('work_shifts.shift_number')
            ->orderBy('users.employee_code');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: ?string, 1: ?string}
     */
    public function resolvePeriodBounds(array $filters): array
    {
        $periodType = $filters['period_type'] ?? 'month';
        $nowVn = CarbonImmutable::now(self::TIMEZONE);

        if ($periodType === 'all') {
            return [null, null];
        }

        if ($periodType === 'day') {
            $date = ! empty($filters['date'])
                ? CarbonImmutable::parse((string) $filters['date'], self::TIMEZONE)
                : $nowVn;

            return [$date->format('Y-m-d'), $date->format('Y-m-d')];
        }

        if ($periodType === 'week') {
            if (! empty($filters['week']) && preg_match('/^(\d{4})-W(\d{2})$/', (string) $filters['week'], $matches)) {
                $anchor = CarbonImmutable::now(self::TIMEZONE)->setISODate((int) $matches[1], (int) $matches[2]);
            } elseif (! empty($filters['date'])) {
                $anchor = CarbonImmutable::parse((string) $filters['date'], self::TIMEZONE);
            } else {
                $anchor = $nowVn;
            }

            $start = $anchor->startOfWeek(CarbonImmutable::MONDAY);
            $end = $start->addDays(6);

            return [$start->format('Y-m-d'), $end->format('Y-m-d')];
        }

        if ($periodType === 'year') {
            $year = ! empty($filters['year']) ? (int) $filters['year'] : (int) $nowVn->format('Y');
            $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, self::TIMEZONE);
            $end = $start->endOfYear();

            return [$start->format('Y-m-d'), $end->format('Y-m-d')];
        }

        // Default: 'month'
        $monthStr = ! empty($filters['month']) ? (string) $filters['month'] : $nowVn->format('Y-m');
        if (preg_match('/^(\d{4})-(\d{2})$/', $monthStr, $matches)) {
            $start = CarbonImmutable::create((int) $matches[1], (int) $matches[2], 1, 0, 0, 0, self::TIMEZONE);
        } else {
            $start = $nowVn->startOfMonth();
        }

        return [$start->format('Y-m-d'), $start->endOfMonth()->format('Y-m-d')];
    }

    /**
     * Tính bảng lương chi tiết cho một nhân viên dựa trên các bản ghi chấm công đã lọc.
     */
    public function calculatePayroll(Collection $assignments, User $staff): array
    {
        $hourlyRate = (int) ($staff->hourly_rate ?: 35000);
        $rows = [];

        $completedShifts = 0;
        $inProgressShifts = 0;
        $violationCount = 0;
        $absentCount = 0;
        $totalHours = 0.0;
        $totalBasePay = 0;
        $totalNightBonus = 0;
        $totalPenalty = 0;
        $totalNetSalary = 0;

        foreach ($assignments as $assignment) {
            $shift = $assignment->shift;
            $hasCheckedIn = $assignment->check_in_at !== null;
            $hasCheckedOut = $assignment->check_out_at !== null;
            $isAbsent = $assignment->attendance_status === 'absent';

            if ($isAbsent) {
                $absentCount++;
            }
            if ($assignment->is_violation) {
                $violationCount++;
            }

            $billableHours = 0.0;
            $basePay = 0;
            $nightBonus = 0;
            $penalty = 0;
            $netPay = 0;

            if ($hasCheckedIn && $hasCheckedOut && ! $isAbsent) {
                $completedShifts++;
                $minutes = (int) $assignment->worked_minutes;
                if ($minutes <= 0 && $assignment->check_in_at && $assignment->check_out_at) {
                    $minutes = (int) max(0, $assignment->check_in_at->diffInMinutes($assignment->check_out_at));
                }
                $billableHours = round(min($minutes, 480) / 60, 2);
                $basePay = (int) round($billableHours * $hourlyRate);
                $nightBonus = $shift?->shift_number === 1
                    ? (int) round($basePay * self::NIGHT_SHIFT_BONUS_RATE)
                    : 0;
                $penalty = $assignment->is_violation ? self::VIOLATION_PENALTY_VND : 0;
                $netPay = max(0, $basePay + $nightBonus - $penalty);
            } elseif ($hasCheckedIn && ! $hasCheckedOut && ! $isAbsent) {
                $inProgressShifts++;
            }

            $totalHours += $billableHours;
            $totalBasePay += $basePay;
            $totalNightBonus += $nightBonus;
            $totalPenalty += $penalty;
            $totalNetSalary += $netPay;

            $serialized = $this->serializeAssignment($assignment);
            $serialized['hourly_rate'] = $hourlyRate;
            $serialized['billable_hours'] = $billableHours;
            $serialized['base_pay'] = $basePay;
            $serialized['night_bonus'] = $nightBonus;
            $serialized['penalty'] = $penalty;
            $serialized['net_pay'] = $netPay;

            $rows[] = $serialized;
        }

        return [
            'staff' => [
                'id' => $staff->id,
                'employee_code' => $staff->employee_code,
                'name' => $staff->name,
                'email' => $staff->email,
                'position' => $staff->staff_position,
                'position_label' => $staff->positionLabel(),
                'hourly_rate' => $hourlyRate,
            ],
            'summary' => [
                'total_shifts' => count($rows),
                'completed_shifts' => $completedShifts,
                'in_progress_shifts' => $inProgressShifts,
                'violation_count' => $violationCount,
                'absent_count' => $absentCount,
                'total_hours' => round($totalHours, 2),
                'hourly_rate' => $hourlyRate,
                'total_base_pay' => $totalBasePay,
                'total_night_bonus' => $totalNightBonus,
                'total_penalty' => $totalPenalty,
                'total_net_salary' => $totalNetSalary,
                'night_bonus_percent' => (int) (self::NIGHT_SHIFT_BONUS_RATE * 100),
                'violation_penalty_vnd' => self::VIOLATION_PENALTY_VND,
            ],
            'rows' => $rows,
        ];
    }

    public function serializeAssignment(WorkShiftAssignment $a): array
    {
        $shift = $a->shift;
        $startsVn = $shift?->starts_at?->copy()->setTimezone(self::TIMEZONE);
        $endsVn = $shift?->ends_at?->copy()->setTimezone(self::TIMEZONE);
        $deadlineVn = $startsVn?->subMinutes(self::CHECK_IN_EARLY_REQUIRED_MINUTES);
        $checkInVn = $a->check_in_at?->copy()->setTimezone(self::TIMEZONE);
        $checkOutVn = $a->check_out_at?->copy()->setTimezone(self::TIMEZONE);

        return [
            'id' => $a->id,
            'work_shift_id' => $a->work_shift_id,
            'user_id' => $a->user_id,
            'employee_code' => $a->user?->employee_code ?? ('NV'.str_pad((string) $a->user_id, 3, '0', STR_PAD_LEFT)),
            'employee_name' => $a->user?->name ?? 'Nhân viên',
            'employee_email' => $a->user?->email,
            'employee_phone' => $a->user?->phone,
            'position' => $a->position,
            'position_label' => $a->positionLabel(),
            'shift_date' => $shift?->shift_date?->format('Y-m-d'),
            'shift_display_date' => $shift?->shift_date?->format('d/m/Y'),
            'shift_number' => $shift?->shift_number,
            'shift_name' => $shift?->name,
            'shift_time' => $startsVn && $endsVn ? $startsVn->format('H:i').' - '.$endsVn->format('H:i') : '',
            'shift_status' => $shift?->status,
            'starts_at_iso' => $startsVn?->toIso8601String(),
            'ends_at_iso' => $endsVn?->toIso8601String(),
            'check_in_deadline_display' => $deadlineVn?->format('H:i d/m/Y'),
            'check_out_allowed_display' => $endsVn?->format('H:i d/m/Y'),
            'check_in_at' => $checkInVn?->toIso8601String(),
            'check_in_display' => $checkInVn?->format('H:i:s d/m/Y'),
            'check_in_short' => $checkInVn?->format('H:i'),
            'check_out_at' => $checkOutVn?->toIso8601String(),
            'check_out_display' => $checkOutVn?->format('H:i:s d/m/Y'),
            'check_out_short' => $checkOutVn?->format('H:i'),
            'check_in_method' => $a->check_in_method,
            'check_out_method' => $a->check_out_method,
            'check_in_confidence' => $a->check_in_confidence !== null ? (float) $a->check_in_confidence : null,
            'attendance_status' => $a->attendance_status,
            'attendance_status_label' => $this->attendanceStatusLabel($a->attendance_status),
            'is_violation' => (bool) $a->is_violation,
            'violation_reason' => $a->violation_reason,
            'late_minutes' => (int) $a->late_minutes,
            'early_leave_minutes' => (int) $a->early_leave_minutes,
            'worked_minutes' => (int) $a->worked_minutes,
            'worked_hours' => round(((int) $a->worked_minutes) / 60, 2),
        ];
    }

    public function shiftStatusLabel(?string $status): string
    {
        return match ($status) {
            'in_progress' => 'Đang làm việc',
            'completed' => 'Đã hoàn thành',
            'cancelled' => 'Tạm ngưng',
            default => 'Sắp diễn ra',
        };
    }

    public function attendanceStatusLabel(?string $status): string
    {
        return match ($status) {
            'on_time' => 'Đúng giờ',
            'completed' => 'Hoàn thành',
            'late' => 'Đi trễ',
            'early_leave' => 'Về sớm',
            'late_and_early_leave' => 'Đi trễ & Về sớm',
            'absent' => 'Vắng mặt',
            default => 'Chờ chấm công',
        };
    }
}
