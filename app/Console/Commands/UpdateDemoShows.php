<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Models\Room;
use App\Models\Show;
use App\Services\ShowSchedulingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

#[Signature('app:update-demo-shows')]
#[Description('Bổ sung lịch chiếu demo cho các ngày còn trống')]
class UpdateDemoShows extends Command
{
    public function handle(ShowSchedulingService $scheduling): int
    {
        $today = now(ShowSchedulingService::TIMEZONE)->startOfDay();
        $movies = Movie::where('status', 'showing')->get();
        $roomIds = Room::pluck('id');
        $times = ['08:00', '09:30', '11:00', '13:00', '14:30', '16:00', '18:30', '20:00', '21:30'];
        $prices = [75000, 85000, 95000, 100000, 120000];
        $created = 0;

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $today->copy()->addDays($offset);
            $from = $day->copy()->setTimezone(config('app.timezone'));
            if (Show::where('start_time', '>=', $from)->where('start_time', '<', $from->copy()->addDay())->exists()) {
                continue;
            }

            $slots = [];
            foreach ($roomIds as $roomId) {
                foreach ($times as $time) {
                    $slots[] = [$roomId, $day->copy()->setTimeFromTimeString($time)->toIso8601String()];
                }
            }
            shuffle($slots);

            foreach ($movies->shuffle() as $movie) {
                $limit = random_int(1, 4);
                for ($added = 0; $added < $limit && $slots;) {
                    [$roomId, $start] = array_pop($slots);

                    try {
                        $scheduling->save([
                            'movie_id' => $movie->id,
                            'room_id' => $roomId,
                            'start_time' => $start,
                            'price' => $prices[array_rand($prices)],
                        ]);
                        $added++;
                        $created++;
                    } catch (ValidationException) {
                        continue;
                    }
                }
            }
        }

        if ($created > 0) {
            Cache::flush();
        }

        $this->info("Đã bổ sung {$created} suất chiếu demo.");

        return self::SUCCESS;
    }
}
