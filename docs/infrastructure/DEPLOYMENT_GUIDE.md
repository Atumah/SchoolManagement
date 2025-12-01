# Complete Infrastructure Deployment Guide
## "The Morningstar" Primary School

This guide will walk you through setting up the entire infrastructure step by step with clear examples.

---

## 📋 Prerequisites

Before starting, make sure you have:
- ✅ 4 Proxmox VMs created:
  - IPfire Firewall
  - Windows Server 2022
  - Windows 11 Client
  - Linux (Zorin) Client
- ✅ Windows Server 2022 ISO mounted
- ✅ Windows 11 ISO mounted
- ✅ Zorin OS ISO mounted
- ✅ IPfire ISO mounted

---

## PART 1: IPfire Firewall Configuration

**LOCATION: IPfire Firewall VM** (192.168.1.1)

### Step 1.1: Initial IPfire Setup

**LOCATION: IPfire Firewall VM**

1. **Boot IPfire VM** and complete initial setup:
   - Set root password (write it down!)
   - Configure network interfaces:
     - **RED** (WAN): Your external network (or Proxmox bridge)
     - **GREEN** (LAN): Internal network
     - **BLUE** (Optional): Not needed for this setup

2. **Set IPfire IP Address**:
   - GREEN interface: `192.168.1.1`
   - Subnet mask: `255.255.255.0`

