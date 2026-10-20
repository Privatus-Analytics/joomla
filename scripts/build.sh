#!/bin/sh
# Builds dist/plg_system_privatusanalytics-<version>.zip, the file Joomla installs.
set -e
cd "$(dirname "$0")/.."
VERSION=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' plugin/privatusanalytics.xml | head -1)
OUT="dist/plg_system_privatusanalytics-$VERSION.zip"
mkdir -p dist
rm -f "$OUT"
# Fixed timestamps (-X drops extra attributes) keep the zip reproducible.
find plugin -exec touch -t 202610200000 {} +
(cd plugin && find . -type f ! -name .DS_Store | LC_ALL=C sort | sed 's:^\./::' | zip -X -q "../$OUT" -@)
echo "Built $OUT"
unzip -l "$OUT"
