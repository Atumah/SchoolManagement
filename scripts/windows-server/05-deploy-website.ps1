# ============================================
# Script: Deploy Website
# Purpose: Installs Apache, PHP, MariaDB and deploys the website
# Usage: Run as Administrator
# ============================================

# Check if running as Administrator
if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    exit 1
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Deploying School Management Website" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Configuration
$WebsitePath = "C:\inetpub\wwwroot\schoolmanagement"
$ApachePath = "C:\Apache24"
$PHPPath = "C:\PHP"
$MariaDBPath = "C:\Program Files\MariaDB"

# Check if website files exist
if (-not (Test-Path ".\public")) {
    Write-Host "ERROR: Website files not found!" -ForegroundColor Red
    Write-Host "Make sure you run this script from the SchoolManagement repository root." -ForegroundColor Yellow
    Write-Host "Current directory: $PWD" -ForegroundColor Yellow
    exit 1
}

Write-Host "Step 1: Checking MariaDB installation..." -ForegroundColor Yellow
$MariaDBInstalled = $false
if (Test-Path "$MariaDBPath\bin\mysql.exe") {
    Write-Host "  ✓ MariaDB found at: $MariaDBPath" -ForegroundColor Green
    $MariaDBInstalled = $true
} else {
    Write-Host "  ✗ MariaDB not found" -ForegroundColor Red
    Write-Host "  Download from: https://mariadb.org/download/" -ForegroundColor Cyan
    Write-Host "  Install to: $MariaDBPath" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "Step 2: Checking Apache installation..." -ForegroundColor Yellow
$ApacheInstalled = $false
if (Test-Path "$ApachePath\bin\httpd.exe") {
    Write-Host "  ✓ Apache found at: $ApachePath" -ForegroundColor Green
    $ApacheInstalled = $true
} else {
    Write-Host "  ✗ Apache not found" -ForegroundColor Red
    Write-Host "  Download from: https://httpd.apache.org/download.cgi" -ForegroundColor Cyan
    Write-Host "  Extract to: $ApachePath" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "Step 3: Checking PHP installation..." -ForegroundColor Yellow
$PHPInstalled = $false
if (Test-Path "$PHPPath\php.exe") {
    Write-Host "  ✓ PHP found at: $PHPPath" -ForegroundColor Green
    $PHPInstalled = $true
} else {
    Write-Host "  ✗ PHP not found" -ForegroundColor Red
    Write-Host "  Download PHP 8.2 Thread Safe from: https://windows.php.net/download/" -ForegroundColor Cyan
    Write-Host "  Extract to: $PHPPath" -ForegroundColor Cyan
}

if (-not ($MariaDBInstalled -and $ApacheInstalled -and $PHPInstalled)) {
    Write-Host ""
    Write-Host "Please install missing components first, then run this script again." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "Step 4: Copying website files..." -ForegroundColor Yellow
if (-not (Test-Path $WebsitePath)) {
    New-Item -ItemType Directory -Path $WebsitePath -Force | Out-Null
}

# Copy files (exclude git, docs, docker, scripts)
Write-Host "  Copying files..." -ForegroundColor Yellow
$ExcludePatterns = @(".git", "*.md", "docker", "scripts", ".env", ".env.example")
$ItemsToCopy = Get-ChildItem -Path "." -Exclude $ExcludePatterns -Force
foreach ($Item in $ItemsToCopy) {
    try {
        Copy-Item -Path $Item.FullName -Destination $WebsitePath -Recurse -Force -ErrorAction Stop
    } catch {
        Write-Host "  ⚠ Could not copy $($Item.Name): $($_.Exception.Message)" -ForegroundColor Yellow
    }
}
Write-Host "  ✓ Website files copied to: $WebsitePath" -ForegroundColor Green

# Set permissions
try {
    $acl = Get-Acl $WebsitePath
    $permission = "MORNINGSTAR\Domain Users", "ReadAndExecute", "ContainerInherit,ObjectInherit", "None", "Allow"
    $accessRule = New-Object System.Security.AccessControl.FileSystemAccessRule $permission
    $acl.SetAccessRule($accessRule)
    Set-Acl $WebsitePath $acl
    Write-Host "  ✓ Set read permissions for Domain Users" -ForegroundColor Green
} catch {
    Write-Host "  ⚠ Could not set permissions (may need to do manually)" -ForegroundColor Yellow
}

# Set write permissions for uploads
$UploadsPath = "$WebsitePath\public\uploads\profiles"
if (Test-Path $UploadsPath) {
    try {
        $acl = Get-Acl $UploadsPath
        $permission = "MORNINGSTAR\Domain Users", "Modify", "ContainerInherit,ObjectInherit", "None", "Allow"
        $accessRule = New-Object System.Security.AccessControl.FileSystemAccessRule $permission
        $acl.SetAccessRule($accessRule)
        Set-Acl $UploadsPath $acl
        Write-Host "  ✓ Set write permissions for uploads folder" -ForegroundColor Green
    } catch {
        Write-Host "  ⚠ Could not set upload permissions (may need to do manually)" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "Step 5: Creating .env file..." -ForegroundColor Yellow

# Get database password
$DBPassword = Read-Host "Enter MariaDB database password for 'schoolapp' user" -AsSecureString
$BSTR = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($DBPassword)
$PlainPassword = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)

$EnvContent = @"
APP_NAME="SchoolManagement"
APP_ENV=production
APP_URL=http://morningstar.local
APP_TIMEZONE=UTC

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=schoolmanagement
DB_USERNAME=schoolapp
DB_PASSWORD=$PlainPassword
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
"@

$EnvContent | Out-File -FilePath "$WebsitePath\.env" -Encoding UTF8 -NoNewline
Write-Host "  ✓ Created .env file" -ForegroundColor Green

Write-Host ""
Write-Host "Step 6: Installing PHP dependencies..." -ForegroundColor Yellow
Set-Location $WebsitePath

# Check if Composer is installed
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    Write-Host "  ✗ Composer not found" -ForegroundColor Red
    Write-Host "  Download from: https://getcomposer.org/download/" -ForegroundColor Cyan
    Write-Host "  Run Composer-Setup.exe to install" -ForegroundColor Cyan
    Write-Host "  After installing Composer, run this script again." -ForegroundColor Yellow
    exit 0
}

Write-Host "  Installing dependencies..." -ForegroundColor Yellow
try {
    composer install --no-dev --optimize-autoloader
    if ($LASTEXITCODE -eq 0 -or $?) {
        Write-Host "  ✓ PHP dependencies installed" -ForegroundColor Green
    } else {
        Write-Host "  ✗ Error installing dependencies" -ForegroundColor Red
    }
} catch {
    Write-Host "  ✗ Error installing dependencies: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "✓ Website deployment completed!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "  1. Configure Apache httpd.conf (see docs/infrastructure/DEPLOYMENT_GUIDE.md)" -ForegroundColor White
Write-Host "  2. Create database and import schema:" -ForegroundColor White
Write-Host "     mysql -u schoolapp -p schoolmanagement < database_schema.sql" -ForegroundColor Gray
Write-Host "  3. Create admin user:" -ForegroundColor White
Write-Host "     cd $WebsitePath" -ForegroundColor Gray
Write-Host "     php scripts\create_admin.php" -ForegroundColor Gray
Write-Host "  4. Test website: http://morningstar.local" -ForegroundColor White
Write-Host ""

