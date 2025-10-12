param([Parameter(Mandatory=$true)][string]$Raw)
if (-not $env:SECRET) { throw "SECRET not set" }
$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [Text.Encoding]::UTF8.GetBytes($env:SECRET)
$bytes = [Text.Encoding]::UTF8.GetBytes($Raw)
$hash = $hmac.ComputeHash($bytes)
[Convert]::ToBase64String($hash)
