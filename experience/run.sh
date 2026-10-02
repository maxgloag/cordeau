#!/usr/bin/env bash
# Expérience B — Rust : lit une demande de devis JSON sur stdin, écrit le JSON
# de sortie sur stdout et propage le code de sortie (0 succès / 1 erreurs).
#
# Utilise le binaire release déjà compilé (aucune compilation à l'appel).
# Prérequis, une seule fois depuis la racine du worktree :
#   cargo build --release --manifest-path spikes/rust-devis/Cargo.toml
set -u
racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
binaire="$racine/spikes/rust-devis/target/release/totaux-devis"
if [ ! -x "$binaire" ]; then
  echo "run.sh : binaire absent ($binaire) — lancer d'abord : cargo build --release --manifest-path spikes/rust-devis/Cargo.toml" >&2
  exit 2
fi
exec "$binaire"
