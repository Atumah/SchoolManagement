# ============================================
# Script: Install Active Directory Domain Services
# Purpose: Automates AD DS installation and domain creation
# Usage: Run as Administrator on Windows Server 2022
# ============================================

# Check if running as Administrator
if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    exit 1
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Installing Active Directory Domain Services" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Configuration - CHANGE THESE VALUES
$DomainName = "morningstar.local"
$DomainNetBIOS = "MORNINGSTAR"
$SafeModePassword = Read-Host "Enter Safe Mode Administrator Password (for AD recovery)" -AsSecureString
$SafeModePasswordConfirm = Read-Host "Confirm Safe Mode Administrator Password" -AsSecureString

# Convert secure strings to plain text for comparison (not secure, but needed for validation)
$BSTR1 = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($SafeModePassword)
$PlainPassword1 = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR1)
$BSTR2 = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($SafeModePasswordConfirm)
$PlainPassword2 = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR2)

if ($PlainPassword1 -ne $PlainPassword2) {
    Write-Host "ERROR: Passwords do not match!" -ForegroundColor Red
    exit 1
}

Write-Host "✓ Passwords match" -ForegroundColor Green
Write-Host ""

# Install AD DS feature
Write-Host "Step 1: Installing AD DS feature..." -ForegroundColor Yellow
try {
    $result = Install-WindowsFeature -Name AD-Domain-Services -IncludeManagementTools
    
    # Check if feature was installed successfully
    # Verify the feature is actually installed
    $FeatureInstalled = Get-WindowsFeature -Name AD-Domain-Services
    if ($FeatureInstalled.InstallState -ne "Installed") {
        Write-Host "ERROR: Failed to install AD DS feature" -ForegroundColor Red
        Write-Host "Feature state: $($FeatureInstalled.InstallState)" -ForegroundColor Red
        exit 1
    }
    
    Write-Host "✓ AD DS feature installed" -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "ERROR: Failed to install AD DS feature" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

# Import AD DS Deployment module
Write-Host "Step 2: Importing AD DS Deployment module..." -ForegroundColor Yellow
try {
    Import-Module ADDSDeployment -ErrorAction Stop
    Write-Host "✓ AD DS Deployment module imported" -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "ERROR: Failed to import ADDSDeployment module" -ForegroundColor Red
    Write-Host "Make sure AD DS feature is installed first" -ForegroundColor Yellow
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

# Promote server to Domain Controller
Write-Host "Step 3: Promoting server to Domain Controller..." -ForegroundColor Yellow
Write-Host "This will take several minutes. Please wait..." -ForegroundColor Yellow
Write-Host ""

try {
    Install-ADDSForest `
        -CreateDnsDelegation:$false `
        -DatabasePath "C:\Windows\NTDS" `
        -DomainMode "Win2012R2" `
        -DomainName $DomainName `
        -DomainNetbiosName $DomainNetBIOS `
        -ForestMode "Win2012R2" `
        -InstallDns:$true `
        -LogPath "C:\Windows\NTDS" `
        -NoRebootOnCompletion:$false `
        -SysvolPath "C:\Windows\SYSVOL" `
        -SafeModeAdministratorPassword $SafeModePassword `
        -Force:$true

    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "✓ Domain Controller installed successfully!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "The server will restart automatically." -ForegroundColor Yellow
    Write-Host "After restart, log in as: $DomainNetBIOS\Administrator" -ForegroundColor Yellow
    Write-Host ""
} catch {
    Write-Host ""
    Write-Host "ERROR: Failed to install Domain Controller" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

