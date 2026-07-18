#!/usr/bin/env bash
#
# Pré-remplit les pages du site avec les patterns du thème (aucune page vide).
# Idempotent : ne touche jamais une page dont le contenu existe déjà.
# Prérequis : ./scripts/setup-structure.sh déjà exécuté.
#
set -euo pipefail
cd "$(dirname "$0")/.."

# Le conteneur wp-cli ne voit que wp-content/ : copie temporaire du script.
TMP=wp-content/jcmv-seed-tmp.php
cp scripts/seed-content.php "$TMP"
trap 'rm -f "$TMP"' EXIT

docker compose run --rm -T wpcli eval-file "$TMP"
