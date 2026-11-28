<?php

namespace App\Features\Identity\Http\Controllers\Api\V1;

use App\Application\Identity\AdminImpersonation;
use App\Exceptions\ImpersonationUnavailable;
use App\Features\Identity\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public exchange endpoint for the admin hand-off link.
 *
 * The Flutter web app opens at `/?impersonation=<code>`, posts the code here,
 * stores the returned user token as its session and removes the code from the
 * address bar. The endpoint is unauthenticated by design (the web tab has no
 * session yet) but the code is single use, short lived and rate limited.
 */
final class ImpersonationController
{
    public function redeem(Request $request, AdminImpersonation $impersonation): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:16', 'max:255'],
        ]);

        try {
            $session = $impersonation->redeem($data['code'], $request);
        } catch (ImpersonationUnavailable $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'data' => new UserResource($session['user']),
            'token' => $session['token'],
            'token_type' => 'Bearer',
            'expires_at' => $session['expires_at']->toISOString(),
            'impersonation' => [
                'admin' => [
                    'id' => $session['admin']->id,
                    'full_name' => $session['admin']->full_name,
                    'email' => $session['admin']->email,
                ],
                'expires_at' => $session['expires_at']->toISOString(),
            ],
        ]);
    }
}
