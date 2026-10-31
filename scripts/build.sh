#!/usr/bin/env bash
# Builds altai.ocmod.zip, the file you upload under Extensions > Installer in OpenCart 4.
# The name of the ZIP is the name of the folder the extension is installed into.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -f altai.ocmod.zip
zip -qr altai.ocmod.zip install.json admin catalog
echo "Built altai.ocmod.zip"
