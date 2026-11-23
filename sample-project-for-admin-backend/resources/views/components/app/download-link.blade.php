@props([
    'href',
    'filename' => null,
    'mime' => null,
])

{{--
    A download link that works in every surface the dashboard runs in.

    Renders a real anchor (so it still downloads in a plain browser, and is keyboard/middle-click
    friendly) and marks it for the shared download bridge in resources/js/downloads.js, which
    re-routes it through Telegram's downloadFile inside the Mini App and through the Android app's
    native downloader inside the WebView. The filename/MIME are passed along so those native
    surfaces can save the file under the right name and type.
--}}
<a
    href="{{ $href }}"
    download
    data-nextel-download="{{ $filename }}"
    data-nextel-mime="{{ $mime }}"
    {{ $attributes }}
>
    {{ $slot }}
</a>
