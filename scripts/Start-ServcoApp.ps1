# Start-ServcoApp.ps1
# PowerShell script to start ServcoApp on different port sets for parallel development
#
# Usage:
#   Start-ServcoApp          # Default ports (9000, 8080, 8081, 3306)
#   Start-ServcoApp 1        # Instance 1 ports (9001, 8180, 8181, 3316)
#   Start-ServcoApp 2        # Instance 2 ports (9002, 8280, 8281, 3326)
#
# Add to your PowerShell profile for alias:
#   function servco { & "C:\Users\thess\DockerProjects\ServcoApp\scripts\Start-ServcoApp.ps1" $args }

param(
    [Parameter(Position=0)]
    [ValidateRange(0,9)]
    [int]$Instance = 0
)

$ProjectRoot = "C:\Users\thess\DockerProjects\ServcoApp"

# Calculate ports based on instance number
if ($Instance -eq 0) {
    $FrontendPort = 9000
    $BackendPort = 8080
    $PhpMyAdminPort = 8081
    $MysqlPort = 3306
    $Suffix = ""
} else {
    $FrontendPort = 9000 + $Instance
    $BackendPort = 8080 + ($Instance * 100)
    $PhpMyAdminPort = 8081 + ($Instance * 100)
    $MysqlPort = 3306 + ($Instance * 10)
    $Suffix = "-$Instance"
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  ServcoApp Instance $Instance" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Ports:" -ForegroundColor Yellow
Write-Host "  Frontend:    http://localhost:$FrontendPort" -ForegroundColor Green
Write-Host "  Backend API: http://localhost:$BackendPort" -ForegroundColor Green
Write-Host "  phpMyAdmin:  http://localhost:$PhpMyAdminPort" -ForegroundColor Green
Write-Host "  MySQL:       localhost:$MysqlPort" -ForegroundColor Green
Write-Host ""

# Set environment variables for docker-compose override
$env:FRONTEND_PORT = $FrontendPort
$env:BACKEND_PORT = $BackendPort
$env:PHPMYADMIN_PORT = $PhpMyAdminPort
$env:MYSQL_PORT = $MysqlPort
$env:INSTANCE_SUFFIX = $Suffix
$env:COMPOSE_PROJECT_NAME = "servcoapp$Suffix"

# Change to project directory
Push-Location $ProjectRoot

try {
    # Check if override file exists
    $OverrideFile = "$ProjectRoot\docker-compose.override.yml"
    if (-not (Test-Path $OverrideFile)) {
        Write-Host "Creating docker-compose.override.yml..." -ForegroundColor Yellow
        Write-Host "Please run this script again after the file is created." -ForegroundColor Yellow
    }

    # Start docker-compose with the override
    Write-Host "Starting containers..." -ForegroundColor Yellow
    docker-compose -p "servcoapp$Suffix" up -d

    Write-Host ""
    Write-Host "Containers started successfully!" -ForegroundColor Green
    Write-Host ""
} finally {
    Pop-Location
}
