<?php

declare(strict_types=1);

namespace App\Scheduling;

use DateInterval;
use DateTimeImmutable;

final readonly class ScheduleRule
{
    /**
     * @param string[] $times  "HH:MM" 24h local times
     * @param int[]    $weekdays 1 (Monday) .. 7 (Sunday), ISO-8601
     */
    public function __construct(
        public array $times,
        public array $weekdays,
        public string $timezone,
    ) {
    }

    public static function fromJson(string $json, string $timezone): self
    {
        $decoded = json_decode($json, true);

        return new self(
            times: is_array($decoded['times'] ?? null) ? array_values($decoded['times']) : [],
            weekdays: is_array($decoded['weekdays'] ?? null) ? array_map('intval', $decoded['weekdays']) : [],
            timezone: $timezone,
        );
    }

    /**
     * Enumerate UTC slot instants within [$from, $until).
     *
     * Local wall-clock times are the source of truth: a time that a DST spring-forward
     * skips (e.g. 02:30 on the US transition day) never fires that day, and a time an
     * autumn-back repeats is normalized by PHP to a single instant, so it fires once.
     *
     * @return DateTimeImmutable[] UTC instants, ascending
     */
    public function slotsBetween(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        if ($this->times === [] || $this->weekdays === []) {
            return [];
        }

        $zone = tz($this->timezone);
        $cursor = $from->setTimezone($zone)->setTime(0, 0);
        $slots = [];

        while ($cursor < $until) {
            if (in_array((int) $cursor->format('N'), $this->weekdays, true)) {
                foreach ($this->times as $time) {
                    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
                        continue;
                    }

                    $candidate = $cursor->setTime((int) $m[1], (int) $m[2]);

                    // A skipped local time (spring-forward) round-trips to a different
                    // wall-clock value; detect and discard rather than firing at the
                    // shifted instant.
                    if ($candidate->format('H:i') !== $time) {
                        continue;
                    }

                    if ($candidate >= $from && $candidate < $until) {
                        $slots[] = to_utc($candidate);
                    }
                }
            }

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        usort($slots, static fn (DateTimeImmutable $a, DateTimeImmutable $b) => $a <=> $b);

        return $slots;
    }

    /** @return DateTimeImmutable[] the next $count UTC slot instants from $from */
    public function nextSlots(DateTimeImmutable $from, int $count = 5): array
    {
        $slots = [];
        $windowStart = $from;
        $windowDays = 14;

        while (count($slots) < $count && $windowDays <= 366) {
            $slots = $this->slotsBetween($windowStart, $windowStart->add(new DateInterval('P' . $windowDays . 'D')));
            $windowDays *= 2;
        }

        return array_slice($slots, 0, $count);
    }
}
