$ErrorActionPreference = 'Stop'

$firebaseDefaults = @{
    FIREBASE_WEB_API_KEY = 'AIzaSyA8LT6sW1iS8UQWTR4soGezRD2uHfKQhE4'
    FIREBASE_WEB_APP_ID = '1:1068160277012:web:20fd2e58c0ce62a60fe501'
    FIREBASE_WEB_AUTH_DOMAIN = 'tl-store-application.firebaseapp.com'
    FIREBASE_WEB_VAPID_KEY = 'BPomcCkDgKhGniOFRtuusM47zLQbzP2CgX5jWa2xTytNjSbTqx5VE0l6ZshpMCe_a3zjAAg67pQ7k2PE09QXdeM'
}

foreach ($name in $firebaseDefaults.Keys) {
    if ([string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable($name))) {
        [Environment]::SetEnvironmentVariable($name, $firebaseDefaults[$name], 'Process')
    }
}

$flutterArgs = @(
    'build', 'web', '--release', '--base-href=/web/'
)
foreach ($name in $firebaseDefaults.Keys) {
    $value = [Environment]::GetEnvironmentVariable($name)
    $flutterArgs += "--dart-define=$name=$value"
}

& flutter @flutterArgs
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

$templatePath = Join-Path $PSScriptRoot '..\web\firebase-messaging-sw.js.template'
$workerPath = Join-Path $PSScriptRoot '..\build\web\firebase-messaging-sw.js'
$worker = Get-Content -LiteralPath $templatePath -Raw
$worker = $worker.Replace('__FIREBASE_WEB_API_KEY__', (ConvertTo-Json $env:FIREBASE_WEB_API_KEY -Compress))
$worker = $worker.Replace('__FIREBASE_WEB_AUTH_DOMAIN__', (ConvertTo-Json $env:FIREBASE_WEB_AUTH_DOMAIN -Compress))
$worker = $worker.Replace('__FIREBASE_WEB_APP_ID__', (ConvertTo-Json $env:FIREBASE_WEB_APP_ID -Compress))
[System.IO.File]::WriteAllText(
    $workerPath,
    $worker,
    [System.Text.UTF8Encoding]::new($false)
)