3. **Access IPfire Web Interface**:
   - Open browser: `https://192.168.1.1:444`
   - Login with root credentials
   - Accept the SSL certificate warning (it's self-signed)

### Step 1.2: Configure Firewall Rules

**LOCATION: IPfire Web Interface** (https://192.168.1.1:444)

**IMPORTANT NOTE**: IPfire can only control traffic between different zones (RED to GREEN). Since all your internal devices are on the same subnet (192.168.1.0/24), you can only create rules for external access. Internal traffic (same subnet) will be controlled by Windows Firewall on the Windows Server instead.

**Network Configuration:**
- IPfire GREEN: 192.168.1.1
- Windows Server: 192.168.1.100
- Windows 11 Client: 192.168.1.102
- Linux Client: 192.168.1.21

In IPfire web interface:

1. Go to **Firewall** → **Firewall Rules**
2. Click **"New rule"** button
3. Add these rules (only these 2 can be created - external access only):

   **Rule 1: Allow HTTP to Windows Server (from Internet)**
   - Source: Select **"Standard Networks"** → Choose **"RED"** (Internet)
   - Destination: Select **"Custom IP/Network"** → Enter: `192.168.1.100`
   - Protocol: Select **"TCP"**
   - Destination Port: Enter: `80`
   - Action: Select **"ACCEPT"**
   - Log: Check **"Log"** (recommended)
   - Remark: Enter: `Allow HTTP to Windows Server`
   - Click **"Add"**

   **Rule 2: Allow HTTPS to Windows Server (from Internet)**
   - Source: Select **"Standard Networks"** → Choose **"RED"** (Internet)
   - Destination: Select **"Custom IP/Network"** → Enter: `192.168.1.100`
   - Protocol: Select **"TCP"**
   - Destination Port: Enter: `443`
   - Action: Select **"ACCEPT"**
   - Log: Check **"Log"**
   - Remark: Enter: `Allow HTTPS to Windows Server`
   - Click **"Add"**

4. **Click "Apply changes"** button at the bottom to activate the rules

**NOTE**: Rules 3-8 (SMB, RDP, LDAP, DNS) cannot be created in IPfire because source and destination are on the same subnet. These will be configured in Windows Firewall on the Windows Server instead (see Step 2.11).

### Step 1.3: Configure DHCP (Optional)

If you want IPfire to handle DHCP:

1. Go to **DHCP** → **DHCP Server**
2. Enable DHCP server
3. Configure:
   - Range: 192.168.1.100 - 192.168.1.200
   - Gateway: 192.168.1.1
   - DNS: 192.168.1.100 (Windows Server)

**OR** use Windows Server DHCP (recommended for AD integration)

---

## PART 2: Windows Server 2022 Configuration

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

### Step 2.1: Initial Windows Server Setup

**LOCATION: Windows Server 2022 VM**

1. **Install Windows Server 2022**:
   - Boot from ISO
   - Complete installation
   - Set Administrator password (write it down!)

2. **Set Static IP Address**:
   - Open **Settings** → **Network & Internet** → **Ethernet**
   - Click **Edit** under IP assignment
   - Select **Manual**
   - Configure:
     ```
     IP address: 192.168.1.100
     Subnet mask: 255.255.255.0
     Gateway: 192.168.1.1 (IPfire)
     DNS: 192.168.1.100 (itself, for AD)
     ```

3. **Rename Computer**:
   - Right-click **This PC** → **Properties**
   - Click **Change settings**
   - Click **Change**
   - Computer name: `MORNINGSTAR-DC01`
   - Workgroup: Keep as WORKGROUP (will change when promoting to DC)
   - Restart when prompted

4. **Install Windows Updates**:
   - Open **Settings** → **Windows Update**
   - Install all updates
   - Restart if needed

### Step 2.2: Install Active Directory Domain Services

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

**IMPORTANT**: Run PowerShell as Administrator for all scripts!

1. **Open PowerShell as Administrator**:
   - Press `Windows Key + X`
   - Select **Windows PowerShell (Admin)** or **Terminal (Admin)**

2. **Clone the repository** (if not already done):
   ```powershell
   cd C:\
   git clone <your-repository-url> SchoolManagement
   cd SchoolManagement
   ```

   **OR** if you already have the files:
   ```powershell
   cd C:\SchoolManagement
   ```

3. **Run AD Installation Script**:
   ```powershell
   .\scripts\windows-server\01-install-ad-ds.ps1
   ```

4. **Follow the prompts**:
   - Enter Safe Mode password (write it down securely!)
   - Confirm Safe Mode password
   - Script will install AD DS and promote server to Domain Controller
   - Server will restart automatically (this takes 5-10 minutes)

5. **After restart**, log in as:
   - Username: `MORNINGSTAR\Administrator`
   - Password: (your Administrator password)

### Step 2.3: Create Users and Groups

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

1. **Open PowerShell as Administrator**

2. **Run user creation script**:
   ```powershell
   cd C:\SchoolManagement
   .\scripts\windows-server\02-create-users-groups.ps1
   ```

3. **Script will**:
   - Create Organizational Units (OUs)
   - Create Security Groups
   - Create 11 user accounts:
     - 7 Teachers (teacher1 - teacher7)
     - 2 Admins (admin1, admin2)
     - 1 Principal (principal1)
     - 1 Web Designer (webdesigner1)
   - Set default password: `ChangeMe123!`

4. **Verify users were created**:
   ```powershell
   Get-ADUser -Filter * | Select-Object Name, SamAccountName
   ```

   You should see all 11 users listed.

### Step 2.4: Create File Shares

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

1. **Create D: drive** (if not exists):
   - Open **Disk Management** (`diskmgmt.msc`)
   - Right-click unallocated space → **New Simple Volume**
   - Follow wizard:
     - Size: Use all available space
     - Drive letter: **D:**
     - Format: **NTFS**
     - Volume label: **Data**

2. **Run file share script**:
   ```powershell
   cd C:\SchoolManagement
   .\scripts\windows-server\03-create-file-shares.ps1
   ```

3. **Script will create**:
   - `D:\Shares\Private` - Private drive for teachers
   - `D:\Shares\General` - General drive for all staff
   - `D:\Shares\Profiles` - Roaming profiles

4. **Verify shares**:
   ```powershell
   Get-SmbShare
   ```
   You should see: Private, General, Profiles

5. **Test access** (from server):
   ```powershell
   Test-Path \\MORNINGSTAR-DC01\Private
   Test-Path \\MORNINGSTAR-DC01\General
   ```

### Step 2.5: Configure Roaming Profiles

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

1. **Run roaming profiles script**:
   ```powershell
   cd C:\SchoolManagement
   .\scripts\windows-server\04-configure-roaming-profiles.ps1
   ```

2. **Verify configuration**:
   ```powershell
   Get-ADUser teacher1 -Properties ProfilePath, HomeDrive, HomeDirectory
   ```

   You should see:
   - ProfilePath: `\\MORNINGSTAR-DC01\Profiles\teacher1`
   - HomeDrive: `P:`
   - HomeDirectory: `\\MORNINGSTAR-DC01\Private\teacher1`

### Step 2.6: Install MariaDB

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

1. **Download MariaDB**:
   - Go to: https://mariadb.org/download/
   - Download: **MariaDB 11.4** for Windows (MSI installer)
   - Save to: `C:\Downloads\`

2. **Install MariaDB**:
   - Run the installer
   - Installation type: **Typical**
   - Root password: Choose a strong password (write it down!)
   - Service name: `MariaDB`
   - Port: `3306`
   - Complete installation

3. **Create database and user**:
   - Open **Command Prompt as Administrator**
   - Navigate to MariaDB bin:
     ```cmd
     cd "C:\Program Files\MariaDB\MariaDB 11.4\bin"
     ```
   - Login:
     ```cmd
     mysql -u root -p
     ```
     (Enter root password when prompted)
   
   - Run these SQL commands:
     ```sql
     CREATE DATABASE schoolmanagement;
     CREATE USER 'schoolapp'@'localhost' IDENTIFIED BY 'YourSecurePassword123!';
     GRANT ALL PRIVILEGES ON schoolmanagement.* TO 'schoolapp'@'localhost';
     FLUSH PRIVILEGES;
     EXIT;
     ```
   
   **IMPORTANT**: Write down the password you set for `schoolapp` user!

4. **Import database schema**:
   ```powershell
   cd C:\SchoolManagement
   "C:\Program Files\MariaDB\MariaDB 11.4\bin\mysql.exe" -u schoolapp -p schoolmanagement < database_schema.sql
   ```
   (Enter schoolapp password when prompted)

   You should see: `Query OK` messages if successful.

### Step 2.7: Install Apache

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

1. **Download Apache**:
   - Go to: https://httpd.apache.org/download.cgi
   - Download: **Apache 2.4** for Windows (ZIP file, not MSI)
   - Extract to: `C:\Apache24`

2. **Configure Apache**:
   - Open: `C:\Apache24\conf\httpd.conf` in Notepad (as Administrator)
   
   **Find and change these lines:**
   
   Line 38: `Define SRVROOT "/Apache24"`
   Change to: `Define SRVROOT "C:/Apache24"`
   
   Line 166: `#LoadModule rewrite_module`
   Change to: `LoadModule rewrite_module modules/mod_rewrite.so`
   
   Line 181: `#LoadModule headers_module`
   Change to: `LoadModule headers_module modules/mod_headers.so`
   
   Line 245: `DocumentRoot "${SRVROOT}/htdocs"`
   Change to: `DocumentRoot "C:/inetpub/wwwroot/schoolmanagement/public"`
   
   Line 246: `<Directory "${SRVROOT}/htdocs">`
   Change to: `<Directory "C:/inetpub/wwwroot/schoolmanagement/public">`
   
   Line 254: `DirectoryIndex index.html`
   Change to: `DirectoryIndex index.php index.html`
   
   **Add these lines at the end of the file:**
   ```apache
   LoadModule php_module "C:/PHP/php8apache2_4.dll"
   PHPIniDir "C:/PHP"
   AddType application/x-httpd-php .php
   ```
   
   - Save file

3. **Install Apache as Service**:
   ```powershell
   cd C:\Apache24\bin
   .\httpd.exe -k install
   ```

4. **Start Apache**:
   ```powershell
   .\httpd.exe -k start
   ```

5. **Verify Apache is running**:
   ```powershell
   Get-Service Apache2.4
   ```
   Status should be "Running"

### Step 2.8: Install PHP

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

1. **Download PHP**:
   - Go to: https://windows.php.net/download/
   - Download: **PHP 8.2 Thread Safe** (ZIP file, not installer)
   - Extract to: `C:\PHP`

2. **Configure PHP**:
   - Copy `C:\PHP\php.ini-development` to `C:\PHP\php.ini`
   - Open `C:\PHP\php.ini` in Notepad
   
   **Find and uncomment these lines (remove the semicolon ; at the start):**
   ```
   extension=mysqli
   extension=pdo_mysql
   extension=intl
   extension=zip
   ```
   
   **Find and change:**
   ```
   extension_dir = "ext"
   ```
   Change to:
   ```
   extension_dir = "C:/PHP/ext"
   ```
   
   **Find:**
   ```
   ;session.save_path = "/tmp"
   ```
   Change to:
   ```
   session.save_path = "C:/Windows/Temp"
   ```
   
   - Save file

3. **Restart Apache**:
   ```powershell
   cd C:\Apache24\bin
   .\httpd.exe -k restart
   ```

4. **Test PHP**:
   - Create file: `C:\inetpub\wwwroot\schoolmanagement\public\test.php`
   - Add content:
     ```php
     <?php phpinfo(); ?>
     ```
   - Open browser: `http://192.168.1.100/test.php`
   - You should see PHP information page
   - Delete test.php after testing

### Step 2.9: Install Composer

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

1. **Download Composer**:
   - Go to: https://getcomposer.org/download/
   - Download: **Composer-Setup.exe**
   - Run installer
   - It will detect PHP automatically at `C:\PHP`

2. **Verify installation**:
   ```powershell
   composer --version
   ```
   Should show: `Composer version X.X.X`

### Step 2.10: Deploy Website

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

1. **Run deployment script**:
   ```powershell
   cd C:\SchoolManagement
   .\scripts\windows-server\05-deploy-website.ps1
   ```

2. **Follow prompts**:
   - Confirm MariaDB is installed
   - Confirm Apache is installed
   - Confirm PHP is installed
   - Enter database password when prompted (the `schoolapp` password you set earlier)

3. **Create admin user**:
   ```powershell
   cd C:\inetpub\wwwroot\schoolmanagement
   php scripts\create_admin.php
   ```

   You should see:
   ```
   ✓ Admin account created successfully!
   Username: admin
   Password: admin123
   ```

4. **Test website**:
   - Open browser: `http://192.168.1.100` or `http://morningstar.local`
   - You should see the login page
   - Login with: `admin` / `admin123`

### Step 2.11: Configure Windows Firewall for Internal Access

**LOCATION: Windows Server 2022** (192.168.1.100)

Since IPfire cannot control same-subnet traffic, we need to configure Windows Firewall on the Windows Server to allow internal network access to required services.

**Method 1: Using PowerShell (Recommended - Faster)**

1. **Open PowerShell as Administrator** on Windows Server:
   - Press `Windows Key + X`
   - Select **Windows PowerShell (Admin)** or **Terminal (Admin)**

2. **Run these commands** (copy and paste all at once, or one by one):

   ```powershell
   # Allow SMB (File Sharing) from Internal Network
   New-NetFirewallRule -DisplayName "Allow SMB from Internal Network" -Direction Inbound -LocalPort 445 -Protocol TCP -Action Allow -RemoteAddress 192.168.1.0/24

   # Allow RDP (Remote Desktop) from Internal Network
   New-NetFirewallRule -DisplayName "Allow RDP from Internal Network" -Direction Inbound -LocalPort 3389 -Protocol TCP -Action Allow -RemoteAddress 192.168.1.0/24

   # Allow LDAP from Internal Network
   New-NetFirewallRule -DisplayName "Allow LDAP from Internal Network" -Direction Inbound -LocalPort 389 -Protocol TCP -Action Allow -RemoteAddress 192.168.1.0/24

   # Allow LDAPS from Internal Network
   New-NetFirewallRule -DisplayName "Allow LDAPS from Internal Network" -Direction Inbound -LocalPort 636 -Protocol TCP -Action Allow -RemoteAddress 192.168.1.0/24

   # Allow DNS UDP from Internal Network
   New-NetFirewallRule -DisplayName "Allow DNS UDP from Internal Network" -Direction Inbound -LocalPort 53 -Protocol UDP -Action Allow -RemoteAddress 192.168.1.0/24

   # Allow DNS TCP from Internal Network
   New-NetFirewallRule -DisplayName "Allow DNS TCP from Internal Network" -Direction Inbound -LocalPort 53 -Protocol TCP -Action Allow -RemoteAddress 192.168.1.0/24
   ```

3. **Verify rules were created**:
   ```powershell
   Get-NetFirewallRule | Where-Object {$_.DisplayName -like "*Internal Network*"} | Select-Object DisplayName, Enabled, Direction
   ```

   You should see all 6 rules listed and enabled.

**Method 2: Using Windows Firewall GUI (Alternative)**

If you prefer using the GUI:

1. **Open Windows Defender Firewall with Advanced Security**:
   - Press `Windows Key + R`
   - Type: `wf.msc`
   - Press Enter

2. **Create Inbound Rules for each port**:

   **Rule 1: Allow SMB (Port 445)**
   - Click **"Inbound Rules"** in the left panel
   - Click **"New Rule..."** in the right panel
   - Select **"Port"** → Click **Next**
   - Select **"TCP"**
   - Select **"Specific local ports"** and enter: `445`
   - Click **Next**
   - Select **"Allow the connection"** → Click **Next**
   - Check all profiles (Domain, Private, Public) → Click **Next**
   - Name: `Allow SMB from Internal Network`
   - Click **Finish**
   - Right-click the new rule → **Properties**
   - Go to **Scope** tab
   - Under **"Remote IP address"**, select **"These IP addresses"**
   - Click **Add** → Enter: `192.168.1.0/24` → Click **OK** → Click **OK**

   **Rule 2: Allow RDP (Port 3389)**
   - Repeat the same process with:
     - Port: `3389`
     - Protocol: **TCP**
     - Name: `Allow RDP from Internal Network`
     - Remote IP: `192.168.1.0/24`

   **Rule 3: Allow LDAP (Port 389)**
   - Port: `389`
   - Protocol: **TCP**
   - Name: `Allow LDAP from Internal Network`
   - Remote IP: `192.168.1.0/24`

   **Rule 4: Allow LDAPS (Port 636)**
   - Port: `636`
   - Protocol: **TCP**
   - Name: `Allow LDAPS from Internal Network`
   - Remote IP: `192.168.1.0/24`

   **Rule 5: Allow DNS UDP (Port 53)**
   - Port: `53`
   - Protocol: **UDP**
   - Name: `Allow DNS UDP from Internal Network`
   - Remote IP: `192.168.1.0/24`

   **Rule 6: Allow DNS TCP (Port 53)**
   - Port: `53`
   - Protocol: **TCP**
   - Name: `Allow DNS TCP from Internal Network`
   - Remote IP: `192.168.1.0/24`

3. **Verify all rules are created and enabled**

**Summary of Windows Firewall Rules Created:**

- Rule 1: Port 445 (TCP) - SMB file sharing from 192.168.1.0/24
- Rule 2: Port 3389 (TCP) - RDP remote desktop from 192.168.1.0/24
- Rule 3: Port 389 (TCP) - LDAP authentication from 192.168.1.0/24
- Rule 4: Port 636 (TCP) - LDAPS secure LDAP from 192.168.1.0/24
- Rule 5: Port 53 (UDP) - DNS queries from 192.168.1.0/24
- Rule 6: Port 53 (TCP) - DNS over TCP from 192.168.1.0/24

---

## PART 3: Windows 11 Client Configuration

**LOCATION: Windows 11 Client VM** (192.168.1.102)

### Step 3.1: Install Windows 11

**LOCATION: Windows 11 Client VM**

1. **Boot Windows 11 VM** from ISO
2. **Complete installation**
3. **Set static IP** (or use DHCP):
   - **LOCATION: Windows 11 Client VM**
   - Open **Settings** → **Network & Internet** → **Ethernet**
   - Click **Edit** under IP assignment
   - Select **Manual**
   - Configure:
     ```
     IP address: 192.168.1.102
     Subnet mask: 255.255.255.0
     Gateway: 192.168.1.1 (IPfire)
     DNS: 192.168.1.100 (Windows Server)
     ```

### Step 3.2: Join Domain

**LOCATION: Windows 11 Client VM** (192.168.1.102)

1. **Open System Properties**:
   - Press `Windows Key + X`
   - Select **System**
   - Click **Rename this PC (advanced)**
   - Click **Change**

2. **Join Domain**:
   - Select **Domain**
   - Enter: `morningstar.local`
   - Click **OK**
   - Enter credentials:
     - Username: `MORNINGSTAR\Administrator`
     - Password: (your admin password)
   - Click **OK**
   - Restart when prompted

3. **After restart**, log in as domain user:
   - Username: `MORNINGSTAR\teacher1`
   - Password: `ChangeMe123!`
   - You'll be prompted to change password (set a new one)

### Step 3.3: Verify Access

**LOCATION: Windows 11 Client VM** (192.168.1.102)

1. **Check network drives**:
   - Open **File Explorer**
   - You should see:
     - **P:** drive (Private) - for teachers
     - **G:** drive (General) - for all staff

2. **Check roaming profile**:
   - Create a file on Desktop
   - Log out and log in as different user (e.g., `teacher2`)
   - Profile should be different (different desktop)

3. **Test website**:
   - Open browser
   - Go to: `http://morningstar.local`
   - Login with website credentials

---

## PART 4: Linux (Zorin) Client Configuration

**LOCATION: Linux (Zorin) Client VM** (192.168.1.21)

### Step 4.1: Install Zorin OS

**LOCATION: Linux (Zorin) Client VM**

1. **Boot Zorin OS VM** from ISO
2. **Complete installation**
3. **Set static IP**:
   - **LOCATION: Linux (Zorin) Client VM**
   ```bash
   sudo ./scripts/linux-client/04-configure-network.sh
   ```
   Or manually edit `/etc/netplan/01-netcfg.yaml`
   
   Configure:
   ```
   IP address: 192.168.1.21
   Gateway: 192.168.1.1
   DNS: 192.168.1.100 (Windows Server)
   ```

### Step 4.2: Join AD Domain

**LOCATION: Linux (Zorin) Client VM** (192.168.1.21)

1. **Install required packages**:
   ```bash
   cd /path/to/SchoolManagement
   sudo ./scripts/linux-client/01-install-packages.sh
   ```

2. **Join domain**:
   ```bash
   sudo ./scripts/linux-client/02-join-domain.sh
   ```
   - Enter Administrator password when prompted

3. **Restart system**:
   ```bash
   sudo reboot
   ```

4. **After restart**, login as AD user:
   - Username: `MORNINGSTAR\teacher1`
   - Password: `ChangeMe123!` (or your changed password)

### Step 4.3: Mount Network Drives

**LOCATION: Linux (Zorin) Client VM** (192.168.1.21)

1. **Mount shares**:
   ```bash
   sudo ./scripts/linux-client/03-mount-shares.sh
   ```
   - Enter domain username (e.g., `teacher1`)
   - Enter domain password

2. **Verify mounts**:
   ```bash
   ls /mnt/private
   ls /mnt/general
   ```

3. **Test website**:
   - Open browser
   - Go to: `http://morningstar.local`
   - Login with website credentials

---

## PART 5: Group Policy Configuration

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

### Step 5.1: Create GPO for Drive Mapping

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

1. **Open Group Policy Management**:
   - On Windows Server: Press `Windows Key + R`
   - Type: `gpmc.msc`
   - Press Enter

2. **Create GPO**:
   - Expand: `morningstar.local`
   - Right-click **morningstar.local** → **Create a GPO in this domain**
   - Name: `Map Network Drives`
   - Click **OK**

3. **Edit GPO**:
   - Right-click **Map Network Drives** → **Edit**
   - Navigate: **User Configuration** → **Preferences** → **Windows Settings** → **Drive Maps**

4. **Map P: Drive (Teachers)**:
   - Right-click **Drive Maps** → **New** → **Mapped Drive**
   - Action: **Create**
   - Location: `\\MORNINGSTAR-DC01\Private\%USERNAME%`
   - Drive letter: **P:**
   - Label: **Private Drive**
   - Reconnect: **Yes** (checked)
   - Click **Common** tab
   - Check **Item-level targeting**
   - Click **Targeting...**
   - Click **New Item** → **Security Group**
   - Group name: `MORNINGSTAR\Teachers`
   - Click **OK**
   - Click **OK** again

5. **Map G: Drive (All Staff)**:
   - Right-click **Drive Maps** → **New** → **Mapped Drive**
   - Action: **Create**
   - Location: `\\MORNINGSTAR-DC01\General`
   - Drive letter: **G:**
   - Label: **General Drive**
   - Reconnect: **Yes** (checked)
   - Click **OK**

6. **Link GPO**:
   - Right-click **Map Network Drives** → **Link Enabled** (should be checked)
   - Drag GPO to **OU=Users** (applies to all users)

7. **Force GPO update on client**:
   - On Windows 11 client, open PowerShell:
     ```powershell
     gpupdate /force
     ```
   - Restart client
   - Drives should appear after login

### Step 5.2: Deploy Network Printer

**LOCATION: Windows Server 2022 VM** (192.168.1.100)

1. **Install printer on server** (if not already done):
   - Go to **Settings** → **Devices** → **Printers & scanners**
   - Add printer
   - Share printer as: `NetworkPrinter`

2. **Create GPO for Printer**:
   - In Group Policy Management, create new GPO: `Deploy Network Printer`
   - Edit GPO
   - Navigate: **User Configuration** → **Preferences** → **Control Panel Settings** → **Printers**
   - Right-click → **New** → **TCP/IP Printer**
   - Printer path: `\\MORNINGSTAR-DC01\NetworkPrinter`
   - Action: **Create**
   - Click **Common** tab
   - Check **Item-level targeting**
   - Click **Targeting...**
   - Click **New Item** → **Security Group**
   - Group name: `MORNINGSTAR\Principal`
   - Click **Remove** (to exclude Principal)
   - Click **OK**

3. **Link GPO** to **OU=Users**

---

## PART 6: Testing and Verification

### Test Checklist

- [ ] All 11 users can log in to Windows 11 client
- [ ] Roaming profiles work (desktop syncs between PCs)
- [ ] Network drives mapped correctly:
  - [ ] Teachers see P: drive (Private)
  - [ ] All staff see G: drive (General)
- [ ] File permissions work:
  - [ ] Teachers can only access their own Private folder
  - [ ] All staff can access General drive
  - [ ] Web Designer cannot access Private drive
- [ ] Network printer accessible (except Principal)
- [ ] Website accessible from all clients
- [ ] Website login works for all roles
- [ ] Announcements/Events editing works (Admin, Principal, Web Designer)

### Quick Test Commands

**On Windows Server:**
```powershell
# List all users
Get-ADUser -Filter * | Select-Object Name, SamAccountName

# List all groups
Get-ADGroup -Filter * | Select-Object Name

# Check shares
Get-SmbShare

# Test website
Invoke-WebRequest http://localhost
```

**On Windows 11 Client:**
```powershell
# Check domain join
systeminfo | findstr /C:"Domain"

# Check mapped drives
Get-PSDrive -PSProvider FileSystem

# Check GPO
gpresult /r
```

**On Linux Client:**
```bash
# Check domain join
realm list

# Check AD users
getent passwd MORNINGSTAR\\teacher1

# Check mounts
mount | grep MORNINGSTAR
```

---

## Troubleshooting

### Can't join domain
- **Check DNS**: `nslookup morningstar.local` should resolve to 192.168.1.100
- **Check firewall**: Allow ports 389, 445, 53 on IPfire
- **Check time sync**: Client time must be within 5 minutes of server
  ```powershell
  # On server
  w32tm /query /status
  # On client
  w32tm /resync
  ```

### Network drives not mapping
- **Check GPO applied**: `gpresult /r`
- **Check share permissions**: `Get-SmbShareAccess Private`
- **Check user is in correct group**: `Get-ADGroupMember Teachers`
- **Force GPO update**: `gpupdate /force` then restart

### Website not accessible
- **Check Apache running**: `Get-Service Apache2.4`
- **Check PHP working**: Create `test.php` with `<?php phpinfo(); ?>`
- **Check database connection**: Verify `.env` file credentials
- **Check Apache logs**: `C:\Apache24\logs\error.log`
- **Check Windows Firewall**: Allow port 80

### Linux can't join domain
- **Check DNS**: `nslookup morningstar.local`
- **Check time sync**: `sudo ntpdate 192.168.1.100`
- **Check Samba config**: `testparm /etc/samba/smb.conf`
- **Check realm**: `realm list`

---

## Security Notes

1. **Change all default passwords** immediately
2. **Enable Windows Firewall** on all systems
3. **Keep systems updated** with latest patches
4. **Regular backups** of:
   - AD database
   - File shares
   - Database
   - Website files

---

## Support

If you encounter issues:
1. Check Windows Event Viewer for errors
2. Check Apache error logs: `C:\Apache24\logs\error.log`
3. Check MariaDB logs: `C:\Program Files\MariaDB\MariaDB 11.4\data\*.err`
4. Review firewall logs in IPfire

---

**End of Guide**

