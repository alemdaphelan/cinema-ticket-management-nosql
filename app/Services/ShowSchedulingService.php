<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\Room;
use App\Models\Show;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShowSchedulingService
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public const CLEANING_MINUTES = 30;

    /**
     * @param  array{movie_id?: int, room_id?: int, start_time?: string, price?: int|float|string}  $attributes
     */
    public function save(array $attributes, ?int $showId = null): Show
    {
        return DB::transaction(function () use ($attributes, $showId): Show {
            $show = $showId === null ? new Show : Show::query()->lockForUpdate()->findOrFail($showId);
            $room = Room::query()->lockForUpdate()->findOrFail($attributes['room_id'] ?? $show->room_id);
            $movieId = (int) ($attributes['movie_id'] ?? $show->movie_id);
            $start = CarbonImmutable::parse($attributes['start_time'] ?? $show->start_time)
                ->setTimezone(config('app.timezone'));
            $timingChanged = ! $show->exists || $movieId !== (int) $show->movie_id || ! $start->equalTo($show->start_time);
            $end = $timingChanged
                ? $start->addMinutes(self::duration(Movie::findOrFail($movieId)))
                : CarbonImmutable::instance($show->end_time);

            if ($timingChanged || (int) $room->id !== (int) $show->room_id) {
                // The room lock serializes concurrent checks even when there are no existing shows.
                $conflict = $room->shows()
                    ->when($show->exists, fn ($query) => $query->where('id', '!=', $show->id))
                    ->where('start_time', '<', $end->addMinutes(self::CLEANING_MINUTES))
                    ->where('end_time', '>', $start->subMinutes(self::CLEANING_MINUTES))
                    ->with('movie:id,title')
                    ->orderBy('start_time')
                    ->first();

                if ($conflict) {
                    $title = $conflict->movie?->title ?? 'Suất chiếu khác';
                    $from = $conflict->start_time->copy()->setTimezone(self::TIMEZONE)->format('H:i d/m/Y');
                    $until = $conflict->end_time->copy()->addMinutes(self::CLEANING_MINUTES)
                        ->setTimezone(self::TIMEZONE)->format('H:i d/m/Y');

                    throw ValidationException::withMessages([
                        'start_time' => "{$room->name} đã có suất «{$title}» từ {$from} đến {$until} (gồm thời gian dọn rạp). Vui lòng chọn giờ khác hoặc đổi phòng.",
                    ]);
                }
            }

            $show->fill([
                'movie_id' => $movieId,
                'room_id' => $room->id,
                'start_time' => $start,
                'end_time' => $end,
                'price' => $attributes['price'] ?? $show->price,
            ])->save();

            return $show;
        }, 3);
    }

    public static function duration(Movie $movie): int
    {
        return $movie->duration_minutes > 0 ? (int) $movie->duration_minutes : 120;
    }
}
