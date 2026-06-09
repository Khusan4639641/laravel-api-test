<?php

namespace App\Http\Controllers\Api\PublicApi;

use App\Http\Controllers\Controller;
use App\Support\LegalSettings;
use Illuminate\Http\JsonResponse;

class LegalSettingsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'settings' => LegalSettings::publicValues(),
        ]);
    }
}
