#!/usr/bin/env bash
#
# Build the Flutter web app for `public/web/` (POSIX counterpart of
# build_web.ps1, ERR-17).
#
#   ./review/tool/build_web.sh                # production defaults
#   API_BASE_URL=https://staging.example/api/v1/ BASE_URL=https://staging.example \
#       ./review/tool/build_web.sh            # a different deployment
#
# The build lands in review/build/web. Publish it with:
#
#   rsync -a --delete review/build/web/ public/web/
#
# (keep public/web/firebase-messaging-sw.js.template in the repository; it is
# copied into the build by this script).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Defaults match build_web.ps1 so both scripts produce the same bundle.
: "${API_BASE_URL:=https://tlstore.org/api/v1/}"
: "${BASE_URL:=https://tlstore.org}"
: "${FIREBASE_WEB_API_KEY:=AIzaSyA8LT6sW1iS8UQWTR4soGezRD2uHfKQhE4}"
: "${FIREBASE_WEB_APP_ID:=1:1068160277012:web:20fd2e58c0ce62a60fe501}"
: "${FIREBASE_WEB_AUTH_DOMAIN:=tl-store-application.firebaseapp.com}"
: "${FIREBASE_WEB_VAPID_KEY:=BPomcCkDgKhGniOFRtuusM47zLQbzP2CgX5jWa2xTytNjSbTqx5VE0l6ZshpMCe_a3zjAAg67pQ7k2PE09QXdeM}"

if ! command -v flutter >/dev/null 2>&1; then
    echo "build_web.sh: the Flutter SDK is not on PATH (https://docs.flutter.dev/get-started/install)." >&2
    exit 1
fi

flutter build web \
    --release \
    --base-href=/web/ \
    --dart-define=API_BASE_URL="$API_BASE_URL" \
    --dart-define=BASE_URL="$BASE_URL" \
    --dart-define=FIREBASE_WEB_API_KEY="$FIREBASE_WEB_API_KEY" \
    --dart-define=FIREBASE_WEB_APP_ID="$FIREBASE_WEB_APP_ID" \
    --dart-define=FIREBASE_WEB_AUTH_DOMAIN="$FIREBASE_WEB_AUTH_DOMAIN" \
    --dart-define=FIREBASE_WEB_VAPID_KEY="$FIREBASE_WEB_VAPID_KEY"

# The service worker is a template so the Firebase values are injected at
# build time instead of being committed as plaintext configuration.
python3 - "$ROOT/web/firebase-messaging-sw.js.template" "$ROOT/build/web/firebase-messaging-sw.js" <<'PY'
import json
import os
import sys

template_path, worker_path = sys.argv[1], sys.argv[2]
with open(template_path, 'r', encoding='utf-8') as handle:
    worker = handle.read()

replacements = {
    '__FIREBASE_WEB_API_KEY__': os.environ['FIREBASE_WEB_API_KEY'],
    '__FIREBASE_WEB_AUTH_DOMAIN__': os.environ['FIREBASE_WEB_AUTH_DOMAIN'],
    '__FIREBASE_WEB_APP_ID__': os.environ['FIREBASE_WEB_APP_ID'],
}

for placeholder, value in replacements.items():
    worker = worker.replace(placeholder, json.dumps(value))

with open(worker_path, 'w', encoding='utf-8') as handle:
    handle.write(worker)
PY

cat <<EOF

Built with API_BASE_URL=$API_BASE_URL
Output:      $ROOT/build/web
Publish:     rsync -a --delete "$ROOT/build/web/" "$ROOT/../public/web/"
EOF
