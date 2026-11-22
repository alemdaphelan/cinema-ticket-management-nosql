<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Services\RedisQueryCache;
use App\Support\MovieTranslations;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class SyncMovieEnglish extends Command
{
    protected $signature = 'movies:sync-english {--force : Re-sync all movies with a tmdb_id}';

    protected $description = 'Đồng bộ tiêu đề và mô tả tiếng Anh chính thức từ TMDB cho các phim';

    public function handle(RedisQueryCache $cache): int
    {
        $apiKey = env('TMDB_API_KEY') ?: 'ebf1d04adcf30701e41125e0fa1ea5c6';

        $query = Movie::query()
            ->whereNotNull('tmdb_id')
            ->where('tmdb_id', '!=', '');

        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('title_en')->orWhere('title_en', '');
            });
        }

        $movies = $query->orderBy('id')->get();
        if ($movies->isEmpty()) {
            $this->info('Tất cả phim có tmdb_id đều đã có tên tiếng Anh.');

            return self::SUCCESS;
        }

        $updatedIds = [];

        foreach ($movies->chunk(10) as $chunk) {
            $chunkList = $chunk->values();
            $responses = Http::pool(function (Pool $pool) use ($chunkList, $apiKey) {
                $requests = [];
                foreach ($chunkList as $idx => $movie) {
                    $requests[$idx] = $pool->as((string) $idx)
                        ->timeout(5)
                        ->withoutVerifying()
                        ->get("https://api.themoviedb.org/3/movie/{$movie->tmdb_id}?api_key={$apiKey}&language=en-US");
                }

                return $requests;
            });

            foreach ($chunkList as $idx => $movie) {
                $response = $responses[(string) $idx] ?? $responses[$idx] ?? null;
                if (! $response || $response instanceof \Throwable || ! $response->successful()) {
                    continue;
                }

                $titleEn = trim((string) $response->json('title', ''));
                $descEn = trim((string) $response->json('overview', ''));
                $updates = [];

                if ($titleEn !== '') {
                    $updates['title_en'] = $titleEn;
                }
                if ($descEn !== '') {
                    $updates['description_en'] = $descEn;
                }

                if ($updates !== []) {
                    $movie->update($updates);
                    $updatedIds[] = $movie->id;
                }
            }
        }

        if ($updatedIds !== []) {
            MovieTranslations::forgetCache();
            $tags = ['movies:list', 'shows:list'];
            foreach ($updatedIds as $id) {
                $tags[] = "movies:detail:{$id}";
            }
            $cache->invalidate($tags);
        }

        $this->info('Đã cập nhật tiếng Anh cho '.count($updatedIds).' / '.$movies->count().' phim.');

        return self::SUCCESS;
    }
}
