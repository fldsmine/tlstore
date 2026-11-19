<?php

namespace App\Features\System\Http\Controllers\Web;

use App\Application\Support\Media;
use App\Models\AppVersion;
use Illuminate\View\View;

final class LandingPageController
{
    public function __invoke(): View
    {
        $publishedVersions = AppVersion::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('build_number')
            ->get()
            ->unique('platform')
            ->keyBy('platform');

        $android = $publishedVersions->get('android');
        $ios = $publishedVersions->get('ios');

        return view('landing', [
            'appName' => config('app.name', 'TLStore'),
            'webAppUrl' => rtrim(config('app.web_url', 'https://web.tlstore.org'), '/'),
            'androidUrl' => $android?->play_store_url
                ?: $android?->direct_download_url
                ?: Media::url($android?->direct_download_path),
            'iosUrl' => $ios?->app_store_url,
        ]);
    }
}
