<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    public const DEFINITIONS = [
        1 => [
            'name' => 'Ca 1',
            'short_label' => 'Ca 1',
            'time_label' => '22:00 - 06:00',
            'start_hour' => 22,
            'duration_hours' => 8,
            'start_day_offset' => -1,
        ],
        2 => [
            'name' => 'Ca 2',
            'short_label' => 'Ca 2',
            'time_label' => '06:00 - 14:00',
            'start_hour' => 6,
            'duration_hours' => 8,
            'start_day_offset' => 0,
        ],
        3 => [
            'name' => 'Ca 3',
            'short_label' => 'Ca 3',
            'time_label' => '14:00 - 22:00',
            'start_hour' => 14,
            'duration_hours' => 8,
            'start_day_offset' => 0,
        ],
    ];

    protected $fillable = [
        'shift_date',
        'shift_number',
        'name',
        'starts_at',
        'ends_at',
        'status',
        'counter_revenue',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date:Y-m-d',
            'shift_number' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'counter_revenue' => 'decimal:2',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkShiftAssignment::class, 'work_shift_id');
    }
}
