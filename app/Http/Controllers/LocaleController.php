<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'locale' => 'required|in:vi,en',
        ]);

        $locale = $validated['locale'];
        $request->session()->put('locale', $locale);
        $cookie = cookie('cinema_locale', $locale, 60 * 24 * 365, '/', null, null, false, false, 'lax');

        if ($request->expectsJson()) {
            return response()->json(['locale' => $locale])->withCookie($cookie);
        }

        return back()->withCookie($cookie);
    }

    public function switch(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['vi', 'en'], true), 404);

        $request->session()->put('locale', $locale);
        $cookie = cookie('cinema_locale', $locale, 60 * 24 * 365, '/', null, null, false, false, 'lax');

        return back()->withCookie($cookie);
    }
}
