<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\BonusController as AdminBonusController;
use App\Http\Controllers\Api\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Api\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PaymentReadinessController as AdminPaymentReadinessController;
use App\Http\Controllers\Api\Admin\OverviewController as AdminOverviewController;
use App\Http\Controllers\Api\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Api\Admin\PartnerController as AdminPartnerController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\Admin\StatusController as AdminStatusController;
use App\Http\Controllers\Api\Admin\StructureController as AdminStructureController;
use App\Http\Controllers\Api\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Api\Support\TicketController as SupportTicketController;
use App\Http\Controllers\Api\BinaryBonusController;
use App\Http\Controllers\Api\DepositPurchaseController;
use App\Http\Controllers\Api\Dashboard\BonusController as DashboardBonusController;
use App\Http\Controllers\Api\Dashboard\DepositProductController as DashboardDepositProductController;
use App\Http\Controllers\Api\Dashboard\EarningsSummaryController as DashboardEarningsSummaryController;
use App\Http\Controllers\Api\Dashboard\NotificationController as DashboardNotificationController;
use App\Http\Controllers\Api\Dashboard\OrderController as DashboardOrderController;
use App\Http\Controllers\Api\Dashboard\OverviewController as DashboardOverviewController;
use App\Http\Controllers\Api\Dashboard\PackageController as DashboardPackageController;
use App\Http\Controllers\Api\Dashboard\ProductController as DashboardProductController;
use App\Http\Controllers\Api\Dashboard\ProfileController as DashboardProfileController;
use App\Http\Controllers\Api\Dashboard\StructureController as DashboardStructureController;
use App\Http\Controllers\Api\Dashboard\SupportTicketController as DashboardSupportTicketController;
use App\Http\Controllers\Api\Dashboard\TransactionController as DashboardTransactionController;
use App\Http\Controllers\Api\Dashboard\WithdrawalController as DashboardWithdrawalController;
use App\Http\Controllers\Api\PackageActivationController;
use App\Http\Controllers\Api\PackageUpgradeController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\Payments\TipTopPayIntentController;
use App\Http\Controllers\Api\Payments\TipTopPayWebhookController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PublicApi\FaqController as PublicFaqController;
use App\Http\Controllers\Api\PublicApi\LegalSettingsController as PublicLegalSettingsController;
use App\Http\Controllers\Api\PublicApi\NewsController as PublicNewsController;
use App\Http\Controllers\Api\PublicApi\PackageController as PublicPackageController;
use App\Http\Controllers\Api\PublicApi\ProductController as PublicProductController;
use App\Http\Controllers\Api\PublicApi\StatusController as PublicStatusController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function (): void {
    Route::get('/products', [PublicProductController::class, 'index']);
    Route::get('/products/{product}', [PublicProductController::class, 'show']);
    Route::get('/packages', [PublicPackageController::class, 'index']);
    Route::get('/registration-packages', [PublicPackageController::class, 'registration']);
    Route::get('/news', [PublicNewsController::class, 'index']);
    Route::get('/news/{news}', [PublicNewsController::class, 'show']);
    Route::get('/faqs', [PublicFaqController::class, 'index']);
    Route::get('/statuses', [PublicStatusController::class, 'index']);
    Route::get('/legal-settings', PublicLegalSettingsController::class);
});

