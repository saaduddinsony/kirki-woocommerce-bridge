#!/usr/bin/env bash
# Build an installable zip: build/kirki-woocommerce-bridge-<version>.zip
# Files listed in .distignore (development files) are left out.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG=kirki-woocommerce-bridge
VERSION=$(sed -n 's/^ \* Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$ROOT/$SLUG.php" | head -1)
mkdir -p "$ROOT/build"
ZIP="$ROOT/build/$SLUG-$VERSION.zip"
python3 - "$ROOT" "$SLUG" "$ZIP" <<'PY'
import os, sys, zipfile
root, slug, out = sys.argv[1:]
ignore = [l.strip().strip('/') for l in open(os.path.join(root, '.distignore')) if l.strip() and not l.startswith('#')]
def skipped(rel):
    first = rel.split('/')[0]
    return first in ignore or rel in ignore
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for dirpath, dirs, files in os.walk(root):
        rel_dir = os.path.relpath(dirpath, root)
        rel_dir = '' if rel_dir == '.' else rel_dir
        dirs[:] = sorted(d for d in dirs if not skipped(os.path.join(rel_dir, d).lstrip('/')))
        for f in sorted(files):
            rel = os.path.join(rel_dir, f).lstrip('/')
            if skipped(rel):
                continue
            z.write(os.path.join(dirpath, f), slug + '/' + rel)
PY
echo "$ZIP"
