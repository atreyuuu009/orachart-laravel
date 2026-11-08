<?php

namespace App\Support;

/**
 * Pure slot maths (no database, no framework) so it can be unit-tested.
 * All values are "minutes since midnight" for a single calendar day.
 */
final class SlotCalculator
{
    public static function overlaps(int $aStart, int $aEnd, int $bStart, int $bEnd): bool
    {
        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /** Does [$start,$end) clash with any busy interval? */
    public static function clashes(int $start, int $end, array $busy): bool
    {
        foreach ($busy as [$bs, $be]) {
            if (self::overlaps($start, $end, $bs, $be)) return true;
        }
        return false;
    }

    /**
     * Start times (minutes) where a treatment of $duration fits inside one of $windows
     * without touching a busy interval. The buffers widen the occupied range only:
     * occupied = [start - $before, start + $duration + $after).
     *
     * @param array<int,array{0:int,1:int}> $windows working windows [open, close]
     * @param array<int,array{0:int,1:int}> $busy    already-occupied intervals (own buffers included)
     * @return int[]
     */
    public static function startTimes(array $windows, int $step, int $duration, int $before, int $after, array $busy): array
    {
        $step = max(5, $step);
        $out = [];
        foreach ($windows as [$open, $close]) {
            for ($t = $open; $t + $duration <= $close; $t += $step) {
                if (! self::clashes($t - $before, $t + $duration + $after, $busy)) $out[$t] = $t;
            }
        }
        ksort($out);
        return array_values($out);
    }

    /**
     * Intersection of two window lists, e.g. clinic hours ∩ dentist hours.
     * @return array<int,array{0:int,1:int}>
     */
    public static function intersect(array $a, array $b): array
    {
        $out = [];
        foreach ($a as [$as, $ae]) {
            foreach ($b as [$bs, $be]) {
                $s = max($as, $bs); $e = min($ae, $be);
                if ($s < $e) $out[] = [$s, $e];
            }
        }
        return $out;
    }

    public static function fmt(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function parse(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));
        return $h * 60 + $m;
    }

    /** 540 => "9:00 AM" */
    public static function label(int $minutes): string
    {
        $h = intdiv($minutes, 60); $m = $minutes % 60;
        return sprintf('%d:%02d %s', $h % 12 === 0 ? 12 : $h % 12, $m, $h >= 12 ? 'PM' : 'AM');
    }
}
