<?php

namespace Tests\Feature;

use App\Models\BonusTransaction;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_get_reports_summary(): void
    {
        $this->seedReportData();
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->getJson('/api/admin/reports/summary')
            ->assertOk()
            ->assertJsonPath('summary.total_turnover', 100000)
            ->assertJsonPath('summary.total_bonus_paid', 25000)
            ->assertJsonPath('summary.pending_withdrawals', 10000)
            ->assertJsonPath('summary.total_pv', 5000)
            ->assertJsonStructure([
                'summary' => [
                    'total_users',
                    'total_turnover',
                    'total_bonus_paid',
                    'pending_withdrawals',
                    'total_pv',
                ],
                'chart' => [
                    '*' => ['period', 'turnover', 'bonuses', 'withdrawals', 'users', 'package_sales', 'pv'],
                ],
            ]);
    }

    public function test_accountant_can_get_reports_summary(): void
    {
        $this->seedReportData();
        Sanctum::actingAs(User::factory()->create(['role' => 'accountant']));

        $this->getJson('/api/admin/reports/summary')
            ->assertOk()
            ->assertJsonPath('summary.total_turnover', 100000)
            ->assertJsonCount(6, 'chart');
    }

    public function test_user_cannot_get_reports_summary(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/admin/reports/summary')->assertForbidden();
    }

    private function seedReportData(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'type' => 'main',
            'currency' => 'KZT',
            'balance' => 100000,
            'hold_balance' => 0,
            'status' => 'active',
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'REPORT-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_amount' => 100000,
            'discount_amount' => 0,
            'total_amount' => 100000,
            'total_pv' => 5000,
        ]);
        BonusTransaction::query()->create([
            'user_id' => $user->id,
            'bonus_type' => 'binary',
            'amount' => 25000,
            'left_pv' => 5000,
            'right_pv' => 5000,
            'matched_pv' => 5000,
            'status' => 'completed',
            'calculated_at' => now(),
        ]);
        WithdrawalRequest::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => 10000,
            'fee_amount' => 0,
            'net_amount' => 10000,
            'currency' => 'KZT',
            'status' => 'pending',
            'payment_method' => 'card_account',
            'payout_period_days' => 14,
        ]);
    }
}
