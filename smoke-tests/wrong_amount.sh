#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8000}"
ENDPOINT="${ENDPOINT:-/api/webhooks/bri/payment}"
URL="$BASE_URL$ENDPOINT"
SIG_HEADER="${SIG_HEADER:-X-Signature}"
TS_HEADER="${TS_HEADER:-X-Timestamp}"
TOKEN="${TOKEN:-}"
BRIVA_NO="${BRIVA_NO:-39012345}"
CUST_CODE="${CUST_CODE:-2023123456}"

NOW=$(date +%s)
JSEQ="${JSEQ_OVERRIDE:-DEV-SMOKE-WRONGAMT-001}"

PAYLOAD=$(cat <<JSON
{"journalSeq":"$JSEQ","amount":999999,"custCode":"$CUST_CODE","bankCode":"390","brivaNo":"$BRIVA_NO","paidAt":"$(date -Ins)"}
JSON
)

if [[ "success" == "out_of_skew" ]]; then
  # mundurkan 3600 detik (1 jam) biar di-drop kalau skew ketat
  NOW=$((NOW - 3600))
fi

if [[ "success" == "bad_sig" ]]; then
  SIG="definitely-wrong-signature=="
else
  SIG=$(./hmac.sh "$PAYLOAD")
fi

AUTH=()
if [[ -n "$TOKEN" ]]; then
  AUTH=(-H "Authorization: Bearer $TOKEN")
fi

curl -i -sS "$URL" \
  -H "Content-Type: application/json" \
  -H "$SIG_HEADER: $SIG" \
  -H "$TS_HEADER: $NOW" \
  "${AUTH[@]}" \
  --data "$PAYLOAD"
