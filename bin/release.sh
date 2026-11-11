#!/bin/sh
# Zabalí pluginy a tému do dist/ (zip s priečinkom pluginu v koreni, ako ho inštaluje WordPress).
# Pred vydaním: hub/ je hlavná kópia — skopíruj ju do plugins/*/hub (okrem gruppa-cms).
set -e
cd "$(dirname "$0")/.."
mkdir -p dist
for d in plugins/*/; do
	slug=$(basename "$d")
	file="$d$slug.php"
	ver=$(grep -m1 "Version:" "$file" | sed 's/.*Version:[[:space:]]*//')
	(cd plugins && zip -qr "../dist/$slug-$ver.zip" "$slug" -x '*.DS_Store')
	echo "dist/$slug-$ver.zip"
done
ver=$(grep -m1 "^Version:" themes/gruppa/style.css | sed 's/Version:[[:space:]]*//')
(cd themes && zip -qr "../dist/gruppa-theme-$ver.zip" gruppa -x '*.DS_Store')
echo "dist/gruppa-theme-$ver.zip"
