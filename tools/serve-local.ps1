param(
    [string]$Database = "sigra_local",
    [string]$User = "root",
    [string]$Password = "",
    [int]$Port = 8080
)

# Previsualización local sin tocar el .env de producción.
# Uso: .\tools\serve-local.ps1
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$backup = Join-Path $env:TEMP ("sigra-env-prod-" + [guid]::NewGuid().ToString("N") + ".bak")
$startedMysql = $false

if (Test-Path ".env") {
    Copy-Item ".env" $backup -Force
}

try {
    $key = -join ((1..64) | ForEach-Object { "{0:x}" -f (Get-Random -Minimum 0 -Maximum 16) })
    $envLocal = @"
CI_ENVIRONMENT = development

app.baseURL = 'http://localhost:$Port/'
APP_API_TOKEN = 'local-test-key'

database.default.hostname = localhost
database.default.database = $Database
database.default.username = $User
database.default.password = '$Password'
database.default.DBDriver = MySQLi
database.default.DBPrefix = ''
database.default.port = 3306

encryption.key = 'hex:$key'

red.minDevices = 3
red.minAlertMagnitude = 3.0
red.minAlertConfidence = 0.45
red.fcmEnabled = false
"@
    [System.IO.File]::WriteAllText((Join-Path $root ".env"), $envLocal, (New-Object System.Text.UTF8Encoding($false)))

    if (-not (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue)) {
        $mysqld = "C:\xampp\mysql\bin\mysqld.exe"
        if (Test-Path $mysqld) {
            Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini", "--standalone" -WindowStyle Hidden
            Start-Sleep -Seconds 4
            $startedMysql = $true
        } else {
            Write-Warning "MySQL no está corriendo y no se encontró C:\xampp\mysql\bin\mysqld.exe."
        }
    }

    $mysql = "C:\xampp\mysql\bin\mysql.exe"
    if (Test-Path $mysql) {
        $mysqlArgs = @("-u", $User)
        if ($Password -ne "") { $mysqlArgs += "-p$Password" }
        $mysqlArgs += @("-e", "CREATE DATABASE IF NOT EXISTS $Database CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;")
        & $mysql @mysqlArgs | Out-Null
    }

    php spark migrate --all
    Write-Host "SIGRA local en http://localhost:$Port" -ForegroundColor Cyan
    Write-Host "Red en vivo: http://localhost:$Port/red" -ForegroundColor Cyan
    php spark serve --host localhost --port $Port
} finally {
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
        Where-Object { $_.CommandLine -match "spark serve" -or $_.CommandLine -match "-S `"localhost:$Port`"" } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

    if ($startedMysql) {
        Get-Process mysqld -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    }

    if (Test-Path $backup) {
        Copy-Item $backup ".env" -Force
        Remove-Item $backup -Force
        Write-Host "`.env` de producción restaurado." -ForegroundColor Green
    }
}
