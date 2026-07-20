#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DEST_DIR="${PROJECT_DIR}/public/uploads"
SOURCE_PATH="${1:-}"

usage() {
  cat <<'EOF'
Usage:
  scripts/setup-media.sh /path/to/backup.zip
  scripts/setup-media.sh /path/to/uploads-directory

Description:
  Copies media files into public/uploads without committing them to git.
EOF
}

if [[ -z "$SOURCE_PATH" ]]; then
  usage
  exit 1
fi

mkdir -p "$DEST_DIR"

copy_from_dir() {
  local src_dir="$1"

  if command -v rsync >/dev/null 2>&1; then
    rsync -a "$src_dir"/ "$DEST_DIR"/
  else
    cp -R "$src_dir"/. "$DEST_DIR"/
  fi
}

if [[ -d "$SOURCE_PATH" ]]; then
  copy_from_dir "$SOURCE_PATH"
elif [[ -f "$SOURCE_PATH" ]]; then
  if [[ "$SOURCE_PATH" != *.zip ]]; then
    echo "Unsupported file type: $SOURCE_PATH"
    echo "Expected a directory or a .zip archive."
    exit 1
  fi

  TMP_DIR="$(mktemp -d)"
  trap 'rm -rf "$TMP_DIR"' EXIT

  unzip -q "$SOURCE_PATH" -d "$TMP_DIR"

  if [[ -d "$TMP_DIR/public/uploads" ]]; then
    copy_from_dir "$TMP_DIR/public/uploads"
  elif [[ -d "$TMP_DIR/uploads" ]]; then
    copy_from_dir "$TMP_DIR/uploads"
  else
    CANDIDATE="$(find "$TMP_DIR" -type d -name uploads | head -n 1 || true)"
    if [[ -z "$CANDIDATE" ]]; then
      echo "No uploads directory found inside archive."
      exit 1
    fi
    copy_from_dir "$CANDIDATE"
  fi
else
  echo "Path not found: $SOURCE_PATH"
  exit 1
fi

TOTAL_FILES="$(find "$DEST_DIR" -type f ! -name '.gitignore' | wc -l | tr -d ' ')"
echo "Media sync complete. Files in public/uploads: $TOTAL_FILES"

