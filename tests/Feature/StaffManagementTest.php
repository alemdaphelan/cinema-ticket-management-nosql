<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;
use App\Services\StaffShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use ZipArchive;

class StaffManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createStaffPool(): array
    {
        $pool = [];
        for ($i = 1; $i <= 6; $i++) {
            $pool[] = User::factory()->create([
                'role' => 'staff',
                'employee_code' => 'NV00'.$i,
                'name' => "Soát vé {$i}",
                'email' => "tc{$i}@cinema.com",
                'staff_position' => 'ticket_checker',
                'hourly_rate' => 36000,
                'account_status' => 'active',
                'display_password' => 'NV00'.$i,
            ]);
        }
        for ($i = 7; $i <= 12; $i++) {
            $code = 'NV'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $pool[] = User::factory()->create([
                'role' => 'staff',
                'employee_code' => $code,
                'name' => "Thu ngân {$i}",
                'email' => "cash{$i}@cinema.com",
                'staff_position' => 'cashier',
                'hourly_rate' => 40000,
                'account_status' => 'active',
                'display_password' => $code,
            ]);
        }
        for ($i = 13; $i <= 15; $i++) {
            $code = 'NV'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $pool[] = User::factory()->create([
                'role' => 'staff',
                'employee_code' => $code,
                'name' => "Snacks {$i}",
                'email' => "snack{$i}@cinema.com",
                'staff_position' => 'snack_prep',
                'hourly_rate' => 35000,
                'account_status' => 'active',
                'display_password' => $code,
            ]);
        }

        return $pool;
    }

    public function test_admin_weekly_schedule_has_three_eight_hour_shifts_five_staff_and_revenue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createStaffPool();
        $customer = User::factory()->create(['role' => 'user']);

        $this->travelTo(CarbonImmutable::parse('2026-11-22 10:00:00', StaffShiftService::TIMEZONE));
        $this->actingAs($admin);

        // Tạo lịch tuần trước thông qua API
        $res = $this->getJson('/api/admin/staff/schedule?date=2026-11-22')
            ->assertOk();

        $data = $res->json('data');
        $this->assertCount(7, $data['days']);
        $this->assertCount(3, $data['shift_rows']);

        // Kiểm tra Ca 2 (06:00 - 14:00) hôm nay có 5 nhân viên: 2 soát vé, 2 thu ngân, 1 chuẩn bị snacks
        $shift2 = $data['matrix'][2]['2026-11-22'];
        $this->assertSame('06:00 - 14:00', $shift2['time_label']);
        $this->assertCount(5, $shift2['assignments']);

        $positions = array_count_values(array_column($shift2['assignments'], 'position'));
        $this->assertSame(2, $positions['ticket_checker'] ?? 0);
        $this->assertSame(2, $positions['cashier'] ?? 0);
        $this->assertSame(1, $positions['snack_prep'] ?? 0);

        // Kiểm tra Ca 1 (22:00 - 06:00) và Ca 3 (14:00 - 22:00) lúc 10:00 ngày 22/11/2026
        $shift1 = $data['matrix'][1]['2026-11-22'];
        $shift3 = $data['matrix'][3]['2026-11-22'];
        $this->assertSame('22:00 - 06:00', $shift1['time_label']);
        $this->assertSame('22/11/2026', $shift1['display_date']);
        $this->assertStringStartsWith('2026-11-21T22:00:00', $shift1['starts_at_iso']);
        $this->assertStringStartsWith('2026-11-22T06:00:00', $shift1['ends_at_iso']);
        $this->assertSame('completed', $shift1['status']);
        $this->assertSame('in_progress', $shift2['status']);
        $this->assertSame('scheduled', $shift3['status']);
        $this->assertSame('14:00 - 22:00', $shift3['time_label']);

        // Bổ sung counter_revenue và 1 đơn hàng paid trong khung giờ Ca 2 để kiểm tra tính doanh thu ca
        $shiftModel = WorkShift::findOrFail($shift2['id']);
        $shiftModel->update(['counter_revenue' => 2500000]);

        $order = Order::create([
            'user_id' => $customer->id,
            'show_id' => null,
            'total_amount' => 350000,
            'status' => 'paid',
            'payment_method' => 'vnpay',
        ]);
        $order->created_at = CarbonImmutable::parse('2026-11-22 09:30:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone'));
        $order->save();

        $updated = $this->getJson('/api/admin/staff/schedule?date=2026-11-22')
            ->assertOk()
            ->json('data.matrix.2.2026-11-22');

        $this->assertEquals(2500000.0, $updated['counter_revenue']);
        $this->assertEquals(350000.0, $updated['online_revenue']);
        $this->assertEquals(2850000.0, $updated['total_revenue']);
        $this->assertSame(1, $updated['online_orders_count']);
    }

    public function test_staff_checkin_and_checkout_rules_and_camera_ai_and_admin_alerts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff1 = User::factory()->create([
            'role' => 'staff',
            'employee_code' => 'NV101',
            'staff_position' => 'ticket_checker',
            'hourly_rate' => 40000,
            'account_status' => 'active',
        ]);
        $staff2 = User::factory()->create([
            'role' => 'staff',
            'employee_code' => 'NV102',
            'staff_position' => 'cashier',
            'hourly_rate' => 40000,
            'account_status' => 'active',
        ]);

        // Tạo Ca 3: 14:00 - 22:00 (giờ VN)
        $startsAt = CarbonImmutable::parse('2026-11-22 14:00:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone'));
        $endsAt = CarbonImmutable::parse('2026-11-22 22:00:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone'));

        $shift = WorkShift::create([
            'shift_date' => '2026-11-22',
            'shift_number' => 3,
            'name' => 'Ca 3',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'scheduled',
        ]);

        $assignOnTime = WorkShiftAssignment::create([
            'work_shift_id' => $shift->id,
            'user_id' => $staff1->id,
            'position' => 'ticket_checker',
        ]);
        $assignLate = WorkShiftAssignment::create([
            'work_shift_id' => $shift->id,
            'user_id' => $staff2->id,
            'position' => 'cashier',
        ]);

        // 1. Staff 1 chấm công bằng Camera AI lúc 13:45 (>= 10 phút trước 14:00) -> đúng giờ
        $this->travelTo(CarbonImmutable::parse('2026-11-22 13:45:00', StaffShiftService::TIMEZONE));
        $this->actingAs($staff1);

        // Không được chấm công hộ nhân viên khác
        $this->postJson('/api/staff/my-attendance/check-in', [
            'assignment_id' => $assignLate->id,
            'method' => 'manual',
        ])->assertForbidden();

        $this->postJson('/api/staff/my-attendance/check-in', [
            'assignment_id' => $assignOnTime->id,
            'method' => 'camera_ai',
            'confidence' => 98.7,
        ])->assertOk()
            ->assertJsonPath('data.attendance_status', 'on_time')
            ->assertJsonPath('data.is_violation', false)
            ->assertJsonPath('data.check_in_method', 'camera_ai');

        // 2. Staff 1 thử kết thúc ca sớm lúc 21:00 -> bị từ chối 422 với early_checkout
        $this->travelTo(CarbonImmutable::parse('2026-11-22 21:00:00', StaffShiftService::TIMEZONE));
        $this->postJson('/api/staff/my-attendance/check-out', [
            'assignment_id' => $assignOnTime->id,
            'method' => 'manual',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('early_checkout');

        // Kết thúc ca đúng giờ lúc 22:05 bằng Camera AI -> hoàn thành hợp lệ
        $this->travelTo(CarbonImmutable::parse('2026-11-22 22:05:00', StaffShiftService::TIMEZONE));
        $this->postJson('/api/staff/my-attendance/check-out', [
            'assignment_id' => $assignOnTime->id,
            'method' => 'camera_ai',
            'confidence' => 99.1,
        ])->assertOk()
            ->assertJsonPath('data.attendance_status', 'completed')
            ->assertJsonPath('data.is_violation', false)
            ->assertJsonPath('data.check_out_method', 'camera_ai');

        // 3. Staff 2 chấm công lúc 13:55 (trễ 5 phút so với hạn 13:50) -> ghi nhận vi phạm
        $this->travelTo(CarbonImmutable::parse('2026-11-22 13:55:00', StaffShiftService::TIMEZONE));
        $this->actingAs($staff2);
        $this->postJson('/api/staff/my-attendance/check-in', [
            'assignment_id' => $assignLate->id,
            'method' => 'manual',
        ])->assertOk()
            ->assertJsonPath('data.attendance_status', 'late')
            ->assertJsonPath('data.is_violation', true)
            ->assertJsonPath('data.late_minutes', 5);

        // Staff 2 bấm kết thúc ca sớm có xác nhận confirm_early -> ghi nhận vi phạm về sớm
        $this->travelTo(CarbonImmutable::parse('2026-11-22 21:30:00', StaffShiftService::TIMEZONE));
        $this->postJson('/api/staff/my-attendance/check-out', [
            'assignment_id' => $assignLate->id,
            'method' => 'manual',
            'confirm_early' => true,
        ])->assertOk()
            ->assertJsonPath('data.attendance_status', 'late_and_early_leave')
            ->assertJsonPath('data.is_violation', true)
            ->assertJsonPath('data.early_leave_minutes', 30);

        // 4. Admin nhận popup cảnh báo và xác nhận đã xem
        $this->actingAs($admin);
        $alerts = $this->getJson('/api/admin/staff/alerts')->assertOk()->json('data');
        $this->assertCount(1, $alerts);
        $this->assertSame('NV102', $alerts[0]['employee_code']);

        $this->postJson('/api/admin/staff/alerts/acknowledge', [
            'assignment_ids' => [$assignLate->id],
        ])->assertOk()
            ->assertJsonPath('acknowledged_count', 1);

        $this->assertCount(0, $this->getJson('/api/admin/staff/alerts')->assertOk()->json('data'));
    }

    public function test_admin_timesheet_filters_and_excel_xlsx_export(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create([
            'role' => 'staff',
            'employee_code' => 'NV201',
            'name' => 'Phạm Minh Tuấn',
            'staff_position' => 'cashier',
            'hourly_rate' => 42000,
            'account_status' => 'active',
        ]);

        $shiftNov = WorkShift::create([
            'shift_date' => '2026-11-20',
            'shift_number' => 2,
            'name' => 'Ca 2',
            'starts_at' => CarbonImmutable::parse('2026-11-20 06:00:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone')),
            'ends_at' => CarbonImmutable::parse('2026-11-20 14:00:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone')),
            'status' => 'completed',
        ]);
        $shiftOct = WorkShift::create([
            'shift_date' => '2026-10-15',
            'shift_number' => 3,
            'name' => 'Ca 3',
            'starts_at' => CarbonImmutable::parse('2026-10-15 14:00:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone')),
            'ends_at' => CarbonImmutable::parse('2026-10-15 22:00:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone')),
            'status' => 'completed',
        ]);

        WorkShiftAssignment::create([
            'work_shift_id' => $shiftNov->id,
            'user_id' => $staff->id,
            'position' => 'cashier',
            'check_in_at' => $shiftNov->starts_at->subMinutes(12),
            'check_out_at' => $shiftNov->ends_at,
            'check_in_method' => 'manual',
            'check_out_method' => 'manual',
            'attendance_status' => 'completed',
            'worked_minutes' => 492,
        ]);

        WorkShiftAssignment::create([
            'work_shift_id' => $shiftOct->id,
            'user_id' => $staff->id,
            'position' => 'cashier',
            'attendance_status' => 'absent',
            'is_violation' => true,
            'violation_reason' => 'Không đi làm',
        ]);

        $this->actingAs($admin);

        // Lọc theo tháng 2026-11 và tìm tên
        $this->getJson('/api/admin/staff/attendance?period_type=month&month=2026-11&search=Minh Tuấn')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('data.0.employee_code', 'NV201');

        // Lọc theo năm 2026 -> thấy cả 2 bản ghi, kiểm tra phân trang per_page=1
        $this->getJson('/api/admin/staff/attendance?period_type=year&year=2026')
            ->assertOk()
            ->assertJsonPath('summary.total', 2);

        $this->getJson('/api/admin/staff/attendance?period_type=year&year=2026&per_page=1&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 1);

        $this->getJson('/api/admin/staff/attendance?period_type=year&year=2026&per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.from', 2)
            ->assertJsonPath('meta.to', 2);

        // Lọc theo ngày cụ thể
        $this->getJson('/api/admin/staff/attendance?period_type=day&date=2026-10-15')
            ->assertOk()
            ->assertJsonPath('summary.absent', 1);

        // Xuất Excel (.xlsx)
        $exportRes = $this->get('/admin/staff/attendance/export?period_type=year&year=2026');
        $exportRes->assertOk();
        $content = $exportRes->streamedContent();
        $this->assertNotEmpty($content);

        if (class_exists(ZipArchive::class)) {
            $this->assertStringStartsWith('PK', $content);
            $tmp = tempnam(sys_get_temp_dir(), 'test_xlsx_');
            file_put_contents($tmp, $content);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($tmp) === true);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            @unlink($tmp);

            $this->assertStringContainsString('NV201', $sheetXml);
            $this->assertStringContainsString('Phạm Minh Tuấn', $sheetXml);
        }
    }

    public function test_admin_staff_accounts_crud_password_reset_and_account_lock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        // Tạo nhân viên mới
        $createRes = $this->postJson('/api/admin/staff/accounts', [
            'employee_code' => 'NV888',
            'name' => 'Lê Thảo Vy',
            'email' => 'nv888@cinema.com',
            'password' => 'customSecret99',
            'phone' => '0909888777',
            'staff_position' => 'snack_prep',
            'hourly_rate' => 37000,
        ])->assertCreated()
            ->assertJsonPath('data.employee_code', 'NV888')
            ->assertJsonPath('data.display_password', 'customSecret99')
            ->assertJsonPath('data.account_status', 'active');

        $staffId = $createRes->json('data.id');

        // Reset mật khẩu về mặc định = mã nhân viên NV888
        $this->postJson("/api/admin/staff/accounts/{$staffId}/reset-password")
            ->assertOk()
            ->assertJsonPath('default_password', 'NV888')
            ->assertJsonPath('data.display_password', 'NV888');

        $staffModel = User::findOrFail($staffId);
        $this->assertTrue(Hash::check('NV888', $staffModel->password));

        // Kiểm tra đăng nhập thành công bằng mật khẩu mặc định mới
        $this->postJson('/api/auth/login', [
            'email' => 'nv888@cinema.com',
            'password' => 'NV888',
        ])->assertOk();

        // Khóa tài khoản và xác nhận không cho đăng nhập
        $this->patchJson("/api/admin/staff/accounts/{$staffId}/status", [
            'account_status' => 'locked',
        ])->assertOk()
            ->assertJsonPath('data.account_status', 'locked');

        $this->postJson('/api/auth/login', [
            'email' => 'nv888@cinema.com',
            'password' => 'NV888',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_staff_workspace_schedule_and_payroll_are_strictly_scoped_and_accurate(): void
    {
        $staffA = User::factory()->create([
            'role' => 'staff',
            'employee_code' => 'NV301',
            'name' => 'Nhân viên A',
            'staff_position' => 'cashier',
            'hourly_rate' => 40000,
            'account_status' => 'active',
        ]);
        $staffB = User::factory()->create([
            'role' => 'staff',
            'employee_code' => 'NV302',
            'name' => 'Nhân viên B',
            'staff_position' => 'ticket_checker',
            'hourly_rate' => 35000,
            'account_status' => 'active',
        ]);

        // Ca 1 (Đêm, +30% phụ cấp): 8 tiếng = 320,000 + 96,000 = 416,000 đ
        $shift1 = WorkShift::create([
            'shift_date' => '2026-11-21',
            'shift_number' => 1,
            'name' => 'Ca 1',
            'starts_at' => CarbonImmutable::parse('2026-11-21 22:00:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone')),
            'ends_at' => CarbonImmutable::parse('2026-11-22 06:00:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone')),
            'status' => 'completed',
        ]);
        // Ca 2 (Sáng) chỉ gán cho Staff B -> Staff A không được thấy
        $shift2 = WorkShift::create([
            'shift_date' => '2026-11-22',
            'shift_number' => 2,
            'name' => 'Ca 2',
            'starts_at' => CarbonImmutable::parse('2026-11-22 06:00:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone')),
            'ends_at' => CarbonImmutable::parse('2026-11-22 14:00:00', StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone')),
            'status' => 'completed',
        ]);

        WorkShiftAssignment::create([
            'work_shift_id' => $shift1->id,
            'user_id' => $staffA->id,
            'position' => 'cashier',
            'check_in_at' => $shift1->starts_at->subMinutes(15),
            'check_out_at' => $shift1->ends_at,
            'attendance_status' => 'completed',
            'worked_minutes' => 495,
        ]);

        WorkShiftAssignment::create([
            'work_shift_id' => $shift2->id,
            'user_id' => $staffB->id,
            'position' => 'ticket_checker',
            'check_in_at' => $shift2->starts_at->subMinutes(10),
            'check_out_at' => $shift2->ends_at,
            'attendance_status' => 'completed',
            'worked_minutes' => 490,
        ]);

        // Staff A không được vào API Admin
        $this->actingAs($staffA);
        $this->getJson('/api/admin/staff/accounts')->assertForbidden();

        // Lịch làm của Staff A chỉ thấy Ca 1 ngày 2026-11-21, không thấy Ca 2 của Staff B
        $scheduleData = $this->getJson('/api/staff/my-schedule?date=2026-11-22')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('2026-11-21', $scheduleData['matrix'][1]);
        $this->assertArrayNotHasKey('2026-11-22', $scheduleData['matrix'][2]);

        // Tính lương của Staff A trong tháng 2026-11
        $payroll = $this->getJson('/api/staff/my-payroll?period_type=month&month=2026-11')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $payroll['summary']['completed_shifts']);
        $this->assertEquals(8.0, $payroll['summary']['total_hours']);
        $this->assertSame(320000, $payroll['summary']['total_base_pay']);
        $this->assertSame(96000, $payroll['summary']['total_night_bonus']);
        $this->assertSame(0, $payroll['summary']['total_penalty']);
        $this->assertSame(416000, $payroll['summary']['total_net_salary']);

        // Xuất Excel bảng chấm công cá nhân của Staff A: chỉ chứa dữ liệu của Staff A
        $exportResponse = $this->get('/staff/payroll/export?period_type=month&month=2026-11');
        $exportResponse->assertOk();
        $content = $exportResponse->streamedContent();
        $this->assertNotEmpty($content);

        if (class_exists(\ZipArchive::class)) {
            $this->assertStringStartsWith('PK', $content);
            $tmp = tempnam(sys_get_temp_dir(), 'staff_xlsx_');
            $this->assertNotFalse($tmp);
            file_put_contents($tmp, $content);

            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($tmp) === true);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            @unlink($tmp);

            $this->assertIsString($sheetXml);
            $this->assertStringContainsString('NV301', $sheetXml);
            $this->assertStringContainsString('416000', $sheetXml);
            $this->assertStringNotContainsString('NV302', $sheetXml);
        }

        $this->get('/api/staff/my-payroll/export?period_type=month&month=2026-11')
            ->assertOk();
    }

    public function test_staff_payroll_and_recent_attendance_pagination(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'employee_code' => 'NV401',
            'name' => 'Nhân viên Phân Trang',
            'staff_position' => 'cashier',
            'hourly_rate' => 40000,
            'account_status' => 'active',
        ]);

        foreach (['2026-11-18', '2026-11-19', '2026-11-20'] as $date) {
            $shift = WorkShift::create([
                'shift_date' => $date,
                'shift_number' => 2,
                'name' => 'Ca 2',
                'starts_at' => CarbonImmutable::parse("{$date} 06:00:00", StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone')),
                'ends_at' => CarbonImmutable::parse("{$date} 14:00:00", StaffShiftService::TIMEZONE)->setTimezone(config('app.timezone')),
                'status' => 'completed',
            ]);

            WorkShiftAssignment::create([
                'work_shift_id' => $shift->id,
                'user_id' => $staff->id,
                'position' => 'cashier',
                'check_in_at' => $shift->starts_at->subMinutes(10),
                'check_out_at' => $shift->ends_at,
                'attendance_status' => 'completed',
                'worked_minutes' => 480,
            ]);
        }

        $this->travelTo(CarbonImmutable::parse('2026-11-21 10:00:00', StaffShiftService::TIMEZONE));
        $this->actingAs($staff);

        // Phân trang bảng chấm công & tính lương (summary vẫn tổng hợp toàn bộ 3 ca = 960,000đ)
        $payPage1 = $this->getJson('/api/staff/my-payroll?period_type=month&month=2026-11&per_page=2&page=1')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $payPage1['rows']);
        $this->assertSame(3, $payPage1['summary']['total_shifts']);
        $this->assertSame(3, $payPage1['summary']['completed_shifts']);
        $this->assertSame(960000, $payPage1['summary']['total_net_salary']);
        $this->assertSame(1, $payPage1['meta']['current_page']);
        $this->assertSame(2, $payPage1['meta']['last_page']);
        $this->assertSame(2, $payPage1['meta']['per_page']);
        $this->assertSame(3, $payPage1['meta']['total']);
        $this->assertSame(1, $payPage1['meta']['from']);
        $this->assertSame(2, $payPage1['meta']['to']);

        $payPage2 = $this->getJson('/api/staff/my-payroll?period_type=month&month=2026-11&per_page=2&page=2')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $payPage2['rows']);
        $this->assertSame(960000, $payPage2['summary']['total_net_salary']);
        $this->assertSame(2, $payPage2['meta']['current_page']);
        $this->assertSame(2, $payPage2['meta']['last_page']);
        $this->assertSame(3, $payPage2['meta']['total']);
        $this->assertSame(3, $payPage2['meta']['from']);
        $this->assertSame(3, $payPage2['meta']['to']);

        // Phân trang lịch sử chấm công gần đây ở màn hình chấm công nhân viên
        $attPage1 = $this->getJson('/api/staff/my-attendance/today?recent_per_page=2&recent_page=1')
            ->assertOk()
            ->json('data');

        $recentTotal = $attPage1['recent_meta']['total'];
        $this->assertGreaterThanOrEqual(3, $recentTotal);
        $this->assertCount(2, $attPage1['recent_assignments']);
        $this->assertSame(1, $attPage1['recent_meta']['current_page']);
        $this->assertSame(2, $attPage1['recent_meta']['per_page']);
        $this->assertSame((int) ceil($recentTotal / 2), $attPage1['recent_meta']['last_page']);
        $this->assertSame(1, $attPage1['recent_meta']['from']);
        $this->assertSame(2, $attPage1['recent_meta']['to']);

        $attPage2 = $this->getJson('/api/staff/my-attendance/today?recent_per_page=2&recent_page=2')
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $attPage2['recent_meta']['current_page']);
        $this->assertSame(3, $attPage2['recent_meta']['from']);
        $this->assertCount(min(2, $recentTotal - 2), $attPage2['recent_assignments']);
    }
}
