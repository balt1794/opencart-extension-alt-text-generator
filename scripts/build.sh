#!/usr/bin/env bash
# Builds the two packages you upload under Extensions > Installer:
#   altai.ocmod.zip      for OpenCart 4.x (the name of the ZIP is the folder it installs into)
#   altai_oc3.ocmod.zip  for OpenCart 3.x
set -euo pipefail
cd "$(dirname "$0")/.."
rm -f altai.ocmod.zip altai_oc3.ocmod.zip alt_text_generator_ai.ocmod.zip
(cd oc4 && zip -qr ../altai.ocmod.zip install.json admin catalog)
(cd oc3 && zip -qr ../altai_oc3.ocmod.zip upload)
echo "Built altai.ocmod.zip (OpenCart 4.x) and altai_oc3.ocmod.zip (OpenCart 3.x)"
