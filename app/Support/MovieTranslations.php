<?php

namespace App\Support;

use App\Models\Movie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MovieTranslations
{
    public const CACHE_KEY = 'i18n:movie_titles';

    /**
     * @return array<string, string>
     */
    public static function titleMap(): array
    {
        try {
            if (! Schema::hasTable('movies') || ! Schema::hasColumn('movies', 'title_en')) {
                return [];
            }

            return Cache::remember(self::CACHE_KEY, 600, function (): array {
                $map = [];
                $movies = Movie::query()
                    ->whereNotNull('title_en')
                    ->where('title_en', '!=', '')
                    ->get(['title', 'title_en']);

                foreach ($movies as $movie) {
                    $vi = trim((string) $movie->title);
                    $en = trim((string) $movie->title_en);
                    if ($vi !== '' && $en !== '' && $vi !== $en) {
                        $map[$vi] = $en;
                    }
                }

                return $map;
            });
        } catch (\Throwable) {
            return [];
        }
    }

    public static function forgetCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
        }
    }
}
