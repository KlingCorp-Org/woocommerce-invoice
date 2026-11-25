#!/usr/bin/env bash
# Builds an installable zip: dist/order-documents-<version>.zip (the version comes from the plugin header).
# Needs: composer, node/npm, php, and Strauss (STRAUSS_RUN, default /opt/tools/strauss-run.php).
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$(pwd)"
STRAUSS_RUN="${STRAUSS_RUN:-/opt/tools/strauss-run.php}"

composer install --no-interaction --prefer-dist --no-dev -q || composer install --no-interaction --prefer-source --no-dev -q
php "$STRAUSS_RUN" >/dev/null
php bin/patch-vendor.php
npm ci --no-audit --no-fund --loglevel=error
npm run build >/dev/null
if command -v msgfmt >/dev/null; then
	for po in languages/*.po; do msgfmt -o "${po%.po}.mo" "$po" 2>/dev/null || true; done
fi

VERSION="$(sed -n 's/^ \* Version: *\([0-9][0-9.]*\).*/\1/p' order-documents.php | head -1)"
if [ -z "$VERSION" ]; then
	echo "Could not read the version from order-documents.php" >&2
	exit 1
fi
ZIP="order-documents-$VERSION.zip"

rm -rf dist && mkdir -p dist/order-documents
rsync -a ./ dist/order-documents/ \
	--exclude '.git' --exclude 'dist' --exclude 'node_modules' --exclude 'vendor' --exclude 'tests' \
	--exclude 'bin' --exclude 'package.json' --exclude 'package-lock.json' --exclude 'composer.lock' \
	--exclude 'phpunit.xml.dist' --exclude 'composer.json' --exclude '.phpunit.result.cache' --exclude '.gitignore' --exclude 'assets/editor/src' --exclude '*.zip'
# The folder inside the zip stays "order-documents", so WordPress updates the installed plugin in place.
(cd dist && zip -qr "$ZIP" order-documents)
echo "Built $ROOT/dist/$ZIP ($(du -h "dist/$ZIP" | cut -f1))"
