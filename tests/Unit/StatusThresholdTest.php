<?php

namespace Tests\Unit;

use App\Services\StatusService;
use Tests\TestCase;

class StatusThresholdTest extends TestCase
{
    public function test_status_thresholds_match_business_tz(): void
    {
        $service = app(StatusService::class);

        $this->assertSame('user', $service->statusForPv(999));
        $this->assertSame('manager', $service->statusForPv(1000));
        $this->assertSame('leader', $service->statusForPv(2500));
        $this->assertSame('director', $service->statusForPv(5000));
        $this->assertSame('bronze_director', $service->statusForPv(10000));
        $this->assertSame('silver_director', $service->statusForPv(25000));
        $this->assertSame('gold_director', $service->statusForPv(50000));
        $this->assertSame('platinum_director', $service->statusForPv(100000));
        $this->assertSame('emerald_director', $service->statusForPv(250000));
        $this->assertSame('diamond_director', $service->statusForPv(500000));
    }
}
