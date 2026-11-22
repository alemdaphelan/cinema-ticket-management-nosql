<?php

namespace App\Http\Controllers;

use App\Events\ActivityOccurred;
use App\Models\Movie;
use App\Models\Order;
use App\Services\MongoReviewRepository;
use App\Services\Neo4jRecommendationRepository;
use App\Services\RedisMovieLeaderboard;
use App\Services\RedisQueryCache;
use App\Support\MovieTranslations;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MovieController extends Controller
{
    public function __construct(
        private RedisQueryCache $cache,
        private RedisMovieLeaderboard $leaderboard,
        private MongoReviewRepository $reviews,
        private Neo4jRecommendationRepository $graph,
    ) {}

    /**
     * [Public] Danh sách phim đang chiếu / sắp chiếu.
     */
    public function publicIndex(Request $request)
    {
        $filters = $request->validate([
            'status' => 'nullable|in:all,showing,coming_soon,stopped,showing_coming_soon',
            'search' => 'nullable|string|max:255',
            'genre' => 'nullable|string|max:100',
            'sort' => 'nullable|in:latest,rating,duration,title',
            'page' => 'nullable|integer|min:1',
        ]);
        $status = $filters['status'] ?? 'showing_coming_soon';
        $search = trim((string) ($filters['search'] ?? ''));
        $genre = trim((string) ($filters['genre'] ?? ''));
        $sort = $filters['sort'] ?? 'latest';
        $page = (int) ($filters['page'] ?? 1);
        $cacheKey = hash('sha256', json_encode([$status, $search, $genre, $sort, $page], JSON_THROW_ON_ERROR));

        $cached = $this->cache->remember('movies:list', $cacheKey, (int) config('nosql.redis.movie_ttl_seconds', 600), function () use ($status, $search, $genre, $sort, $page) {
            $query = Movie::query();

            if ($status === 'showing_coming_soon') {
                $query->whereIn('status', ['showing', 'coming_soon']);
            } elseif ($status !== 'all') {
                $query->where('status', $status);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('title_en', 'like', "%{$search}%");
                });
            }

            if ($genre !== '') {
                $query->where('genre', 'like', "%{$genre}%");
            }

            switch ($sort) {
                case 'rating':
                    $movies = $query->get();
                    $summaries = $this->reviews->summaries($movies->pluck('id')->all());
                    $sorted = $movies->sort(function (Movie $left, Movie $right) use ($summaries) {
                        $ratingOrder = ($summaries[$right->id]['average_rating'] ?? 0) <=> ($summaries[$left->id]['average_rating'] ?? 0);
                        if ($ratingOrder !== 0) {
                            return $ratingOrder;
                        }

                        return [$right->created_at?->timestamp ?? 0, $right->id] <=> [$left->created_at?->timestamp ?? 0, $left->id];
                    })->values();
                    $paginator = new LengthAwarePaginator(
                        $sorted->forPage($page, 12)->values(),
                        $sorted->count(),
                        12,
                        $page,
                        ['path' => request()->url(), 'query' => request()->query()],
                    );

                    return $this->attachReviewStats($paginator->toArray(), $summaries);
                case 'duration':
                    $query->orderByDesc('duration_minutes')->orderByDesc('id');
                    break;
                case 'title':
                    $query->orderBy('title')->orderBy('id');
                    break;
                case 'latest':
                default:
                    $query->orderByDesc('created_at')->orderByDesc('id');
                    break;
            }

            $paginator = $query->paginate(12, ['*'], 'page', $page);
            $summaries = $this->reviews->summaries($paginator->getCollection()->pluck('id')->all());

            return $this->attachReviewStats($paginator->toArray(), $summaries);
        });

        $payload = $cached['value'];
        $user = auth('sanctum')->user() ?? auth('web')->user() ?? auth()->user();
        if ($user) {
            $recommendations = $this->cache->remember('neo4j:recommendations', "homepage:{$user->id}", (int) config('nosql.neo4j.cache_ttl_seconds', 300), fn (): array => $this->graph->recommendations((int) $user->id, 8));
            $payload['recommendations'] = $recommendations['value'];
        }

        return response()->json($payload)->header('X-Cache', strtoupper($cached['status']));
    }

    /**
     * [Public] Chi tiết phim (bao gồm URL teaser).
     */
    public function publicShow(int $id)
    {
        $cached = $this->cache->remember("movies:detail:{$id}", 'record', (int) config('nosql.redis.movie_ttl_seconds', 600), function () use ($id) {
            $movie = Movie::query()->findOrFail($id);
            $summary = $this->reviews->summary($id);

            return [
                'data' => $movie->toArray(),
                'review_stats' => [
                    'total_reviews' => $summary['total_reviews'],
                    'average_rating' => $summary['average_rating'],
                ],
            ];
        });

        $movieData = $cached['value'];
        $movie = $movieData['data'];
        $reviewStats = $movieData['review_stats'];
        $similar = $this->cache->remember('neo4j:similar', "detail:{$id}", (int) config('nosql.neo4j.cache_ttl_seconds', 300), fn (): array => $this->graph->similarMovies($id, 8));

        // Kiểm tra user đã xem phim chưa (hỗ trợ cả Sanctum Token và Web Session)
        $hasWatched = false;
        $hasReviewed = false;
        $user = auth('sanctum')->user() ?? auth('web')->user() ?? auth()->user();

        if ($user) {
            $userId = $user->id;
            $hasWatched = Order::where('user_id', $userId)
                ->whereIn('status', ['paid', 'completed'])
                ->whereHas('show', function ($query) use ($id) {
                    $query->where('movie_id', $id)
                        ->where('start_time', '<', now());
                })
                ->exists();
            $hasReviewed = $this->reviews->exists($id, (int) $userId);
        }

        $this->leaderboard->increment($id, (float) config('nosql.redis.leaderboard_weights.view', 1));
        ActivityOccurred::dispatch(
            'MOVIE_VIEWED',
            $user ? (int) $user->id : null,
            $id,
            null,
            ['cache_status' => $cached['status']],
            $user ? 'user' : 'guest',
            'movie',
            (string) $id,
        );

        return response()->json([
            'data' => $movie,
            'review_stats' => $reviewStats,
            'has_watched' => $hasWatched,
            'has_reviewed' => $hasReviewed,
            'similar_movies' => $similar['value'],
        ])->header('X-Cache', strtoupper($cached['status']));
    }

    /**
     * [Admin] Danh sách phim (bao gồm cả stopped).
     */
    public function fetchTmdb(Request $request)
    {
        $request->validate([
            'tmdb_id' => 'required|string',
        ]);

        $tmdbId = $request->tmdb_id;
        $apiKey = env('TMDB_API_KEY');

        if (! $apiKey) {
            return response()->json(['message' => 'TMDB API key is not configured.'], 500);
        }

        $response = Http::withoutVerifying()->get("https://api.themoviedb.org/3/movie/{$tmdbId}", [
            'api_key' => $apiKey,
            'language' => 'vi',
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'Failed to fetch data from TMDB', 'error' => $response->json()], $response->status());
        }

        $data = $response->json();

        // Get Credits for Director/Cast
        $creditsResponse = Http::withoutVerifying()->get("https://api.themoviedb.org/3/movie/{$tmdbId}/credits", [
            'api_key' => $apiKey,
        ]);

        $director = 'Unknown';
        if ($creditsResponse->successful()) {
            $crew = $creditsResponse->json()['crew'] ?? [];
            foreach ($crew as $member) {
                if ($member['job'] === 'Director') {
                    $director = $member['name'];
                    break;
                }
            }
        }

        $mappedData = [
            'tmdb_id' => $tmdbId,
            'title' => $data['title'] ?? $data['original_title'],
            'director' => $director,
            'poster_url' => isset($data['poster_path']) ? 'https://image.tmdb.org/t/p/w500'.$data['poster_path'] : null,
            'teaser_url' => null, // Typically TMDB videos endpoint is needed for trailers
            'duration_minutes' => $data['runtime'] ?? 0,
            'status' => 'coming_soon',
            'raw_data' => $data, // optional for review
        ];

        return response()->json([
            'message' => 'TMDB data fetched successfully.',
            'data' => $mappedData,
        ]);
    }

    public function index()
    {
        $movies = Movie::orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $movies]);
    }

    /**
     * [Admin] Thêm phim mới.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'director' => 'nullable|string|max:255',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'poster_url' => 'nullable|string',
            'teaser_url' => 'nullable|string',
            'teaser_file' => 'nullable|file|mimes:mp4|max:102400',
            'duration_minutes' => 'required|integer|min:1',
            'status' => 'required|in:coming_soon,showing,stopped',
            'tmdb_id' => 'nullable|string',
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'genre' => 'nullable|string',
            'age_rating' => 'nullable|string|max:10',
            'release_date' => 'nullable|date',
        ]);

        if ($request->hasFile('poster')) {
            $path = $request->file('poster')->store('movies', 'public');
            $validated['poster_url'] = '/storage/'.$path;
        }

        if ($request->hasFile('teaser_file')) {
            $path = $request->file('teaser_file')->store('teasers', 'public');
            $validated['teaser_url'] = '/storage/'.$path;
        }

        unset($validated['teaser_file']);

        $movie = Movie::create($validated);
        MovieTranslations::forgetCache();
        $this->cache->invalidate(['movies:list', 'shows:list']);

        return response()->json(['data' => $movie, 'message' => 'Thêm phim thành công!'], 201);
    }

    /**
     * [Admin] Cập nhật phim.
     */
    public function update(Request $request, int $id)
    {
        $movie = Movie::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'director' => 'nullable|string|max:255',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'poster_url' => 'nullable|string',
            'teaser_url' => 'nullable|string',
            'teaser_file' => 'nullable|file|mimes:mp4|max:102400',
            'duration_minutes' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:coming_soon,showing,stopped',
            'tmdb_id' => 'nullable|string',
            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'genre' => 'nullable|string',
            'age_rating' => 'nullable|string|max:10',
            'release_date' => 'nullable|date',
        ]);

        if ($request->hasFile('poster')) {
            if ($movie->poster_url && str_starts_with($movie->poster_url, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $movie->poster_url);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('poster')->store('movies', 'public');
            $validated['poster_url'] = '/storage/'.$path;
        }

        if ($request->hasFile('teaser_file')) {
            if ($movie->teaser_url && str_starts_with($movie->teaser_url, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $movie->teaser_url);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('teaser_file')->store('teasers', 'public');
            $validated['teaser_url'] = '/storage/'.$path;
        }

        unset($validated['teaser_file']);

        $movie->update($validated);
        MovieTranslations::forgetCache();
        $this->cache->invalidate(['movies:list', "movies:detail:{$movie->id}", 'shows:list']);

        return response()->json(['data' => $movie, 'message' => 'Cập nhật phim thành công!']);
    }

    /**
     * [Admin] Xóa / Ẩn phim.
     */
    public function destroy(int $id)
    {
        $movie = Movie::findOrFail($id);
        $movie->delete();
        MovieTranslations::forgetCache();
        $this->cache->invalidate(['movies:list', "movies:detail:{$id}", 'shows:list']);

        return response()->json(null, 204);
    }

    /**
     * [Admin] Kéo phim từ TMDB (hàng loạt).
     */
    public function fetchTmdbList()
    {
        Artisan::call('movies:fetch-tmdb');
        MovieTranslations::forgetCache();
        $this->cache->invalidate(['movies:list', 'shows:list']);

        return response()->json(['message' => 'Đã kéo thành công dữ liệu từ TMDB!']);
    }

    /**
     * @param  array<string, mixed>  $paginator
     * @param  array<int, array<string, int|float>>  $summaries
     * @return array<string, mixed>
     */
    private function attachReviewStats(array $paginator, array $summaries): array
    {
        $paginator['data'] = array_map(function (array $movie) use ($summaries) {
            $summary = $summaries[(int) $movie['id']] ?? ['total_reviews' => 0, 'average_rating' => 0];
            $movie['reviews_count'] = $summary['total_reviews'];
            $movie['reviews_avg_rating'] = $summary['average_rating'];

            return $movie;
        }, $paginator['data']);

        return $paginator;
    }
}
