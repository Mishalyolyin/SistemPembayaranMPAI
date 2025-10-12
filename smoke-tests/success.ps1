$BASE_URL = $env:BASE_URL; if (-not $BASE_URL) { $BASE_URL = "http://localhost:8000" }
$ENDPOINT = $env:ENDPOINT; if (-not $ENDPOINT) { $ENDPOINT = "/api/webhooks/bri/payment" }
$URL = "$BASE_URL$ENDPOINT"
$SIG_HEADER = $env:SIG_HEADER; if (-not $SIG_HEADER) { $SIG_HEADER = "X-Signature" }
$TS_HEADER = $env:TS_HEADER; if (-not $TS_HEADER) { $TS_HEADER = "X-Timestamp" }
$TOKEN = $env:TOKEN
$BRIVA_NO = $env:BRIVA_NO; if (-not $BRIVA_NO) { $BRIVA_NO = "39012345" }
$CUST_CODE = $env:CUST_CODE; if (-not $CUST_CODE) { $CUST_CODE = "2023123456" }

$NOW = [int][double]::Parse((Get-Date -UFormat %s))
$JSEQ = if ($env:JSEQ_OVERRIDE) { $env:JSEQ_OVERRIDE } else { "DEV-SMOKE-OK-001" }

$paidAt = (Get-Date).ToString("yyyy-MM-ddTHH:mm:ssK")
$payload = @{
  journalSeq = $JSEQ
  amount = 1500000
  custCode = $CUST_CODE
  bankCode = "390"
  brivaNo = $BRIVA_NO
  paidAt = $paidAt
} | ConvertTo-Json -Compress

if ("success" -eq "out_of_skew") {
  $NOW = $NOW - 3600
}

if ("success" -eq "bad_sig") {
  $sig = "definitely-wrong-signature=="
} else {
  $sig = & ./hmac.ps1 -Raw $payload
}

$headers = @{
  "Content-Type" = "application/json"
  $SIG_HEADER = $sig
  $TS_HEADER = $NOW
}
if ($TOKEN) {
  $headers["Authorization"] = "Bearer $TOKEN"
}

Invoke-WebRequest -Uri $URL -Method POST -Headers $headers -Body $payload
