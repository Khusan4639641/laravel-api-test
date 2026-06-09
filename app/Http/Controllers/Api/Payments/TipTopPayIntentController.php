<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\TipTopPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipTopPayIntentController extends Controller
{
    public function __invoke(Request $request, Order $order, TipTopPayService $service): JsonResponse
    {
        $intent = $service->createIntent($order, $request->user());

        return response()->json(['intent' => $intent] + $intent);
    }
}
