<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkShiftAssignment extends Model
{
    public const POSITIONS = [
        'ticket_checker' => 'Soát vé',
        'cashier' => 'Thu ngân',
        'snack_prep' => 'Chuẩn bị snacks',
    ];

    public const POSITION_QUOTAS = [
        'ticket_checker' => 2,
        'cashier' => 2,
        'snack_prep' => 1,
    ];

    protected $fillable = [
        'work_shift_id',
        'user_id',
        'position',
        'check_in_at',
        'check_out_at',
        'check_in_method',
        'check_out_method',
        'check_in_confidence',
        'attendance_status',
        'is_violation',
        'violation_reason',
        'late_minutes',
        'early_leave_minutes',
        'worked_minutes',
        'admin_alert_acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_confidence' => 'decimal:2',
            'is_violation' => 'boolean',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'admin_alert_acknowledged_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function positionLabel(): string
    {
        return self::POSITIONS[$this->position] ?? $this->position;
    }
}
