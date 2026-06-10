<?php

namespace Tests\Unit;

use App\Models\Package;
use App\Models\User;
use App\Services\BonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBinaryBonusEligibility;
use Tests\TestCase;

class BinaryBonusPercentTest extends TestCase
{
    use CreatesBinaryBonusEligibility;
    use RefreshDatabase;

    public function test_binary_bonus_uses_current_package_percent(): void
    {
        $cases = [
            ['START', 7, '35000.00'],
            ['VIP', 8, '40000.00'],
            ['ELITE', 10, '50000.00'],
        ];

        foreach ($cases as [$code, $percent, $expectedAmount]) {
            $package = $this->createPackage($code, $percent);
            $user = User::factory()->create([
                'current_package_id' => $package->id,
                'left_pv' => 1000,
                'right_pv' => 1000,
                'remaining_left_pv' => 1000,
                'remaining_right_pv' => 1000,
            ]);
            $this->makeBinaryBonusEligible($user);

            $bonus = app(BonusService::class)->calculateBinaryBonus($user);

            $this->assertNotNull($bonus);
            $this->assertSame($expectedAmount, $bonus->amount);
            $this->assertSame(number_format($percent, 2, '.', ''), $bonus->metadata['binary_percent']);
        }
    }

    private function createPackage(string $code, int $binaryPercent): Package
    {
        return Package::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'price' => 60000,
            'pv' => 60000,
            'referral_percent' => 10,
            'binary_percent' => $binaryPercent,
            'sort_order' => 1,
            'status' => 'active',
            'is_active' => true,
            'is_upgradeable' => true,
        ]);
    }
}
