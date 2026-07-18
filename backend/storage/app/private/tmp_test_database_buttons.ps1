$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:8000/api/v2/admin'
$loginBody = @{ username = 'cerbo'; password = 'Cheng2008li#7111' } | ConvertTo-Json
$login = Invoke-RestMethod -Method Post -Uri "$base/login" -ContentType 'application/json' -Body $loginBody
$token = $login.data.token
if (-not $token) { $token = $login.data.access_token }
if (-not $token) {
  $login | ConvertTo-Json -Depth 8
  throw 'login token missing'
}
$headers = @{ Authorization = "Bearer $token"; Accept = 'application/json' }
Write-Output "LOGIN_OK token_len=$($token.Length)"

$status = Invoke-RestMethod -Method Get -Uri "$base/database/status" -Headers $headers
Write-Output ("STATUS_OK database={0} tables={1} rows={2} size_mb={3}" -f $status.data.database, $status.data.total_count, $status.data.total_rows, $status.data.total_size_mb)
$first = $status.data.list | Select-Object -First 1
Write-Output ("FIRST_TABLE name={0} rows={1} size_mb={2}" -f $first.name, $first.rows, $first.size_mb)

$target = ($status.data.list | Where-Object { $_.rows -lt 1000 } | Select-Object -First 1)
if (-not $target) { $target = $first }
$optBody = @{ tables = @($target.name) } | ConvertTo-Json
$opt = Invoke-RestMethod -Method Post -Uri "$base/database/optimizations" -Headers $headers -ContentType 'application/json' -Body $optBody
Write-Output ("OPTIMIZE_OK status={0} message={1} optimized={2}" -f $opt.data.status, $opt.message, ($opt.data.detail.optimized_tables -join ','))

$backupPath = Join-Path $env:TEMP ("caiwu_db_backup_test_{0}.sql" -f (Get-Date -Format 'yyyyMMddHHmmss'))
$backupResp = Invoke-WebRequest -Method Post -Uri "$base/database/backups" -Headers @{ Authorization = "Bearer $token"; Accept = 'application/sql, application/json' } -OutFile $backupPath -PassThru
$backupSize = (Get-Item $backupPath).Length
$head = Get-Content -LiteralPath $backupPath -TotalCount 5
Write-Output ("BACKUP_OK http={0} size={1} file={2}" -f $backupResp.StatusCode, $backupSize, $backupPath)
Write-Output ("BACKUP_HEAD {0}" -f ($head -join ' | '))
if ($backupSize -le 0) { throw 'backup file empty' }
Write-Output 'ALL_BUTTON_TESTS_PASSED'
