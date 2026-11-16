<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ShowController;
use App\Http\Middleware\EnsureBookingAllowed;
use App\Models\Order;
use App\Services\BookingHoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// === Trang chủ ===
Route::get('/', function () {
    return view('pages.home');
})->name('home');

Route::get('/about', function () {
    return view('pages.about');
})->name('about');

// === Auth Web Routes ===
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('pages.auth.login');
    })->name('login');

    Route::post('/login', [AuthController::class, 'webLogin'])->name('login.submit');

    Route::get('/register', function () {
        return view('pages.auth.register');
    })->name('register');

    Route::post('/register', [AuthController::class, 'webRegister'])->name('register.submit');

    Route::get('/forgot-password', function () {
        return view('pages.auth.forgot-password');
    })->name('password.request');
});

Route::post('/logout', [AuthController::class, 'webLogout'])->name('logout')->middleware('auth');

// === Thể loại ===
Route::get('/genres', function () {
    return view('pages.genres');
})->name('genres');

// === Phim ===
Route::get('/movies/{id}', function (int $id) {
    return view('pages.movie-detail', ['movieId' => $id]);
})->name('movie.detail');

Route::post('/movies/{id}/reviews', [App\Http\Controllers\ReviewController::class, 'store'])->name('movie.review.submit');
Route::post('/api/movies/{id}/reviews', [App\Http\Controllers\ReviewController::class, 'store']);

// === Lịch chiếu ===
Route::get('/schedule', function () {
    return view('pages.schedule');
})->name('schedule');

// === Booking (cần login) ===
Route::middleware('auth')->prefix('booking')->group(function () {
    Route::get('/seats/{showId}', function (Request $request, int $showId) {
        $activeOrder = \App\Models\Order::where('user_id', auth()->id())
            ->where('show_id', $showId)
            ->where('status', 'pending')
            ->where('hold_expires_at', '>', now())
            ->with(['seats.seat'])
            ->first();

        return view('pages.booking.select-seats', [
            'showId' => $showId,
            'activeOrder' => $activeOrder,
            'orderId' => $activeOrder?->id,
            'holdExpiresAt' => $activeOrder?->hold_expires_at,
            'checkoutReady' => $activeOrder && ($activeOrder->checkout_payment_method !== null
                || $request->query('return_to_payment') === (string) $activeOrder->id),
        ]);
    })->middleware(EnsureBookingAllowed::class)->name('booking.seats');

    Route::get('/snacks/{orderId}', function (Request $request, BookingHoldService $holds, int $orderId) {
        $order = Order::where('user_id', auth()->id())
            ->where('id', $orderId)
            ->with(['snacks.snack'])
            ->first();

        if (! $order) {
            return redirect()->route('home');
        }

        if ($order->show_id === null || in_array($order->status, ['paid', 'completed'], true)) {
            return redirect()->route('booking.success', ['orderId' => $order->id]);
        }

        if ($order->status === 'cancelled') {
            $redirect = redirect()->route('booking.seats', ['showId' => $order->show_id]);
            if ($order->cancel_reason === Order::CANCEL_REASON_HOLD_EXPIRED) {
                $redirect->with('booking_notice', [
                    'title' => 'Hết thời gian giữ ghế',
                    'message' => $holds->expirationMessage($request->attributes->get('booking_user')),
                    'type' => 'warning',
                ]);
            }

            return $redirect;
        }

        return view('pages.booking.select-snacks', [
            'orderId' => $orderId,
            'showId' => $order->show_id,
            'order' => $order,
            'selectedSnacks' => $order->snacks->map(fn ($line) => [
                'id' => $line->snack_id,
                'name' => $line->snack->name,
                'image_url' => $line->snack->image_url,
                'price' => $line->price / $line->quantity,
                'quantity' => $line->quantity,
            ]),
            'holdExpiresAt' => $order->hold_expires_at,
        ]);
    })->middleware(EnsureBookingAllowed::class)->name('booking.snacks');

    Route::get('/payment/{orderId}', function (Request $request, BookingHoldService $holds, int $orderId) {
        $order = Order::where('user_id', auth()->id())
            ->where('id', $orderId)
            ->with('voucher')
            ->first();

        if (! $order) {
            return redirect()->route('home');
        }

        if ($order->show_id === null || in_array($order->status, ['paid', 'completed'], true)) {
            return redirect()->route('booking.success', ['orderId' => $order->id]);
        }

        if ($order->status === 'cancelled') {
            $redirect = redirect()->route('booking.seats', ['showId' => $order->show_id]);
            if ($order->cancel_reason === Order::CANCEL_REASON_HOLD_EXPIRED) {
                $redirect->with('booking_notice', [
                    'title' => 'Hết thời gian giữ ghế',
                    'message' => $holds->expirationMessage($request->attributes->get('booking_user')),
                    'type' => 'warning',
                ]);
            }

            return $redirect;
        }

        return view('pages.booking.payment', [
            'orderId' => $orderId,
            'showId' => $order->show_id,
            'order' => $order,
            'holdExpiresAt' => $order->hold_expires_at,
        ]);
    })->middleware(EnsureBookingAllowed::class)->name('booking.payment');

    Route::get('/success/{orderId}', function (int $orderId) {
        $order = \App\Models\Order::where('user_id', auth()->id())
            ->where('id', $orderId)
            ->firstOrFail();

        return view('pages.booking.success', [
            'orderId' => $orderId,
            'showId' => $order->show_id,
            'order' => $order,
        ]);
    })->name('booking.success');

    Route::get('/history', function () {
        return view('pages.user.ticket-history');
    })->name('booking.history');
});

