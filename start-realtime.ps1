<#
    start-realtime.ps1 — Khoi dong 2 dich vu realtime can cho du an nay:
      1. Laravel Reverb  (khoa/mo cho admin khi dang tu van khach - TimeslotHoldService)
      2. Node Socket.IO  (slot/hold/gia/booking/chat cho khach hang - websocket/server.js)

    Ca 2 KHONG tu chay theo `php artisan serve`/Herd - phai tu khoi dong moi lan lam viec.
    Chay: .\start-realtime.ps1  (tu thu muc goc du an)

    Reverb dung --hostname=127.0.0.1 de KHONG tu bat TLS (Herd tu do cert theo hostname that,
    kien truc du an la Reverb luon chay plain HTTP, nginx lo phan TLS - xem config/reverb.php).
#>

$ErrorActionPreference = 'Stop'

function Read-DotEnvValue {
    param([string]$Key, [string]$Default = '')
    $line = Get-Content .env | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if (-not $line) { return $Default }
    return ($line -split '=', 2)[1].Trim('"')
}

$reverbPort = Read-DotEnvValue -Key 'REVERB_SERVER_PORT' -Default '8081'
$wsPort     = Read-DotEnvValue -Key 'WS_PORT' -Default '3001'
$wsKey      = Read-DotEnvValue -Key 'WS_INTERNAL_KEY'
$wsOrigin   = Read-DotEnvValue -Key 'WS_ALLOWED_ORIGIN' -Default 'https://365sport-cms.test'

if (-not $wsKey) {
    Write-Host "Thieu WS_INTERNAL_KEY trong .env - dung lai." -ForegroundColor Red
    exit 1
}

$projectRoot = $PSScriptRoot

Write-Host "Khoi dong Reverb (port $reverbPort)..." -ForegroundColor Cyan
Start-Process powershell -WorkingDirectory $projectRoot -ArgumentList @(
    '-NoExit', '-Command',
    "php artisan reverb:start --host=0.0.0.0 --port=$reverbPort --hostname=127.0.0.1"
)

Write-Host "Khoi dong Socket.IO (port $wsPort)..." -ForegroundColor Cyan
Start-Process powershell -WorkingDirectory $projectRoot -ArgumentList @(
    '-NoExit', '-Command',
    "`$env:WS_PORT='$wsPort'; `$env:WS_INTERNAL_KEY='$wsKey'; `$env:WS_ALLOWED_ORIGIN='$wsOrigin'; node websocket/server.js"
)

Write-Host "Da mo 2 cua so rieng cho Reverb va Socket.IO - dong cua so do de tat dich vu tuong ung." -ForegroundColor Green
