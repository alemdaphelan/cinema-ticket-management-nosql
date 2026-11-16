<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const CANCEL_REASON_HOLD_EXPIRED = 'hold_expired';

    protected $fillable = [
        'user_id',
        'show_id',
        'voucher_id',
        'total_amount',
        'status',
        'payment_method',
        'checkout_payment_method',
        'qr_code',
        'hold_expires_at',
    ];

    protected $casts = [
        'hold_expires_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function show()
    {
        return $this->belongsTo(Show::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class)->withTrashed();
    }

    public function seats()
    {
        return $this->hasMany(OrderSeat::class);
    }

    public function snacks()
    {
        return $this->hasMany(OrderSnack::class);
    }
}