// === Profile & Settings ===
Route::middleware('auth')->group(function () {
    Route::get('/profile/settings', [App\Http\Controllers\UserController::class, 'settings'])->name('user.settings');
    Route::post('/profile/settings', [App\Http\Controllers\UserController::class, 'updateSettings'])->name('user.settings.update');
    Route::get('/profile/password', [App\Http\Controllers\UserController::class, 'password'])->name('user.password');
    Route::post('/profile/password', [App\Http\Controllers\UserController::class, 'updatePassword'])->name('user.password.update');
});

// === Admin (cần login + role admin) ===
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::get('/movies', function () {
        return view('admin.movies.index');
    })->name('admin.movies');

    Route::get('/movies/create', function () {
        return view('admin.movies.create');
    })->name('admin.movies.create');

    Route::get('/movies/{id}/edit', function (int $id) {
        return view('admin.movies.edit', ['movieId' => $id]);
    })->name('admin.movies.edit');

    Route::get('/shows', [ShowController::class, 'adminPage'])->name('admin.shows');

    Route::get('/shows/create', function () {
        $movies = \App\Models\Movie::whereIn('status', ['showing', 'coming_soon'])->orderBy('title')->get();
        $rooms = \App\Models\Room::orderBy('id')->get();
        return view('admin.shows.create', compact('movies', 'rooms'));
    })->name('admin.shows.create');

    Route::get('/snacks', function () {
        return view('admin.snacks.index');
    })->name('admin.snacks');

    Route::get('/vouchers', function () {
        return view('admin.vouchers.index');
    })->name('admin.vouchers');

    Route::get('/orders', function () {
        return view('admin.orders.index');
    })->name('admin.orders');

    Route::get('/orders/export', [App\Http\Controllers\OrderController::class, 'exportExcel'])->name('admin.orders.export');
});

// === Staff (cần login + role staff hoặc admin) ===
Route::middleware(['auth', \App\Http\Middleware\RoleMiddleware::class . ':staff,admin'])->prefix('staff')->group(function () {
    Route::get('/scan', function () {
        return view('staff.scan');
    })->name('staff.scan');

    Route::get('/orders', function () {
        return view('staff.orders.index');
    })->name('staff.orders');

    Route::get('/orders/export', [App\Http\Controllers\OrderController::class, 'exportExcel'])->name('staff.orders.export');

    Route::get('/booking', function () {
        return redirect('/');
    })->name('staff.booking');
});

// === Chatbot ===
Route::post('/chatbot/chat', [App\Http\Controllers\ChatbotController::class, 'chat'])->name('chatbot.chat');

// === Swagger API Docs ===
Route::get('/api/docs', function () {
    return view('swagger');
});

Route::get('/api/docs/yaml', function () {
    return response()->file(base_path('docs/swagger_api.yaml'));
});
