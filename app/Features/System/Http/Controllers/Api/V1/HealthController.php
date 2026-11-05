<?php

namespace App\Features\System\Http\Controllers\Api\V1;

use App\Application\Actions\System\CheckApiHealth;
use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __invoke(CheckApiHealth $checkApiHealth): JsonResponse
    {
        return response()->json($checkApiHealth->handle());
    }
}
