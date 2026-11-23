<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ActivatePackageRequest;
use App\Http\Resources\Api\V1\PackageResource;
use App\Http\Resources\Api\V1\PackageSummaryResource;
use App\Models\Package;
use App\Services\TokenRedemptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    /** Public catalogue — prices resolve for the requester's region when authenticated. */
    public function index(): JsonResponse
    {
        $packages = Package::query()->active()->ordered()->get();

        return ApiResponse::success('Packages retrieved successfully.', [
            'packages' => PackageResource::collection($packages),
        ]);
    }

    public function activate(ActivatePackageRequest $request, TokenRedemptionService $redemption): JsonResponse
    {
        $package = $redemption->activate(
            $request->user(),
            (string) $request->validated('package_id'),
            (string) $request->validated('token'),
        );

        return ApiResponse::success('Your account has been activated.', [
            'package' => new PackageSummaryResource($package),
        ]);
    }

    public function upgrade(ActivatePackageRequest $request, TokenRedemptionService $redemption): JsonResponse
    {
        $package = $redemption->upgrade(
            $request->user(),
            (string) $request->validated('package_id'),
            (string) $request->validated('token'),
        );

        return ApiResponse::success('Your package has been upgraded.', [
            'package' => new PackageSummaryResource($package),
        ]);
    }
}
