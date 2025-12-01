# ============================================
# Script: Configure Roaming Profiles
# Purpose: Sets up roaming profiles for all users
# Usage: Run as Administrator
# ============================================

# Check if running as Administrator
if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    exit 1
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Configuring Roaming Profiles" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Import-Module ActiveDirectory

$ServerName = $env:COMPUTERNAME
$ProfilesPath = "\\$ServerName\Profiles"

# Get all users
$Users = Get-ADUser -Filter * -SearchBase "OU=Users,$(Get-ADDomain).DistinguishedName"

Write-Host "Configuring roaming profiles for $($Users.Count) users..." -ForegroundColor Yellow
Write-Host ""

$Count = 0
foreach ($User in $Users) {
    try {
        $ProfilePath = "$ProfilesPath\$($User.SamAccountName)"
        $HomeDrive = "P:"
        $HomeDirectory = "\\$ServerName\Private\$($User.SamAccountName)"

        # Check if user is a teacher or principal (they get P: drive)
        $UserGroups = Get-ADPrincipalGroupMembership -Identity $User | Select-Object -ExpandProperty Name
        if ($UserGroups -contains "Teachers" -or $UserGroups -contains "Principal") {
            Set-ADUser -Identity $User -ProfilePath $ProfilePath -HomeDrive $HomeDrive -HomeDirectory $HomeDirectory
        } else {
            # Other users get profile but no home drive
            Set-ADUser -Identity $User -ProfilePath $ProfilePath
        }

        $Count++
        Write-Host "  ✓ Configured: $($User.SamAccountName)" -ForegroundColor Green
    } catch {
        Write-Host "  ✗ Error configuring $($User.SamAccountName): $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "✓ Roaming profiles configured for $Count users!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

