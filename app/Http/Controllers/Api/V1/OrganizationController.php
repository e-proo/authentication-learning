<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()
            ->memberships()
            ->where('memberships.status', 'active')
            ->whereHas('organization', fn ($query) => $query->where('is_active', true))
            ->with(['organization:id,name,slug,kind,commerce_mode', 'branch:id,organization_id,name,code'])
            ->get()
            ->map(fn ($membership) => [
                'membership_id' => $membership->id,
                'organization' => $membership->organization,
                'branch' => $membership->branch,
                'is_default' => $membership->is_default,
                'roles' => $membership->roles()->pluck('slug'),
            ])
            ->values();

        return response()->json(['data' => $organizations]);
    }
}