Route::get('/ref/{user_id}/{branch}', [ReferralController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::prefix('payments/tiptoppay')->group(function (): void {
    Route::get('/status', [TipTopPayWebhookController::class, 'status']);
    Route::post('/check', [TipTopPayWebhookController::class, 'check']);
    Route::post('/pay', [TipTopPayWebhookController::class, 'pay']);
    Route::post('/fail', [TipTopPayWebhookController::class, 'fail']);
    Route::post('/confirm', [TipTopPayWebhookController::class, 'confirm']);
    Route::post('/refund', [TipTopPayWebhookController::class, 'refund']);
    Route::post('/cancel', [TipTopPayWebhookController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'account_active'])->group(function (): void {
    Route::middleware('role_permission:admin.bonuses.manage')->post('/bonuses/binary/calculate', BinaryBonusController::class);
    Route::post('/deposits/purchase', DepositPurchaseController::class);
    Route::post('/packages/{package}/activate', PackageActivationController::class);
    Route::post('/packages/{package}/upgrade', PackageUpgradeController::class);
    Route::get('/withdrawals', [WithdrawalController::class, 'index']);
    Route::post('/withdrawals', [WithdrawalController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/payment/tiptoppay/intent', TipTopPayIntentController::class);
    Route::post('/orders/{order}/payments/tiptoppay/intent', TipTopPayIntentController::class);
    Route::post('/payments/tiptoppay/package-intent', [TipTopPayIntentController::class, 'packageFromPayload']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/me/permissions', PermissionController::class);

    Route::prefix('dashboard')->group(function (): void {
        Route::get('/overview', DashboardOverviewController::class);
        Route::get('/profile', DashboardProfileController::class);
        Route::patch('/profile/avatar', [DashboardProfileController::class, 'avatar']);
        Route::post('/profile/avatar', [DashboardProfileController::class, 'avatar']);
        Route::get('/structure', DashboardStructureController::class);
        Route::get('/transactions', DashboardTransactionController::class);
        Route::get('/bonuses', DashboardBonusController::class);
        Route::get('/earnings-summary', DashboardEarningsSummaryController::class);
        Route::get('/notifications', [DashboardNotificationController::class, 'index']);
        Route::get('/packages', DashboardPackageController::class);
        Route::post('/package/{package}/payments/tiptoppay/intent', [TipTopPayIntentController::class, 'package']);
        Route::get('/products', DashboardProductController::class);
        Route::get('/deposit-products', DashboardDepositProductController::class);
        Route::get('/orders', DashboardOrderController::class);
        Route::get('/withdrawals', [DashboardWithdrawalController::class, 'index']);
        Route::post('/withdrawals', [DashboardWithdrawalController::class, 'store']);
        Route::get('/support-tickets', [DashboardSupportTicketController::class, 'index']);
        Route::post('/support-tickets', [DashboardSupportTicketController::class, 'store']);
        Route::middleware('own_resource:ticket')->group(function (): void {
            Route::get('/support-tickets/{ticket}', [DashboardSupportTicketController::class, 'show']);
            Route::put('/support-tickets/{ticket}', [DashboardSupportTicketController::class, 'update']);
            Route::patch('/support-tickets/{ticket}/close', [DashboardSupportTicketController::class, 'close']);
        });
    });

    Route::middleware('role_permission:support.manage')->prefix('support')->group(function (): void {
        Route::get('/tickets', [SupportTicketController::class, 'index']);
        Route::get('/tickets/{ticket}', [SupportTicketController::class, 'show']);
        Route::post('/tickets/{ticket}/reply', [SupportTicketController::class, 'reply']);
        Route::patch('/tickets/{ticket}/status', [SupportTicketController::class, 'status']);
        Route::patch('/tickets/{ticket}/assign', [SupportTicketController::class, 'assign']);
    });

    Route::middleware('role_permission:support.manage')->prefix('admin')->group(function (): void {
        Route::get('/support-tickets', [SupportTicketController::class, 'index']);
        Route::get('/support-tickets/{ticket}', [SupportTicketController::class, 'show']);
        Route::post('/support-tickets/{ticket}/reply', [SupportTicketController::class, 'reply']);
        Route::patch('/support-tickets/{ticket}/reply', [SupportTicketController::class, 'reply']);
        Route::patch('/support-tickets/{ticket}/status', [SupportTicketController::class, 'status']);
        Route::patch('/support-tickets/{ticket}/assign', [SupportTicketController::class, 'assign']);
        Route::patch('/support-tickets/{ticket}/close', [SupportTicketController::class, 'close']);
    });

    Route::prefix('admin')->group(function (): void {
        Route::middleware('role_permission:admin.overview')->group(function (): void {
            Route::get('/overview', AdminOverviewController::class);
        });

        Route::middleware('role_permission:admin.transactions.read')->group(function (): void {
            Route::get('/transactions', [AdminTransactionController::class, 'index']);
        });

        Route::middleware('role_permission:admin.withdrawals.read')->group(function (): void {
            Route::get('/withdrawals', [AdminWithdrawalController::class, 'index']);
        });

        Route::middleware('role_permission:admin.orders.read')->group(function (): void {
            Route::get('/orders', [AdminOrderController::class, 'index']);
            Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        });

        Route::middleware('role_permission:admin.read')->group(function (): void {
            Route::get('/structure', AdminStructureController::class);
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::get('/users/{user}', [AdminUserController::class, 'show']);
            Route::get('/products', [AdminProductController::class, 'index']);
            Route::get('/products/{product}', [AdminProductController::class, 'show']);
            Route::get('/packages', [AdminPackageController::class, 'index']);
            Route::get('/bonuses', [AdminBonusController::class, 'index']);
            Route::get('/news', [AdminNewsController::class, 'index']);
            Route::get('/news/{news}', [AdminNewsController::class, 'show']);
            Route::get('/faqs', [AdminFaqController::class, 'index']);
            Route::get('/statuses', AdminStatusController::class);
        });

        Route::middleware('role_permission:admin.bonuses.manage')->group(function (): void {
            Route::post('/bonuses/binary/calculate', [AdminBonusController::class, 'calculateBinary']);
            Route::post('/partners/{user}/binary-bonus/calculate', [AdminPartnerController::class, 'calculateBinaryBonus']);
            Route::post('/partners/{user}/binary/recalculate', [AdminPartnerController::class, 'recalculateBinaryBonus']);
        });

        Route::middleware('role_permission:admin.catalog.write')->group(function (): void {
            Route::post('/products', [AdminProductController::class, 'store']);
            Route::put('/products/{product}', [AdminProductController::class, 'update']);
            Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
            Route::post('/packages', [AdminPackageController::class, 'store']);
            Route::put('/packages/{package}', [AdminPackageController::class, 'update']);
            Route::post('/news', [AdminNewsController::class, 'store']);
            Route::put('/news/{news}', [AdminNewsController::class, 'update']);
            Route::delete('/news/{news}', [AdminNewsController::class, 'destroy']);
            Route::post('/faqs', [AdminFaqController::class, 'store']);
            Route::put('/faqs/{faq}', [AdminFaqController::class, 'update']);
            Route::delete('/faqs/{faq}', [AdminFaqController::class, 'destroy']);
        });

        Route::middleware('role_permission:admin.orders.manage')->group(function (): void {
            Route::patch('/orders/{order}/status', [AdminOrderController::class, 'status']);
        });

        Route::middleware('role_permission:admin.withdrawals.manage')->group(function (): void {
            Route::patch('/withdrawals/{withdrawal}/approve', [AdminWithdrawalController::class, 'approve']);
            Route::patch('/withdrawals/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject']);
        });

        Route::middleware('role_permission:admin.partners.create')->group(function (): void {
            Route::post('/partners/bulk-create', [AdminPartnerController::class, 'bulkCreate']);
            Route::post('/partners', [AdminPartnerController::class, 'store']);
        });

        Route::middleware('role_permission:admin.read')->group(function (): void {
            Route::get('/partners', [AdminUserController::class, 'index']);
            Route::get('/partners/search', [AdminUserController::class, 'search']);
            Route::get('/sponsors/search', [AdminUserController::class, 'sponsorSearch']);
            Route::get('/partners/{user}/delete-preview', [AdminPartnerController::class, 'deletePreview']);
            Route::get('/partners/{user}', [AdminPartnerController::class, 'show']);
            Route::get('/partners/{user}/transactions', [AdminPartnerController::class, 'transactions']);
            Route::get('/partners/{user}/tree', [AdminPartnerController::class, 'tree']);
        });

        Route::middleware('role_permission:admin.partners.manage')->group(function (): void {
            Route::put('/partners/{user}', [AdminPartnerController::class, 'update']);
            Route::patch('/partners/{user}/status', [AdminPartnerController::class, 'status']);
            Route::patch('/partners/{user}/package', [AdminPartnerController::class, 'package']);
            Route::patch('/partners/{user}/block', [AdminPartnerController::class, 'block']);
            Route::patch('/partners/{user}/unblock', [AdminPartnerController::class, 'unblock']);
            Route::patch('/partners/{user}/note', [AdminPartnerController::class, 'note']);
            Route::post('/partners/{user}/change-password', [AdminPartnerController::class, 'changePassword']);
            Route::delete('/partners/{user}', [AdminPartnerController::class, 'destroy']);
        });

        Route::middleware('role_permission:admin.reports')->group(function (): void {
            Route::get('/reports/summary', [AdminReportController::class, 'summary']);
        });

        Route::middleware('role_permission:admin.settings')->group(function (): void {
            Route::get('/settings', [AdminSettingsController::class, 'index']);
            Route::put('/settings', [AdminSettingsController::class, 'update']);
            Route::get('/payment-readiness', AdminPaymentReadinessController::class);
        });
    });
});
