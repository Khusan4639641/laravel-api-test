<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BinaryBonusPeriodResolver
{
    public const TIMEZONE = 'Asia/Tashkent';

    /**
     * Resolve the half-month period containing the scheduler invocation.
     * Period ends are inclusive because the existing schema stores timestamps
     * and BonusService compares them as inclusive values.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable, scheduled_for: CarbonImmutable, timezone: string}
     */
    public function resolve(?CarbonInterface $scheduledFor = null): array
    {
        $scheduledFor = CarbonImmutable::instance(
            ($scheduledFor ?: now(self::TIMEZONE))->setTimezone(self::TIMEZONE)
        );

        $first = $scheduledFor->startOfMonth()->setTime(3, 0);
        $fifteenth = $first->addDays(14);

        if ($scheduledFor->lt($first)) {
            $start = $first->subMonth()->addDays(14);
            $nextBoundary = $first;
        } elseif ($scheduledFor->lt($fifteenth)) {
            $start = $first;
            $nextBoundary = $fifteenth;
        } else {
            $start = $fifteenth;
            $nextBoundary = $first->addMonth();
        }

        return [
            'start' => $start,
            'end' => $nextBoundary->subSecond(),
            'scheduled_for' => $scheduledFor,
            'timezone' => self::TIMEZONE,
        ];
    }
}
