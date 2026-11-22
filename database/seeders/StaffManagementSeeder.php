<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use App\Services\StaffShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffManagementSeeder extends Seeder
{
    public function run(StaffShiftService $shiftService): void
    {
        $nowVn = CarbonImmutable::now(StaffShiftService::TIMEZONE);

        $staffProfiles = [
            [
                'employee_code' => 'NV001',
                'name' => 'Nguyễn Văn An',
                'email' => 'staff@cinema.com',
                'plain_password' => 'password',
                'phone' => '0901234001',
                'date_of_birth' => '1999-04-12',
                'gender' => 'male',
                'citizen_id' => '079099001001',
                'address' => '140 Lê Trọng Tấn, Tân Phú, TP.HCM',
                'staff_position' => 'ticket_checker',
                'hourly_rate' => 38000,
                'account_status' => 'active',
                'hired_at' => '2025-03-01',
            ],
            [
                'employee_code' => 'NV002',
                'name' => 'Trần Thị Bảo Ngọc',
                'email' => 'nv002@cinema.com',
                'phone' => '0901234002',
                'date_of_birth' => '2001-08-22',
                'gender' => 'female',
                'citizen_id' => '079201001002',
                'address' => '25 Tây Thạnh, Tân Phú, TP.HCM',
                'staff_position' => 'ticket_checker',
                'hourly_rate' => 36000,
                'account_status' => 'active',
                'hired_at' => '2025-05-15',
            ],
            [
                'employee_code' => 'NV003',
                'name' => 'Lê Hoàng Minh',
                'email' => 'nv003@cinema.com',
                'phone' => '0901234003',
                'date_of_birth' => '2000-11-05',
                'gender' => 'male',
                'citizen_id' => '079200001003',
                'address' => '88 Trường Chinh, Tân Bình, TP.HCM',
                'staff_position' => 'ticket_checker',
                'hourly_rate' => 36000,
                'account_status' => 'active',
                'hired_at' => '2025-06-10',
            ],
            [
                'employee_code' => 'NV004',
                'name' => 'Phạm Thu Hà',
                'email' => 'nv004@cinema.com',
                'phone' => '0901234004',
                'date_of_birth' => '2002-01-19',
                'gender' => 'female',
                'citizen_id' => '079202001004',
                'address' => '12 Cộng Hòa, Tân Bình, TP.HCM',
                'staff_position' => 'ticket_checker',
                'hourly_rate' => 35000,
                'account_status' => 'active',
                'hired_at' => '2025-08-01',
            ],
            [
                'employee_code' => 'NV005',
                'name' => 'Võ Quốc Đạt',
                'email' => 'nv005@cinema.com',
                'phone' => '0901234005',
                'date_of_birth' => '1998-09-30',
                'gender' => 'male',
                'citizen_id' => '079098001005',
                'address' => '310 Lũy Bán Bích, Tân Phú, TP.HCM',
                'staff_position' => 'ticket_checker',
                'hourly_rate' => 37000,
                'account_status' => 'active',
                'hired_at' => '2025-02-18',
            ],
            [
                'employee_code' => 'NV006',
                'name' => 'Đặng Khánh Linh',
                'email' => 'nv006@cinema.com',
                'phone' => '0901234006',
                'date_of_birth' => '2001-12-03',
                'gender' => 'female',
                'citizen_id' => '079201001006',
                'address' => '45 Hoàng Văn Thụ, Phú Nhuận, TP.HCM',
                'staff_position' => 'ticket_checker',
                'hourly_rate' => 35000,
                'account_status' => 'active',
                'hired_at' => '2025-09-01',
            ],
            [
                'employee_code' => 'NV007',
                'name' => 'Nguyễn Thành Trung',
                'email' => 'nv007@cinema.com',
                'phone' => '0901234007',
                'date_of_birth' => '1997-06-14',
                'gender' => 'male',
                'citizen_id' => '079097001007',
                'address' => '72 Nguyễn Văn Quá, Quận 12, TP.HCM',
                'staff_position' => 'cashier',
                'hourly_rate' => 42000,
                'account_status' => 'active',
                'hired_at' => '2024-11-20',
            ],
            [
                'employee_code' => 'NV008',
                'name' => 'Bùi Thanh Thảo',
                'email' => 'nv008@cinema.com',
                'phone' => '0901234008',
                'date_of_birth' => '2000-03-27',
                'gender' => 'female',
                'citizen_id' => '079200001008',
                'address' => '190 Quang Trung, Gò Vấp, TP.HCM',
                'staff_position' => 'cashier',
                'hourly_rate' => 40000,
                'account_status' => 'active',
                'hired_at' => '2025-01-12',
            ],
            [
                'employee_code' => 'NV009',
                'name' => 'Huỳnh Gia Bảo',
                'email' => 'nv009@cinema.com',
                'phone' => '0901234009',
                'date_of_birth' => '1999-10-08',
                'gender' => 'male',
                'citizen_id' => '079099001009',
                'address' => '54 Cách Mạng Tháng 8, Quận 3, TP.HCM',
                'staff_position' => 'cashier',
                'hourly_rate' => 40000,
                'account_status' => 'active',
                'hired_at' => '2025-04-05',
            ],
            [
                'employee_code' => 'NV010',
                'name' => 'Đỗ Mỹ Duyên',
                'email' => 'nv010@cinema.com',
                'phone' => '0901234010',
                'date_of_birth' => '2001-05-11',
                'gender' => 'female',
                'citizen_id' => '079201001010',
                'address' => '220 Phan Huy Ích, Gò Vấp, TP.HCM',
                'staff_position' => 'cashier',
                'hourly_rate' => 39000,
                'account_status' => 'active',
                'hired_at' => '2025-07-01',
            ],
            [
                'employee_code' => 'NV011',
                'name' => 'Phan Tuấn Kiệt',
                'email' => 'nv011@cinema.com',
                'phone' => '0901234011',
                'date_of_birth' => '1998-02-24',
                'gender' => 'male',
                'citizen_id' => '079098001011',
                'address' => '98 Tân Kỳ Tân Quý, Tân Phú, TP.HCM',
                'staff_position' => 'cashier',
                'hourly_rate' => 41000,
                'account_status' => 'active',
                'hired_at' => '2025-03-18',
            ],
            [
                'employee_code' => 'NV012',
                'name' => 'Ngô Phương Anh',
                'email' => 'nv012@cinema.com',
                'phone' => '0901234012',
                'date_of_birth' => '2002-07-09',
                'gender' => 'female',
                'citizen_id' => '079202001012',
                'address' => '15 Võ Oanh, Bình Thạnh, TP.HCM',
                'staff_position' => 'cashier',
                'hourly_rate' => 38000,
                'account_status' => 'active',
                'hired_at' => '2025-10-01',
            ],
            [
                'employee_code' => 'NV013',
                'name' => 'Lý Đức Huy',
                'email' => 'nv013@cinema.com',
                'phone' => '0901234013',
                'date_of_birth' => '2000-09-16',
                'gender' => 'male',
                'citizen_id' => '079200001013',
                'address' => '310 Hòa Bình, Tân Phú, TP.HCM',
                'staff_position' => 'snack_prep',
                'hourly_rate' => 36000,
                'account_status' => 'active',
                'hired_at' => '2025-02-02',
            ],
            [
                'employee_code' => 'NV014',
                'name' => 'Trương Ngọc Ánh',
                'email' => 'nv014@cinema.com',
                'phone' => '0901234014',
                'date_of_birth' => '2001-11-28',
                'gender' => 'female',
                'citizen_id' => '079201001014',
                'address' => '64 Bà Hom, Quận 6, TP.HCM',
                'staff_position' => 'snack_prep',
                'hourly_rate' => 36000,
                'account_status' => 'active',
                'hired_at' => '2025-05-09',
            ],
            [
                'employee_code' => 'NV015',
                'name' => 'Hoàng Phúc Thịnh',
                'email' => 'nv015@cinema.com',
                'phone' => '0901234015',
                'date_of_birth' => '1999-07-17',
                'gender' => 'male',
                'citizen_id' => '079099001015',
                'address' => '108 Âu Cơ, Tân Bình, TP.HCM',
                'staff_position' => 'snack_prep',
                'hourly_rate' => 37000,
                'account_status' => 'active',
                'hired_at' => '2025-04-21',
            ],
            [
                'employee_code' => 'NV016',
                'name' => 'Vũ Hải Yến',
                'email' => 'nv016@cinema.com',
                'phone' => '0901234016',
                'date_of_birth' => '2000-12-19',
                'gender' => 'female',
                'citizen_id' => '079200001016',
                'address' => '19 Lê Văn Sỹ, Phú Nhuận, TP.HCM',
                'staff_position' => 'cashier',
                'hourly_rate' => 39000,
                'account_status' => 'locked',
                'hired_at' => '2025-01-25',
            ],
        ];

        $staffUsers = [];
        foreach ($staffProfiles as $profile) {
            $plainPassword = $profile['plain_password'] ?? $profile['employee_code'];
            $user = User::updateOrCreate(
                ['email' => $profile['email']],
                [
                    'name' => $profile['name'],
                    'password' => Hash::make($plainPassword),
                    'role' => 'staff',
                    'employee_code' => $profile['employee_code'],
                    'phone' => $profile['phone'],
                    'date_of_birth' => $profile['date_of_birth'],
                    'gender' => $profile['gender'],
                    'citizen_id' => $profile['citizen_id'],
                    'address' => $profile['address'],
                    'staff_position' => $profile['staff_position'],
                    'hourly_rate' => $profile['hourly_rate'],
                    'account_status' => $profile['account_status'],
                    'hired_at' => $profile['hired_at'],
                    'display_password' => $plainPassword,
                ]
            );
            $staffUsers[$profile['employee_code']] = $user;
        }

        // Tạo lịch 29 ngày (-14 ngày đến +14 ngày)
        $shiftService->ensureRangeShifts($nowVn->subDays(14), $nowVn->addDays(14));

        // Đảm bảo NV001 (staff@cinema.com) chắc chắn có ca hôm nay (Ca 3: 14:00 - 22:00) ở trạng thái chờ chấm công để dễ test
        $nv001 = $staffUsers['NV001'];
        $todayStr = $nowVn->format('Y-m-d');
        $todayShift3 = WorkShift::where('shift_date', $todayStr)->where('shift_number', 3)->first();
        if ($todayShift3 && ! $todayShift3->assignments()->where('user_id', $nv001->id)->exists()) {
            // Nếu NV001 đang làm ca khác hôm nay thì chuyển sang Ca 3 hôm nay để bảo đảm không trùng ca trong ngày
            $otherTodayIds = WorkShift::where('shift_date', $todayStr)->where('id', '!=', $todayShift3->id)->pluck('id');
            $swappedOut = WorkShiftAssignment::whereIn('work_shift_id', $otherTodayIds)->where('user_id', $nv001->id)->first();
            $replaceTarget = $todayShift3->assignments()->where('position', 'ticket_checker')->first();
            if ($replaceTarget) {
                $oldUserId = $replaceTarget->user_id;
                $replaceTarget->update([
                    'user_id' => $nv001->id,
                    'check_in_at' => null,
                    'check_out_at' => null,
                    'attendance_status' => 'pending',
                    'is_violation' => false,
                    'violation_reason' => null,
                ]);
                if ($swappedOut) {
                    $swappedOut->update(['user_id' => $oldUserId]);
                }
            }
        }

        $yesterdayStr = $nowVn->subDay()->format('Y-m-d');
        $storageTz = config('app.timezone');
        $nowStorage = $nowVn->setTimezone($storageTz);

        $shifts = WorkShift::with('assignments')->orderBy('shift_date')->orderBy('shift_number')->get();

        foreach ($shifts as $shift) {
            $startsAt = CarbonImmutable::instance($shift->starts_at);
            $endsAt = CarbonImmutable::instance($shift->ends_at);
            $shiftDateStr = $shift->shift_date->format('Y-m-d');

            // Bổ sung doanh thu tại quầy thực tế cho các ca đã diễn ra hoặc đang diễn ra
            if ($startsAt->lte($nowStorage) && (float) $shift->counter_revenue === 0.0) {
                $baseRev = match ($shift->shift_number) {
                    1 => random_int(18, 36) * 100000,
                    2 => random_int(48, 86) * 100000,
                    default => random_int(82, 148) * 100000,
                };
                if ($startsAt->gt($nowStorage->subHours(8))) {
                    $baseRev = (int) round($baseRev * 0.55);
                }
                $shift->update(['counter_revenue' => $baseRev]);
            }

            // Chỉ seed chấm công cho ca chưa từng có chấm công nào
            if ($shift->assignments->every(fn ($a) => $a->check_in_at === null && $a->attendance_status === 'pending')) {
                // Ca đã kết thúc
                if ($endsAt->lte($nowStorage)) {
                    $isYesterdayShift3 = ($shiftDateStr === $yesterdayStr && $shift->shift_number === 3);

                    foreach ($shift->assignments->values() as $idx => $assignment) {
                        $methodIn = ($idx % 2 === 0) ? 'camera_ai' : 'manual';
                        $methodOut = ($idx % 3 === 0) ? 'camera_ai' : 'manual';
                        $confidence = $methodIn === 'camera_ai' ? round(97 + ($idx * 0.4), 2) : null;

                        if ($isYesterdayShift3 && $idx === 4) {
                            // 1 trường hợp về sớm chưa xác nhận ở Ca 3 hôm qua để hiện lên popup cảnh báo
                            $checkIn = $startsAt->subMinutes(12);
                            $checkOut = $endsAt->subMinutes(45);
                            $assignment->update([
                                'check_in_at' => $checkIn,
                                'check_out_at' => $checkOut,
                                'check_in_method' => $methodIn,
                                'check_out_method' => $methodOut,
                                'check_in_confidence' => $confidence,
                                'attendance_status' => 'early_leave',
                                'is_violation' => true,
                                'violation_reason' => 'Chấm công ra ca sớm 45 phút trước giờ kết thúc ca',
                                'early_leave_minutes' => 45,
                                'worked_minutes' => (int) $checkIn->diffInMinutes($checkOut),
                                'admin_alert_acknowledged_at' => null,
                            ]);
                        } else {
                            $checkIn = $startsAt->subMinutes(12 + ($idx % 6));
                            $checkOut = $endsAt->addMinutes(2 + ($idx % 5));
                            $assignment->update([
                                'check_in_at' => $checkIn,
                                'check_out_at' => $checkOut,
                                'check_in_method' => $methodIn,
                                'check_out_method' => $methodOut,
                                'check_in_confidence' => $confidence,
                                'attendance_status' => 'completed',
                                'is_violation' => false,
                                'violation_reason' => null,
                                'worked_minutes' => (int) $checkIn->diffInMinutes($checkOut),
                            ]);
                        }
                    }
                } elseif ($startsAt->lte($nowStorage) && $endsAt->gt($nowStorage)) {
                    // Ca đang diễn ra
                    foreach ($shift->assignments->values() as $idx => $assignment) {
                        if ($assignment->user_id === $nv001->id) {
                            if ($startsAt->gt($nowStorage->subMinutes(StaffShiftService::ABSENT_GRACE_MINUTES))) {
                                continue;
                            }
                            $checkIn = $startsAt->subMinutes(12);
                            $assignment->update([
                                'check_in_at' => $checkIn,
                                'check_in_method' => 'manual',
                                'attendance_status' => 'on_time',
                                'is_violation' => false,
                            ]);
                            continue;
                        }
                        if ($idx === 3) {
                            // 1 nhân viên đi trễ 22p (chấm công lúc 06:12 thay vì <= 05:50) -> popup cảnh báo Admin
                            $checkIn = $startsAt->addMinutes(12);
                            $assignment->update([
                                'check_in_at' => $checkIn,
                                'check_in_method' => 'camera_ai',
                                'check_in_confidence' => 98.40,
                                'attendance_status' => 'late',
                                'is_violation' => true,
                                'violation_reason' => 'Chấm công vào ca trễ 22 phút — quy định phải trước 10 phút khi ca bắt đầu',
                                'late_minutes' => 22,
                                'admin_alert_acknowledged_at' => null,
                            ]);
                        } elseif ($idx === 4) {
                            // 1 nhân viên vắng mặt hôm nay -> popup cảnh báo Admin
                            $assignment->update([
                                'attendance_status' => 'absent',
                                'is_violation' => true,
                                'violation_reason' => 'Không đi làm / Vắng mặt không chấm công',
                                'admin_alert_acknowledged_at' => null,
                            ]);
                        } else {
                            $checkIn = $startsAt->subMinutes(13 + $idx);
                            $assignment->update([
                                'check_in_at' => $checkIn,
                                'check_in_method' => $idx === 0 ? 'camera_ai' : 'manual',
                                'check_in_confidence' => $idx === 0 ? 99.10 : null,
                                'attendance_status' => 'on_time',
                                'is_violation' => false,
                            ]);
                        }
                    }
                }
            }
        }

        $shiftService->syncStatusesAndAbsences($nowVn);
    }
}
