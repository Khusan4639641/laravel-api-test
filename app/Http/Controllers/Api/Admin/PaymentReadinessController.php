<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Models\Product;
use App\Support\LegalSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PaymentReadinessController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $appUrl = (string) config('app.url');
        $activeProducts = Product::query()
            ->where('status', 'active')
            ->get(['id', 'image_path', 'metadata', 'stock_quantity']);
        $legalSettings = LegalSettings::publicValues();
        $webhookRoutes = $this->webhookRoutesStatus();

        return response()->json([
            'app_url' => $appUrl,
            'https_enabled' => $this->httpsEnabled($request, $appUrl),
            'tiptop_enabled' => (bool) config('tiptoppay.enabled'),
            'public_terminal_id_set' => trim((string) config('tiptoppay.public_terminal_id')) !== '',
            'currency' => strtoupper((string) config('tiptoppay.currency', 'KZT')),
            'legal_pages' => $this->legalPagesStatus(),
            'requisites_filled' => $this->requisitesFilled($legalSettings),
            'requisites_missing' => $this->missingRequisites($legalSettings),
            'products_active_count' => $activeProducts->count(),
            'products_without_image_count' => $activeProducts->filter(fn (Product $product): bool => ! $this->productHasImage($product))->count(),
            'products_without_stock_count' => $activeProducts->filter(fn (Product $product): bool => (int) $product->stock_quantity <= 0)->count(),
            'orders_payment_status_support' => $this->ordersPaymentStatusSupport(),
            'webhook_routes_work' => collect($webhookRoutes)->every(fn (bool $available): bool => $available),
            'webhook_routes' => $webhookRoutes,
            'checkout_requires_delivery_fields' => $this->checkoutRequiresDeliveryFields(),
        ]);
    }

    private function httpsEnabled(Request $request, string $appUrl): bool
    {
        if ($request->secure()) {
            return true;
        }

        return Str::startsWith(strtolower($appUrl), 'https://');
    }

    /**
     * @return array<int, array{path: string, available: bool}>
     */
    private function legalPagesStatus(): array
    {
        return collect([
            '/payment',
            '/contacts',
            '/legal/offer',
            '/legal/privacy',
            '/legal/delivery',
            '/legal/refund',
            '/legal/requisites',
            '/payment/success',
            '/payment/fail',
        ])->map(fn (string $path): array => [
            'path' => $path,
            'available' => true,
        ])->all();
    }

    /**
     * @param  array<string, string>  $settings
     */
    private function requisitesFilled(array $settings): bool
    {
        return $this->missingRequisites($settings) === [];
    }

    /**
     * @param  array<string, string>  $settings
     * @return array<int, string>
     */
    private function missingRequisites(array $settings): array
    {
        $required = [
            'company_legal_name',
            'company_bin',
            'legal_address',
            'actual_address',
            'bank_name',
            'iban',
            'bik',
            'support_phone',
            'support_email',
            'website_url',
        ];

        return collect($required)
            ->filter(fn (string $key): bool => $this->isMissingRequisiteValue($key, $settings[$key] ?? null))
            ->values()
            ->all();
    }

    private function isMissingRequisiteValue(string $key, ?string $value): bool
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return true;
        }

        $lower = mb_strtolower($normalized);

        if (str_contains($lower, 'уточняется') || str_contains($lower, 'placeholder')) {
            return true;
        }

        return match ($key) {
            'company_bin' => $normalized === '000000000000',
            'bank_name' => $normalized === 'Банк компании',
            'iban' => $normalized === 'KZ000000000000000000',
            'bik' => $normalized === 'XXXXKZKX',
            default => false,
        };
    }

    private function productHasImage(Product $product): bool
    {
        $metadata = is_array($product->metadata) ? $product->metadata : [];
        $image = $product->image_path ?: ($metadata['image_url'] ?? $metadata['image'] ?? null);

        return is_string($image) && trim($image) !== '';
    }

    private function ordersPaymentStatusSupport(): bool
    {
        foreach (['payment_status', 'payment_provider', 'payment_external_id', 'payment_transaction_id', 'paid_at', 'payment_meta'] as $column) {
            if (! Schema::hasColumn('orders', $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, bool>
     */
    private function webhookRoutesStatus(): array
    {
        return collect(['check', 'pay', 'fail', 'confirm', 'refund', 'cancel'])
            ->mapWithKeys(fn (string $action): array => [
                $action => $this->hasPostRoute("api/payments/tiptoppay/{$action}"),
            ])
            ->all();
    }

    private function hasPostRoute(string $uri): bool
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array('POST', $route->methods(), true)) {
                return true;
            }
        }

        return false;
    }

    private function checkoutRequiresDeliveryFields(): bool
    {
        $rules = (new StoreOrderRequest())->rules();

        foreach (['recipient_name', 'phone', 'city', 'delivery_address'] as $field) {
            if (! in_array('required', $rules[$field] ?? [], true)) {
                return false;
            }
        }

        return true;
    }
}
