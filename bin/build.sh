#!/usr/bin/env bash
# Builds an installable zip: dist/order-documents.zip
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

rm -rf dist && mkdir -p dist/order-documents
rsync -a ./ dist/order-documents/ \
	--exclude '.git' --exclude 'dist' --exclude 'node_modules' --exclude 'vendor' --exclude 'tests' \
	--exclude 'bin' --exclude 'package.json' --exclude 'package-lock.json' --exclude 'composer.lock' \
	--exclude 'phpunit.xml.dist' --exclude 'composer.json' --exclude '.phpunit.result.cache' --exclude '.gitignore' --exclude 'assets/editor/src' --exclude '*.zip'
(cd dist && zip -qr order-documents.zip order-documents)
echo "Built $ROOT/dist/order-documents.zip ($(du -h dist/order-documents.zip | cut -f1))"
