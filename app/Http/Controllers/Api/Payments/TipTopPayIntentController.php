<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Services\Payments\TipTopPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TipTopPayIntentController extends Controller
{
    private const PACKAGE_PURCHASE_DISABLED_MESSAGE = 'Покупка пакетов пользователем временно недоступна. Обратитесь к администратору.';

    public function __invoke(Request $request, Order $order, TipTopPayService $service): JsonResponse
    {
        return response()->json($service->createOrderPaymentIntent($request->user(), $order));
    }

    public function package(Request $request, Package $package, TipTopPayService $service): JsonResponse
    {
        if (! config('safi.user_package_purchases_enabled', false)) {
            return response()->json([
                'message' => self::PACKAGE_PURCHASE_DISABLED_MESSAGE,
            ], 403);
        }

        $validated = $request->validate([
            'package_code' => ['sometimes', 'string', Rule::in(Package::PUBLIC_CODES)],
            'upgrade_from' => ['sometimes', 'nullable', 'string'],
        ]);

        if (isset($validated['package_code']) && strtoupper($validated['package_code']) !== strtoupper((string) $package->code)) {
            abort(404);
        }

        return response()->json($service->createPackagePaymentIntent(
            $request->user(),
            $package,
            isset($validated['upgrade_from']) ? strtoupper((string) $validated['upgrade_from']) : null,
        ));
    }

    public function packageFromPayload(Request $request, TipTopPayService $service): JsonResponse
    {
        if (! config('safi.user_package_purchases_enabled', false)) {
            return response()->json([
                'message' => self::PACKAGE_PURCHASE_DISABLED_MESSAGE,
            ], 403);
        }

        $validated = $request->validate([
            'package_code' => ['required', 'string', Rule::in(Package::PUBLIC_CODES)],
            'upgrade_from' => ['sometimes', 'nullable', 'string'],
        ]);

        $package = Package::query()
            ->where('code', strtoupper($validated['package_code']))
            ->firstOrFail();

        return response()->json($service->createPackagePaymentIntent(
            $request->user(),
            $package,
            isset($validated['upgrade_from']) ? strtoupper((string) $validated['upgrade_from']) : null,
        ));
    }
}
