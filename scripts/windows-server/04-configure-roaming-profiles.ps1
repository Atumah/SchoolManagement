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

try {
    Import-Module ActiveDirectory -ErrorAction Stop
} catch {
    Write-Host "ERROR: Failed to import ActiveDirectory module" -ForegroundColor Red
    exit 1
}

$ServerName = $env:COMPUTERNAME
$ProfilesPath = "\\$ServerName\Profiles"

# Get all users
try {
    $DomainDN = (Get-ADDomain).DistinguishedName
    $Users = Get-ADUser -Filter * -SearchBase "OU=Users,$DomainDN" -ErrorAction Stop
} catch {
    Write-Host "ERROR: Failed to get users from Active Directory" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

Write-Host "Configuring roaming profiles for $($Users.Count) users..." -ForegroundColor Yellow
Write-Host ""

$Count = 0
foreach ($User in $Users) {
    try {
        $ProfilePath = "$ProfilesPath\$($User.SamAccountName)"
        
        # Check if user is a teacher or principal (they get P: drive)
        $UserGroups = @()
        try {
            $UserGroups = Get-ADPrincipalGroupMembership -Identity $User -ErrorAction Stop | Select-Object -ExpandProperty Name
        } catch {
            Write-Host "  ⚠ Could not get group membership for $($User.SamAccountName): $($_.Exception.Message)" -ForegroundColor Yellow
        }
        
        if ($UserGroups -contains "Teachers" -or $UserGroups -contains "Principal") {
            # Teachers and Principal get P: drive mapped to their private folder
            $HomeDrive = "P:"
            $HomeDirectory = "\\$ServerName\Private\$($User.SamAccountName)"
            
            # Verify private folder exists
            $PrivateFolderPath = "D:\Shares\Private\$($User.SamAccountName)"
            if (-not (Test-Path $PrivateFolderPath)) {
                Write-Host "  ⚠ Warning: Private folder not found for $($User.SamAccountName), creating it..." -ForegroundColor Yellow
                try {
                    New-Item -ItemType Directory -Path $PrivateFolderPath -Force | Out-Null
                } catch {
                    Write-Host "  ✗ Could not create private folder: $($_.Exception.Message)" -ForegroundColor Red
                }
            }
            
            Set-ADUser -Identity $User -ProfilePath $ProfilePath -HomeDrive $HomeDrive -HomeDirectory $HomeDirectory -ErrorAction Stop
        } else {
            # Other users get profile but no home drive
            Set-ADUser -Identity $User -ProfilePath $ProfilePath -ErrorAction Stop
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

