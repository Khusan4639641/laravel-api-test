<?php

namespace Tests\Feature;

use App\Models\BinaryBonusRun;
use App\Models\Package;
use App\Models\PvTransaction;
use App\Models\User;
use App\Services\BonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBinaryBonusEligibility;
use Tests\TestCase;

class PvTransactionBinaryBonusTest extends TestCase
{
    use CreatesBinaryBonusEligibility;
    use RefreshDatabase;

    public function test_pv_transactions_are_immutable_inputs_and_consumption_is_recorded_on_the_run(): void
    {
        $package = Package::query()->create([
            'code' => 'START',
            'name' => 'START',
            'slug' => 'start-pv-transaction-test',
            'price' => 60000,
            'pv' => 100,
            'activity_pv' => 100,
            'turnover_pv' => 100,
            'referral_percent' => 10,
            'binary_percent' => 7,
            'status' => 'active',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'remaining_left_pv' => '100.00',
            'remaining_right_pv' => '100.00',
        ]);
        $this->makeBinaryBonusEligible($user);
        $before = PvTransaction::query()->orderBy('id')->get()->map->getAttributes()->all();

        app(BonusService::class)->calculateBinaryBonus($user);

        $this->assertSame($before, PvTransaction::query()->orderBy('id')->get()->map->getAttributes()->all());
        $run = BinaryBonusRun::query()->firstOrFail();
        $this->assertSame('100.00', $run->used_left_pv);
        $this->assertSame('100.00', $run->used_right_pv);
        $this->assertSame('0.00', $user->refresh()->remaining_left_pv);
        $this->assertSame('0.00', $user->remaining_right_pv);
    }
}
