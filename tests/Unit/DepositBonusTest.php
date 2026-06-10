<?php

namespace Tests\Unit;

use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BonusService;
use App\Services\DepositPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBinaryBonusEligibility;
use Tests\TestCase;

class DepositBonusTest extends TestCase
{
    use CreatesBinaryBonusEligibility;
    use RefreshDatabase;

    public function test_binary_bonus_split_sends_ten_percent_to_deposit_and_ninety_to_main_wallet(): void
    {
        $package = $this->createPackage('ELITE', 10);
        $user = User::factory()->create([
            'current_package_id' => $package->id,
            'left_pv' => 1000,
            'right_pv' => 1000,
            'remaining_left_pv' => 1000,
            'remaining_right_pv' => 1000,
        ]);
        $this->makeBinaryBonusEligible($user);

        $bonus = app(BonusService::class)->calculateBinaryBonus($user);

        $mainWallet = $user->wallets()->where('type', 'main')->firstOrFail();
        $depositWallet = $user->wallets()->where('type', 'deposit')->firstOrFail();

        $this->assertSame('50000.00', $bonus?->amount);
        $this->assertSame('45000.00', $mainWallet->balance);
        $this->assertSame('5000.00', $depositWallet->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'binary_bonus_main',
            'amount' => '45000.00',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'binary_bonus_deposit',
            'amount' => '5000.00',
        ]);
    }

    public function test_deposit_purchase_gives_twenty_percent_cashback(): void
    {
        $user = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'currency' => 'KZT',
            'balance' => 100000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        $result = app(DepositPurchaseService::class)->purchase($user, 50000);
        $cashbackBonus = $result['cashback_bonus'];

        $this->assertSame('50000.00', $user->wallets()->where('type', 'deposit')->firstOrFail()->balance);
        $this->assertSame('10000.00', $user->wallets()->where('type', 'main')->firstOrFail()->balance);
        $this->assertSame('10000.00', $cashbackBonus?->amount);
        $this->assertSame('20', $cashbackBonus?->metadata['cashback_percent']);
    }

    public function test_deposit_product_purchase_debits_deposit_and_cashbacks_to_main(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Deposit Product',
            'sku' => 'DEP-UNIT',
            'price' => 1000,
            'pv' => 2,
            'stock_quantity' => 5,
            'status' => 'active',
            'is_deposit_product' => true,
        ]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'currency' => 'KZT',
            'balance' => 2000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        $result = app(DepositPurchaseService::class)->purchaseProduct($user, $product, 1);

        $this->assertSame('1000.00', $result['deposit_transaction']->amount);
        $this->assertSame('200.00', $result['cashback_bonus']?->amount);
        $this->assertSame('1000.00', $user->wallets()->where('type', 'deposit')->firstOrFail()->balance);
        $this->assertSame('200.00', $user->wallets()->where('type', 'main')->firstOrFail()->balance);
        $this->assertSame(4, $product->refresh()->stock_quantity);
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
