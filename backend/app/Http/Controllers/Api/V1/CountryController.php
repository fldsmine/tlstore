<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CountryResource;
use App\Models\Country;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /** Public — the registration form needs the list of supported countries. */
    public function index(): JsonResponse
    {
        $countries = Country::query()->active()->orderBy('name')->get();

        return ApiResponse::success('Countries retrieved successfully.', [
            'countries' => CountryResource::collection($countries),
        ]);
    }
}
