#!/usr/bin/env bash
# bin/get-plugin-zip.sh <branch>   Download the zip CI built for the branch's latest green run.
# bin/get-plugin-zip.sh --build    Build the zip locally from the current checkout.
# bin/get-plugin-zip.sh --release  Download the latest released zip.
set -euo pipefail

REPO="rtCamp/publishio"
OUT="artifacts/zips"
mkdir -p "$OUT"
rm -f "$OUT/publishio.zip"

case "${1:-}" in
  --build)
    composer install --no-dev --prefer-dist --no-progress
    npm ci
    npm run build
    npm run plugin-zip
    mv publishio.zip "$OUT/publishio.zip"
    echo "Note: dev Composer packages were removed; run 'composer install' again before PHPUnit/PHPStan."
    ;;
  --release)
    curl -fsSL -o "$OUT/publishio.zip" "https://github.com/$REPO/releases/latest/download/publishio.zip"
    ;;
  "")
    echo "Usage: $0 <branch> | --build | --release" >&2
    exit 1
    ;;
  *)
    BRANCH="$1"
    RUN_ID=$(gh run list --repo "$REPO" --workflow ci.yml --branch "$BRANCH" --status success \
      --limit 1 --json databaseId -q '.[0].databaseId')
    if [[ -z "$RUN_ID" ]]; then
      echo "No successful CI run found for branch '$BRANCH'." >&2
      exit 1
    fi
    NAME=$(gh api "repos/$REPO/actions/runs/$RUN_ID/artifacts" \
      -q '.artifacts[] | select(.name | startswith("publishio-")) | .name' | head -n1)
    gh run download "$RUN_ID" --repo "$REPO" -n "$NAME" -D "$OUT"
    echo "Run: https://github.com/$REPO/actions/runs/$RUN_ID  Artifact: $NAME"
    ;;
esac

echo "Zip ready: $OUT/publishio.zip"
