<?php

namespace Grav\Plugin\Eventz;

/**
 * Flat-file recurrence — one JSON per occurrence, no RRULE engine.
 */
class MudEventzRecurrence
{
    public const FREQUENCIES = ['weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'];

    /** @param array<string, mixed> $recurrence */
    public static function isEnabled(array $recurrence): bool
    {
        return !empty($recurrence['enabled'])
            && in_array((string) ($recurrence['frequency'] ?? ''), self::FREQUENCIES, true);
    }

    /** @param array<string, mixed> $recurrence */
    public static function normalize(array $recurrence): array
    {
        $frequency = strtolower(trim((string) ($recurrence['frequency'] ?? 'monthly')));
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            $frequency = 'monthly';
        }

        $interval = max(1, (int) ($recurrence['interval'] ?? 1));
        if ($frequency === 'biweekly') {
            $frequency = 'weekly';
            $interval = max(2, $interval * 2);
        }

        $dayOfMonth = (int) ($recurrence['day_of_month'] ?? 0);
        if ($dayOfMonth < 1 || $dayOfMonth > 28) {
            $dayOfMonth = 15;
        }

        $weekday = strtolower(trim((string) ($recurrence['weekday'] ?? 'thursday')));
        if (!in_array($weekday, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], true)) {
            $weekday = 'thursday';
        }

        $time = trim((string) ($recurrence['time'] ?? '18:00'));
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '18:00';
        }

        $timezone = trim((string) ($recurrence['timezone'] ?? 'UTC'));
        if ($timezone === '') {
            $timezone = 'UTC';
        }

        $anchor = trim((string) ($recurrence['anchor'] ?? ''));

        return [
            'enabled' => !empty($recurrence['enabled']),
            'frequency' => $frequency,
            'interval' => $interval,
            'day_of_month' => $dayOfMonth,
            'weekday' => $weekday,
            'time' => $time,
            'timezone' => $timezone,
            'anchor' => $anchor,
            'duration_hours' => max(1, min(12, (int) ($recurrence['duration_hours'] ?? 2))),
        ];
    }

    /**
     * @param array<string, mixed> $recurrence
     * @return list<string> ISO8601 start times
     */
    public static function nextOccurrences(array $recurrence, string $afterIso, int $count): array
    {
        $recurrence = self::normalize($recurrence);
        if (!self::isEnabled($recurrence)) {
            return [];
        }

        $tz = self::timezone($recurrence['timezone']);
        $cursor = self::parseDate($afterIso, $tz) ?? new \DateTimeImmutable('now', $tz);
        $times = [];

        for ($i = 0; $i < $count; $i++) {
            $next = $i === 0
                ? self::nextSlot($cursor, $recurrence, $tz)
                : self::advanceOnce($cursor, $recurrence, $tz);
            if ($next === null) {
                break;
            }
            $times[] = $next->format(\DateTimeInterface::ATOM);
            $cursor = $next;
        }

        return $times;
    }

    /** @param array<string, mixed> $recurrence */
    private static function nextSlot(\DateTimeImmutable $after, array $recurrence, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        $frequency = $recurrence['frequency'];

        if ($frequency === 'weekly') {
            $targetWeekday = self::weekdayNumber((string) $recurrence['weekday']);
            $local = $after->setTimezone($tz);
            $current = (int) $local->format('N');
            $delta = ($targetWeekday - $current + 7) % 7;
            [$hour, $minute] = array_map('intval', explode(':', (string) $recurrence['time'] . ':00'));
            $candidate = $local->modify('+' . $delta . ' days')->setTime($hour, $minute, 0);
            if ($candidate <= $local) {
                $candidate = $candidate->modify('+7 days');
            }

            return $candidate->setTimezone(new \DateTimeZone('UTC'));
        }

        if (in_array($frequency, ['monthly', 'quarterly', 'yearly'], true)) {
            $day = (int) $recurrence['day_of_month'];
            $local = $after->setTimezone($tz);
            $candidate = self::setLocalDateTime($local, $day, (string) $recurrence['time'], $tz);
            if ($candidate <= $after) {
                $months = $frequency === 'yearly' ? 12 : ($frequency === 'quarterly' ? 3 : (int) $recurrence['interval']);
                $candidate = self::setLocalDateTime($local->modify('+' . $months . ' month'), $day, (string) $recurrence['time'], $tz);
            }

            return $candidate;
        }

        return self::advanceOnce($after, $recurrence, $tz);
    }

    public static function occurrenceSlug(string $chapterSlug, \DateTimeImmutable $start): string
    {
        $chapterSlug = MudEventzRsvp::normalizeSlug($chapterSlug);

        return $chapterSlug . '-' . $start->format('Y-m-d');
    }

    public static function occurrenceLabel(\DateTimeImmutable $start, string $timezone): string
    {
        $local = $start->setTimezone(self::timezone($timezone));

        return $local->format('l, F j, Y · g:i A');
    }

    /** @param array<string, mixed> $recurrence */
    private static function advanceOnce(\DateTimeImmutable $cursor, array $recurrence, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        $frequency = $recurrence['frequency'];
        $interval = (int) $recurrence['interval'];

        if ($frequency === 'weekly') {
            $targetWeekday = self::weekdayNumber((string) $recurrence['weekday']);
            $candidate = $cursor->modify('+' . ($interval * 7) . ' days');
            $candidate = self::alignWeekday($candidate, $targetWeekday, $tz, (string) $recurrence['time']);

            return $candidate;
        }

        if ($frequency === 'monthly') {
            $candidate = $cursor->modify('+' . $interval . ' month');
            $day = (int) $recurrence['day_of_month'];

            return self::setLocalDateTime($candidate, $day, (string) $recurrence['time'], $tz);
        }

        if ($frequency === 'quarterly') {
            $candidate = $cursor->modify('+' . ($interval * 3) . ' month');
            $day = (int) $recurrence['day_of_month'];

            return self::setLocalDateTime($candidate, $day, (string) $recurrence['time'], $tz);
        }

        if ($frequency === 'yearly') {
            $candidate = $cursor->modify('+' . $interval . ' year');
            $day = (int) $recurrence['day_of_month'];

            return self::setLocalDateTime($candidate, $day, (string) $recurrence['time'], $tz);
        }

        return null;
    }

    private static function alignWeekday(\DateTimeImmutable $base, int $weekday, \DateTimeZone $tz, string $time): \DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time . ':00'));
        $local = $base->setTimezone($tz);
        $current = (int) $local->format('N');
        $delta = ($weekday - $current + 7) % 7;
        if ($delta === 0) {
            $delta = 7;
        }
        $local = $local->modify('+' . $delta . ' days')->setTime($hour, $minute, 0);

        return $local->setTimezone(new \DateTimeZone('UTC'));
    }

    private static function setLocalDateTime(\DateTimeImmutable $base, int $day, string $time, \DateTimeZone $tz): \DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time . ':00'));
        $local = $base->setTimezone($tz);
        $year = (int) $local->format('Y');
        $month = (int) $local->format('m');
        $local = new \DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute), $tz);

        return $local->setTimezone(new \DateTimeZone('UTC'));
    }

    private static function weekdayNumber(string $weekday): int
    {
        return match ($weekday) {
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            default => 7,
        };
    }

    private static function timezone(string $name): \DateTimeZone
    {
        $aliases = [
            'aest' => 'Australia/Brisbane',
            'aedt' => 'Australia/Brisbane',
            'brisbane' => 'Australia/Brisbane',
            'qld' => 'Australia/Brisbane',
            'denver' => 'America/Denver',
            'mst' => 'America/Denver',
            'mdt' => 'America/Denver',
        ];
        $key = strtolower(trim($name));
        if (isset($aliases[$key])) {
            $name = $aliases[$key];
        }

        try {
            return new \DateTimeZone($name);
        } catch (\Throwable $e) {
            return new \DateTimeZone('Australia/Brisbane');
        }
    }

    private static function parseDate(string $iso, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        if ($iso === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($iso))->setTimezone($tz);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
