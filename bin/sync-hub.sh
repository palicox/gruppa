#!/bin/sh
# Skopíruje hub/ (hlavná kópia) do všetkých pluginov Gruppa Tools.
set -e
cd "$(dirname "$0")/.."
for d in plugins/*/; do
	[ -d "$d/hub" ] || continue
	rm -rf "$d/hub" && cp -r hub "$d/hub"
	echo "hub → $d"
done
