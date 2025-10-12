#!/usr/bin/env bash
set -euo pipefail

RAW="$1"
SECRET="${SECRET:?SECRET not set}"

# HMAC-SHA256 base64 of RAW
# macOS: use -binary to avoid hex; Linux: same
SIGNATURE=$(printf "%s" "$RAW" | openssl dgst -sha256 -hmac "$SECRET" -binary | openssl base64)
printf "%s" "$SIGNATURE"
