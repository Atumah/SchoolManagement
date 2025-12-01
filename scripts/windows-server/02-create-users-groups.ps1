# ============================================
# Script: Create Users and Groups
# Purpose: Creates AD users and groups for the school
# Usage: Run as Administrator AFTER domain is created
# ============================================

# Check if running as Administrator
if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    exit 1
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Creating Users and Groups" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Import AD module
try {
    Import-Module ActiveDirectory -ErrorAction Stop
} catch {
    Write-Host "ERROR: Failed to import ActiveDirectory module" -ForegroundColor Red
    Write-Host "Make sure you are running this on a Domain Controller" -ForegroundColor Yellow
    exit 1
}

# Configuration
try {
    $DomainDN = (Get-ADDomain).DistinguishedName
} catch {
    Write-Host "ERROR: Failed to get domain information" -ForegroundColor Red
    Write-Host "Make sure you are logged in to the domain" -ForegroundColor Yellow
    exit 1
}
$UsersOU = "OU=Users,$DomainDN"
$TeachersOU = "OU=Teachers,$UsersOU"
$AdminOU = "OU=Administration,$UsersOU"
$ManagementOU = "OU=Management,$UsersOU"
$ITOU = "OU=IT,$UsersOU"

# Default password for all users (CHANGE THIS!)
$DefaultPassword = "ChangeMe123!" | ConvertTo-SecureString -AsPlainText -Force

Write-Host "IMPORTANT: All users will have password: ChangeMe123!" -ForegroundColor Yellow
Write-Host "Users will be required to change password on first login." -ForegroundColor Yellow
Write-Host ""
$confirm = Read-Host "Continue? (yes/no)"
if ($confirm -ne "yes") {
    Write-Host "Cancelled." -ForegroundColor Yellow
    exit 0
}

# Create Organizational Units
Write-Host "Step 1: Creating Organizational Units..." -ForegroundColor Yellow

