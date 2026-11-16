<?php

namespace App\Http\Middleware;

use App\Services\BookingHoldService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBookingAllowed
{
    public function __construct(private BookingHoldService $holds) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->holds->reconcile($request->user()->id);

        if ($this->holds->isBlocked($user)) {
            return redirect()->route('home')->with('booking_notice', [
                'title' => 'Tạm khóa đặt vé',
                'message' => $this->holds->blockedMessage($user),
                'type' => 'error',
            ]);
        }

        $request->attributes->set('booking_user', $user);

        return $next($request);
    }
}
