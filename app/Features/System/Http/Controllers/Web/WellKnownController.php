<?php

namespace App\Features\System\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;

/**
 * App-link verification files (ERR-28).
 *
 * Android reads `/.well-known/assetlinks.json` and iOS reads
 * `/.well-known/apple-app-site-association` before a universal link can open
 * the app. Both used to be missing, so every `autoVerify` link silently fell
 * back to the browser. The identifiers come from the signing setup
 * (`ANDROID_SHA256_CERT_FINGERPRINTS`, `IOS_APP_ID`); until they are set the
 * endpoint answers 404 rather than serving an unusable file.
 */
final class WellKnownController
{
    public function assetLinks(): JsonResponse
    {
        $fingerprints = array_values((array) config('trustlens.deep_links.android_sha256_fingerprints', []));
        abort_if($fingerprints === [], 404, 'Android app links are not configured.');

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => (string) config('trustlens.deep_links.android_package'),
                'sha256_cert_fingerprints' => array_values(array_map(
                    static fn ($fingerprint): string => strtoupper(trim((string) $fingerprint)),
                    $fingerprints,
                )),
            ],
        ]], 200, ['Content-Type' => 'application/json']);
    }

    public function appleAppSiteAssociation(): JsonResponse
    {
        $appId = trim((string) config('trustlens.deep_links.ios_app_id', ''));
        abort_if($appId === '', 404, 'iOS universal links are not configured.');

        return response()->json([
            'applinks' => [
                'details' => [[
                    'appID' => $appId,
                    'paths' => array_values((array) config('trustlens.deep_links.ios_paths', ['*'])),
                ]],
            ],
        ], 200, ['Content-Type' => 'application/json']);
    }
}
