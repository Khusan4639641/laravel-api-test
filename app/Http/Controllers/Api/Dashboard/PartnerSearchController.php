<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransferPartnerResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $limit = min(max((int) $request->integer('limit', 20), 1), 30);

        if (mb_strlen($query) < 2 && ! ctype_digit($query)) {
            return response()->json(['data' => []]);
        }

        $partners = User::query()
            ->with(['profile', 'currentPackage'])
            ->where('id', '!=', $request->user()->id)
            ->where('role', User::ROLE_USER)
            ->where('account_status', 'active')
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('login', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhereHas('profile', fn (Builder $profileQuery) => $profileQuery->where('phone', 'like', "%{$query}%"));

                if (ctype_digit($query)) {
                    $builder->orWhere('id', (int) $query);
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => TransferPartnerResource::collection($partners),
        ]);
    }
}