try {
    # Create Users OU
    if (-not (Get-ADOrganizationalUnit -Filter "Name -eq 'Users'" -SearchBase $DomainDN -ErrorAction SilentlyContinue)) {
        New-ADOrganizationalUnit -Name "Users" -Path $DomainDN -ProtectedFromAccidentalDeletion $true
        Write-Host "  ✓ Created OU: Users" -ForegroundColor Green
    } else {
        Write-Host "  - OU Users already exists" -ForegroundColor Gray
    }

    # Create Teachers OU
    if (-not (Get-ADOrganizationalUnit -Filter "Name -eq 'Teachers'" -SearchBase $UsersOU -ErrorAction SilentlyContinue)) {
        New-ADOrganizationalUnit -Name "Teachers" -Path $UsersOU -ProtectedFromAccidentalDeletion $true
        Write-Host "  ✓ Created OU: Teachers" -ForegroundColor Green
    } else {
        Write-Host "  - OU Teachers already exists" -ForegroundColor Gray
    }

    # Create Administration OU
    if (-not (Get-ADOrganizationalUnit -Filter "Name -eq 'Administration'" -SearchBase $UsersOU -ErrorAction SilentlyContinue)) {
        New-ADOrganizationalUnit -Name "Administration" -Path $UsersOU -ProtectedFromAccidentalDeletion $true
        Write-Host "  ✓ Created OU: Administration" -ForegroundColor Green
    } else {
        Write-Host "  - OU Administration already exists" -ForegroundColor Gray
    }

    # Create Management OU
    if (-not (Get-ADOrganizationalUnit -Filter "Name -eq 'Management'" -SearchBase $UsersOU -ErrorAction SilentlyContinue)) {
        New-ADOrganizationalUnit -Name "Management" -Path $UsersOU -ProtectedFromAccidentalDeletion $true
        Write-Host "  ✓ Created OU: Management" -ForegroundColor Green
    } else {
        Write-Host "  - OU Management already exists" -ForegroundColor Gray
    }

    # Create IT OU
    if (-not (Get-ADOrganizationalUnit -Filter "Name -eq 'IT'" -SearchBase $UsersOU -ErrorAction SilentlyContinue)) {
        New-ADOrganizationalUnit -Name "IT" -Path $UsersOU -ProtectedFromAccidentalDeletion $true
        Write-Host "  ✓ Created OU: IT" -ForegroundColor Green
    } else {
        Write-Host "  - OU IT already exists" -ForegroundColor Gray
    }

} catch {
    Write-Host "ERROR creating OUs: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Create Security Groups
Write-Host "Step 2: Creating Security Groups..." -ForegroundColor Yellow

$Groups = @(
    @{Name="Teachers"; Description="All teachers"},
    @{Name="Administration"; Description="Administrative staff"},
    @{Name="Principal"; Description="School principal"},
    @{Name="WebDesigner"; Description="Web designer and part-time administrator"},
    @{Name="FileServer_Private_Access"; Description="Access to private network drive"},
    @{Name="FileServer_General_Access"; Description="Access to general network drive"},
    @{Name="Website_Editors"; Description="Can edit website announcements and events"}
)

foreach ($Group in $Groups) {
    try {
        if (-not (Get-ADGroup -Filter "Name -eq '$($Group.Name)'" -ErrorAction SilentlyContinue)) {
            New-ADGroup -Name $Group.Name -GroupScope Global -Description $Group.Description -Path $UsersOU
            Write-Host "  ✓ Created group: $($Group.Name)" -ForegroundColor Green
        } else {
            Write-Host "  - Group $($Group.Name) already exists" -ForegroundColor Gray
        }
    } catch {
        Write-Host "  ✗ Error creating group $($Group.Name): $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host ""

# Create Users
Write-Host "Step 3: Creating User Accounts..." -ForegroundColor Yellow

# Teachers (7 users)
$Teachers = @(
    @{Username="teacher1"; Name="Teacher One"; Email="teacher1@morningstar.local"; OU=$TeachersOU; Group="Teachers"},
    @{Username="teacher2"; Name="Teacher Two"; Email="teacher2@morningstar.local"; OU=$TeachersOU; Group="Teachers"},
    @{Username="teacher3"; Name="Teacher Three"; Email="teacher3@morningstar.local"; OU=$TeachersOU; Group="Teachers"},
    @{Username="teacher4"; Name="Teacher Four"; Email="teacher4@morningstar.local"; OU=$TeachersOU; Group="Teachers"},
    @{Username="teacher5"; Name="Teacher Five"; Email="teacher5@morningstar.local"; OU=$TeachersOU; Group="Teachers"},
    @{Username="teacher6"; Name="Teacher Six"; Email="teacher6@morningstar.local"; OU=$TeachersOU; Group="Teachers"},
    @{Username="teacher7"; Name="Teacher Seven"; Email="teacher7@morningstar.local"; OU=$TeachersOU; Group="Teachers"}
)

# Administration (2 users)
$Admins = @(
    @{Username="admin1"; Name="Admin One"; Email="admin1@morningstar.local"; OU=$AdminOU; Group="Administration"},
    @{Username="admin2"; Name="Admin Two"; Email="admin2@morningstar.local"; OU=$AdminOU; Group="Administration"}
)

# Principal (1 user)
$Principal = @(
    @{Username="principal1"; Name="Principal One"; Email="principal1@morningstar.local"; OU=$ManagementOU; Group="Principal"}
)

# Web Designer (1 user)
$WebDesigner = @(
    @{Username="webdesigner1"; Name="Web Designer One"; Email="webdesigner1@morningstar.local"; OU=$ITOU; Group="WebDesigner"}
)

$AllUsers = $Teachers + $Admins + $Principal + $WebDesigner

foreach ($User in $AllUsers) {
    try {
        if (-not (Get-ADUser -Filter "SamAccountName -eq '$($User.Username)'" -ErrorAction SilentlyContinue)) {
            New-ADUser `
                -SamAccountName $User.Username `
                -Name $User.Name `
                -DisplayName $User.Name `
                -GivenName ($User.Name -split ' ')[0] `
                -Surname ($User.Name -split ' ')[-1] `
                -EmailAddress $User.Email `
                -UserPrincipalName "$($User.Username)@morningstar.local" `
                -Path $User.OU `
                -AccountPassword $DefaultPassword `
                -Enabled $true `
                -PasswordNeverExpires $false `
                -ChangePasswordAtLogon $true

            # Add user to group
            try {
                if (Get-ADGroup -Filter "Name -eq '$($User.Group)'" -ErrorAction SilentlyContinue) {
                    Add-ADGroupMember -Identity $User.Group -Members $User.Username -ErrorAction Stop
                    Write-Host "  ✓ Created user: $($User.Username) (Group: $($User.Group))" -ForegroundColor Green
                } else {
                    Write-Host "  ⚠ Created user: $($User.Username) but group $($User.Group) not found" -ForegroundColor Yellow
                }
            } catch {
                Write-Host "  ⚠ Created user: $($User.Username) but failed to add to group $($User.Group): $($_.Exception.Message)" -ForegroundColor Yellow
            }
        } else {
            Write-Host "  - User $($User.Username) already exists" -ForegroundColor Gray
        }
    } catch {
        Write-Host "  ✗ Error creating user $($User.Username): $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host ""

# Add users to file server groups
Write-Host "Step 4: Adding users to file server groups..." -ForegroundColor Yellow

# Teachers get private drive access
foreach ($Teacher in $Teachers) {
    try {
        if (Get-ADGroup -Filter "Name -eq 'FileServer_Private_Access'" -ErrorAction SilentlyContinue) {
            Add-ADGroupMember -Identity "FileServer_Private_Access" -Members $Teacher.Username -ErrorAction SilentlyContinue
        }
    } catch {}
}

# All staff get general drive access
$AllStaff = $Teachers + $Admins + $Principal + $WebDesigner
foreach ($Staff in $AllStaff) {
    try {
        if (Get-ADGroup -Filter "Name -eq 'FileServer_General_Access'" -ErrorAction SilentlyContinue) {
            Add-ADGroupMember -Identity "FileServer_General_Access" -Members $Staff.Username -ErrorAction SilentlyContinue
        }
    } catch {}
}

# Website editors (Admin, Principal, Web Designer)
$WebsiteEditors = $Admins + $Principal + $WebDesigner
foreach ($Editor in $WebsiteEditors) {
    try {
        if (Get-ADGroup -Filter "Name -eq 'Website_Editors'" -ErrorAction SilentlyContinue) {
            Add-ADGroupMember -Identity "Website_Editors" -Members $Editor.Username -ErrorAction SilentlyContinue
        }
    } catch {}
}

Write-Host "  ✓ Added users to file server groups" -ForegroundColor Green
Write-Host ""

Write-Host "========================================" -ForegroundColor Green
Write-Host "✓ Users and Groups created successfully!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Summary:" -ForegroundColor Cyan
Write-Host "  - 7 Teachers created" -ForegroundColor White
Write-Host "  - 2 Administration users created" -ForegroundColor White
Write-Host "  - 1 Principal created" -ForegroundColor White
Write-Host "  - 1 Web Designer created" -ForegroundColor White
Write-Host ""
Write-Host "Default password for all users: ChangeMe123!" -ForegroundColor Yellow
Write-Host "Users must change password on first login." -ForegroundColor Yellow
Write-Host ""

