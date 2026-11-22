<?php

namespace App\Console\Commands;

use App\Services\StaffShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('staff:ensure-shifts')]
#[Description('Đảm bảo lịch làm 3 ca/ngày cho nhân viên rạp luôn đầy đủ trong 14 ngày tới')]
class EnsureStaffShifts extends Command
{
    public function handle(StaffShiftService $shiftService): int
    {
        $nowVn = CarbonImmutable::now(StaffShiftService::TIMEZONE);
        $shiftService->ensureRangeShifts($nowVn->subDays(1), $nowVn->addDays(14));
        $shiftService->syncStatusesAndAbsences($nowVn);

        $this->info('Đã đồng bộ lịch làm 3 ca/ngày cho nhân viên trong 14 ngày tới.');

        return self::SUCCESS;
    }
}
