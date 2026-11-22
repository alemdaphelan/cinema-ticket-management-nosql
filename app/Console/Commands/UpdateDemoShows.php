<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Models\Room;
use App\Models\Show;
use App\Services\RedisQueryCache;
use App\Services\ShowSchedulingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('app:update-demo-shows')]
#[Description('Bổ sung lịch chiếu demo cho các ngày còn trống')]
class UpdateDemoShows extends Command
{
    public function handle(ShowSchedulingService $scheduling, RedisQueryCache $cache): int
    {
        $today = now(ShowSchedulingService::TIMEZONE)->startOfDay();
        $movies = Movie::where('status', 'showing')->get();
        $roomIds = Room::pluck('id');
        $times = ['14:20', '17:30', '20:40', '11:10', '08:00'];
        $prices = [75000, 85000, 95000, 100000, 120000];
        $created = 0;

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $today->copy()->addDays($offset);
            $from = $day->copy()->setTimezone(config('app.timezone'));
            if (Show::where('start_time', '>=', $from)->where('start_time', '<', $from->copy()->addDay())->exists()) {
                continue;
            }

            $slots = [];
            foreach ($times as $time) {
                $shuffledRooms = $roomIds->shuffle();
                foreach ($shuffledRooms as $roomId) {
                    $slots[] = [$roomId, $day->copy()->setTimeFromTimeString($time)->toIso8601String()];
                }
            }
            $slots = array_reverse($slots);

            // Pass 1: Đảm bảo mỗi phim đang chiếu có ít nhất 1 suất chiếu mỗi ngày
            foreach ($movies->shuffle() as $movie) {
                while ($slots) {
                    [$roomId, $start] = array_pop($slots);

                    try {
                        $scheduling->save([
                            'movie_id' => $movie->id,
                            'room_id' => $roomId,
                            'start_time' => $start,
                            'price' => $prices[array_rand($prices)],
                        ]);
                        $created++;
                        break;
                    } catch (ValidationException) {
                        continue;
                    }
                }
            }

            // Pass 2: Phân bổ các khung giờ còn lại cho thêm suất chiếu trong ngày
            foreach ($movies->shuffle() as $movie) {
                if (! $slots) {
                    break;
                }

                [$roomId, $start] = array_pop($slots);

                try {
                    $scheduling->save([
                        'movie_id' => $movie->id,
                        'room_id' => $roomId,
                        'start_time' => $start,
                        'price' => $prices[array_rand($prices)],
                    ]);
                    $created++;
                } catch (ValidationException) {
                    continue;
                }
            }
        }

        if ($created > 0) {
            $cache->invalidate(['shows:list']);
        }

        $this->info("Đã bổ sung {$created} suất chiếu demo.");

        return self::SUCCESS;
    }
}
