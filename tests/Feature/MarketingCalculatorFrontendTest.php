<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingCalculatorFrontendTest extends TestCase
{
    public function test_marketing_calculator_uses_expected_demo_formula_and_limits(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/safi/pages/MarketingPlanPage.tsx'));

        $this->assertStringContainsString('CALCULATOR_MAX_AMOUNT = 250_000_000', $source);
        $this->assertStringContainsString('START: { referral: 10, binary: 7 }', $source);
        $this->assertStringContainsString('VIP: { referral: 10, binary: 8 }', $source);
        $this->assertStringContainsString('ELITE: { referral: 10, binary: 10 }', $source);
        $this->assertStringContainsString('const lesserBranch = Math.min(safeLeftVol, safeRightVol);', $source);
        $this->assertStringContainsString('const estimatedReferral = (safePersonalSales * referralPercent) / 100;', $source);
        $this->assertStringContainsString('const estimatedBinary = (lesserBranch * binaryPercent) / 100;', $source);
        $this->assertStringNotContainsString('clampMoney(personalSales, 1000000)', $source);
        $this->assertStringNotContainsString('clampMoney(leftVol, 5000000)', $source);
    }
}
