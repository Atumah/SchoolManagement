# ============================================
# Script: Create File Shares
# Purpose: Creates Private and General network drives
# Usage: Run as Administrator
# ============================================

# Check if running as Administrator
if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    exit 1
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Creating File Shares" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Import AD module
Import-Module ActiveDirectory

# Configuration
$BasePath = "D:\Shares"
$PrivatePath = "$BasePath\Private"
$GeneralPath = "$BasePath\General"
$ProfilesPath = "$BasePath\Profiles"

# Check if D: drive exists
if (-not (Test-Path "D:\")) {
    Write-Host "WARNING: D: drive does not exist!" -ForegroundColor Yellow
    Write-Host "Please create D: drive first using Disk Management." -ForegroundColor Yellow
    Write-Host "Press any key to continue anyway (will use C: drive)..." -ForegroundColor Yellow
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    $BasePath = "C:\Shares"
    $PrivatePath = "$BasePath\Private"
    $GeneralPath = "$BasePath\General"
    $ProfilesPath = "$BasePath\Profiles"
}

# Create base directory structure
Write-Host "Step 1: Creating folder structure..." -ForegroundColor Yellow

try {
    # Create base Shares directory
    if (-not (Test-Path $BasePath)) {
        New-Item -ItemType Directory -Path $BasePath -Force | Out-Null
        Write-Host "  ✓ Created: $BasePath" -ForegroundColor Green
    }

    # Create Private directory
    if (-not (Test-Path $PrivatePath)) {
        New-Item -ItemType Directory -Path $PrivatePath -Force | Out-Null
        Write-Host "  ✓ Created: $PrivatePath" -ForegroundColor Green
    }

    # Create General directory
    if (-not (Test-Path $GeneralPath)) {
        New-Item -ItemType Directory -Path $GeneralPath -Force | Out-Null
        Write-Host "  ✓ Created: $GeneralPath" -ForegroundColor Green
    }

    # Create Profiles directory
    if (-not (Test-Path $ProfilesPath)) {
        New-Item -ItemType Directory -Path $ProfilesPath -Force | Out-Null
        Write-Host "  ✓ Created: $ProfilesPath" -ForegroundColor Green
    }

    # Create subdirectories in General
    $GeneralSubdirs = @("Documents", "Announcements", "Events")
    foreach ($Subdir in $GeneralSubdirs) {
        $SubdirPath = "$GeneralPath\$Subdir"
        if (-not (Test-Path $SubdirPath)) {
            New-Item -ItemType Directory -Path $SubdirPath -Force | Out-Null
            Write-Host "  ✓ Created: $SubdirPath" -ForegroundColor Green
        }
    }

} catch {
    Write-Host "ERROR creating folders: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Create individual folders for each teacher and principal
Write-Host "Step 2: Creating user folders..." -ForegroundColor Yellow

$Teachers = @("teacher1", "teacher2", "teacher3", "teacher4", "teacher5", "teacher6", "teacher7")
$Principal = @("principal1")

foreach ($User in ($Teachers + $Principal)) {
    $UserPath = "$PrivatePath\$User"
    if (-not (Test-Path $UserPath)) {
        New-Item -ItemType Directory -Path $UserPath -Force | Out-Null
        Write-Host "  ✓ Created private folder for: $User" -ForegroundColor Green
    }
}

Write-Host ""

# Create shares
Write-Host "Step 3: Creating network shares..." -ForegroundColor Yellow

# Remove existing shares if they exist
$ExistingShares = Get-SmbShare -ErrorAction SilentlyContinue | Where-Object { $_.Name -in @("Private", "General", "Profiles") }
foreach ($Share in $ExistingShares) {
    Remove-SmbShare -Name $Share.Name -Force -ErrorAction SilentlyContinue
    Write-Host "  - Removed existing share: $($Share.Name)" -ForegroundColor Gray
}

# Create Private share
try {
    New-SmbShare -Name "Private" -Path $PrivatePath -Description "Private network drive for teachers and principal" -FullAccess "MORNINGSTAR\FileServer_Private_Access" | Out-Null
    Write-Host "  ✓ Created share: Private" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Error creating Private share: $($_.Exception.Message)" -ForegroundColor Red
}

# Create General share
try {
    New-SmbShare -Name "General" -Path $GeneralPath -Description "General network drive for all staff" -FullAccess "MORNINGSTAR\FileServer_General_Access" | Out-Null
    Write-Host "  ✓ Created share: General" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Error creating General share: $($_.Exception.Message)" -ForegroundColor Red
}

# Create Profiles share
try {
    New-SmbShare -Name "Profiles" -Path $ProfilesPath -Description "Roaming user profiles" -FullAccess "MORNINGSTAR\Domain Users" | Out-Null
    Write-Host "  ✓ Created share: Profiles" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Error creating Profiles share: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Configure NTFS permissions
Write-Host "Step 4: Configuring NTFS permissions..." -ForegroundColor Yellow

# Private folder: Each user can only access their own folder
foreach ($User in ($Teachers + $Principal)) {
    $UserPath = "$PrivatePath\$User"
    if (Test-Path $UserPath) {
        try {
            # Remove inherited permissions
            $acl = Get-Acl $UserPath
            $acl.SetAccessRuleProtection($true, $false)
            Set-Acl $UserPath $acl

            # Add permissions: User gets Full Control on their folder
            $UserAccount = "MORNINGSTAR\$User"
            $Permission = $UserAccount, "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow"
            $AccessRule = New-Object System.Security.AccessControl.FileSystemAccessRule $Permission
            $acl.SetAccessRule($AccessRule)

            # Administrators get Full Control
            $AdminAccount = "MORNINGSTAR\Domain Admins"
            $Permission = $AdminAccount, "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow"
            $AccessRule = New-Object System.Security.AccessControl.FileSystemAccessRule $Permission
            $acl.SetAccessRule($AccessRule)

            Set-Acl $UserPath $acl
            Write-Host "  ✓ Set permissions for: $User" -ForegroundColor Green
        } catch {
            Write-Host "  ✗ Error setting permissions for $User: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
}

# General folder: All staff can read/write
try {
    $acl = Get-Acl $GeneralPath
    $acl.SetAccessRuleProtection($true, $false)
    
    # Domain Users get Modify
    $UsersAccount = "MORNINGSTAR\Domain Users"
    $Permission = $UsersAccount, "Modify", "ContainerInherit,ObjectInherit", "None", "Allow"
    $AccessRule = New-Object System.Security.AccessControl.FileSystemAccessRule $Permission
    $acl.SetAccessRule($AccessRule)

    # Domain Admins get Full Control
    $AdminAccount = "MORNINGSTAR\Domain Admins"
    $Permission = $AdminAccount, "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow"
    $AccessRule = New-Object System.Security.AccessControl.FileSystemAccessRule $Permission
    $acl.SetAccessRule($AccessRule)

    Set-Acl $GeneralPath $acl
    Write-Host "  ✓ Set permissions for General folder" -ForegroundColor Green
} catch {
    Write-Host "  ✗ Error setting permissions for General folder: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

$ServerName = $env:COMPUTERNAME
Write-Host "========================================" -ForegroundColor Green
Write-Host "✓ File shares created successfully!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Shares created:" -ForegroundColor Cyan
Write-Host "  - \\$ServerName\Private (Private drive)" -ForegroundColor White
Write-Host "  - \\$ServerName\General (General drive)" -ForegroundColor White
Write-Host "  - \\$ServerName\Profiles (Roaming profiles)" -ForegroundColor White
Write-Host ""

