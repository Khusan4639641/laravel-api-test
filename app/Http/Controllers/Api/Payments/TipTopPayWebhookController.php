<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\TipTopPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipTopPayWebhookController extends Controller
{
    public function check(Request $request, TipTopPayService $service): JsonResponse
    {
        return response()->json($service->handleCheck($request));
    }

    public function pay(Request $request, TipTopPayService $service): JsonResponse
    {
        return response()->json($service->handlePay($request));
    }

    public function fail(Request $request, TipTopPayService $service): JsonResponse
    {
        return response()->json($service->handleFail($request));
    }

    public function refund(Request $request, TipTopPayService $service): JsonResponse
    {
        return response()->json($service->handleRefund($request));
    }

    public function cancel(Request $request, TipTopPayService $service): JsonResponse
    {
        return response()->json($service->handleCancel($request));
    }
}
