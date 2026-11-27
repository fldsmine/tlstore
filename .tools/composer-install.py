#!/usr/bin/env python3
"""Offline 'composer install' for this sandbox.

packagist.org / getcomposer.org are not reachable here, but every dist URL in
composer.lock points at api.github.com zipballs (allowed -> codeload.github.com).
This script downloads those zipballs, unpacks them into vendor/, and writes a
composer-compatible vendor/composer/installed.json so Laravel's PackageManifest
package discovery keeps working.

Usage: python3 .tools/composer-install.py [--dev]
"""
import json
import os
import shutil
import subprocess
import sys
import zipfile
from concurrent.futures import ThreadPoolExecutor

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
VENDOR = os.path.join(ROOT, "vendor")
TMP = os.path.join(ROOT, ".tools", "tmp", "dists")
TOKEN = subprocess.check_output(["gh", "auth", "token"], text=True).strip()


def fetch(pkg):
    url = pkg["dist"]["url"]
    dest = os.path.join(TMP, pkg["name"].replace("/", "__") + ".zip")
    if os.path.exists(dest) and os.path.getsize(dest) > 0:
        return dest
    r = subprocess.run(
        [
            "curl", "-sL", "--fail", "--retry", "3", "--max-time", "180",
            "-H", f"Authorization: Bearer {TOKEN}",
            "-o", dest, url,
        ],
        capture_output=True, text=True,
    )
    if r.returncode != 0:
        raise RuntimeError(f"download failed for {pkg['name']}: {r.stderr[:300]}")
    return dest


def unpack(item):
    pkg, is_dev = item
    name = pkg["name"]
    target = os.path.join(VENDOR, *name.split("/"))
    if os.path.isdir(target):
        return name, "cached"
    archive = fetch(pkg)
    staging = os.path.join(TMP, "x_" + name.replace("/", "__"))
    if os.path.isdir(staging):
        shutil.rmtree(staging)
    with zipfile.ZipFile(archive) as zf:
        zf.extractall(staging)
    roots = os.listdir(staging)
    src = os.path.join(staging, roots[0]) if len(roots) == 1 else staging
    os.makedirs(os.path.dirname(target), exist_ok=True)
    shutil.move(src, target)
    shutil.rmtree(staging, ignore_errors=True)
    return name, "ok"


def main():
    include_dev = "--dev" in sys.argv
    with open(os.path.join(ROOT, "composer.lock")) as fh:
        lock = json.load(fh)

    os.makedirs(TMP, exist_ok=True)
    os.makedirs(os.path.join(VENDOR, "composer"), exist_ok=True)

    items = [(p, False) for p in lock["packages"]]
    if include_dev:
        items += [(p, True) for p in lock["packages-dev"]]

    with ThreadPoolExecutor(max_workers=8) as pool:
        for name, status in pool.map(unpack, items):
            print(f"  {status:6} {name}", flush=True)

    # composer/composer's real ClassLoader (PSR-0/PSR-4/classmap implementation)
    cl_dest = os.path.join(VENDOR, "composer", "ClassLoader.php")
    if not os.path.exists(cl_dest):
        subprocess.run(
            ["curl", "-sL", "--fail", "-H", f"Authorization: Bearer {TOKEN}",
             "-o", cl_dest,
             "https://api.github.com/repos/composer/composer/contents/"
             "src/Composer/Autoload/ClassLoader.php?ref=2.9.2"],
            check=True,
        )

    packages = []
    for pkg, is_dev in items:
        rel = os.path.join("..", pkg["name"])
        packages.append({
            "name": pkg["name"],
            "version": pkg["version"],
            "version_normalized": pkg.get("version_normalized", ""),
            "type": pkg.get("type", "library"),
            "license": pkg.get("license", []),
            "authors": pkg.get("authors", []),
            "description": pkg.get("description", ""),
            "autoload": pkg.get("autoload", {}),
            "extra": pkg.get("extra", {}),
            "bin": pkg.get("bin", []),
            "install-path": rel,
            "dev": is_dev,
        })

    install_info = {"packages": packages, "dev": include_dev,
                    "dev-package-names": [p["name"] for p in lock["packages-dev"]]}
    with open(os.path.join(VENDOR, "composer", "installed.json"), "w") as fh:
        json.dump(install_info, fh, indent=4)
    print(f"installed {len(packages)} packages -> vendor/, wrote vendor/composer/installed.json")


if __name__ == "__main__":
    main()
