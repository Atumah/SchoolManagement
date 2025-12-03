# Quick Start Guide
## Infrastructure Deployment - Quick Reference

This is a condensed version of the full deployment guide. Use this for quick reference after reading the full guide.

---

## Prerequisites Checklist

- [ ] 4 Proxmox VMs created
- [ ] All ISOs mounted
- [ ] Repository cloned to Windows Server

---

## IPfire Firewall (15 minutes)

1. Boot and set IP: `192.168.1.1`
2. Access web UI: `https://192.168.1.1:444`
3. Add firewall rules:
   - HTTP (80) → 192.168.1.10
   - HTTPS (443) → 192.168.1.10
   - SMB (445) → 192.168.1.10 (from 192.168.1.0/24)
   - RDP (3389) → 192.168.1.10 (from 192.168.1.0/24)
   - LDAP (389) → 192.168.1.10 (from 192.168.1.0/24)
   - DNS (53) → 192.168.1.10 (from 192.168.1.0/24)

---

## Windows Server 2022 (2-3 hours)

### Initial Setup
1. Install Windows Server 2022
2. Set static IP: `192.168.1.10`
3. Rename: `MORNINGSTAR-DC01`
4. Install updates

### Install AD Domain Services
```powershell
cd C:\SchoolManagement
.\scripts\windows-server\01-install-ad-ds.ps1
# Enter Safe Mode password
# Server will restart
```

### Create Users and Groups
```powershell
.\scripts\windows-server\02-create-users-groups.ps1
# Creates 11 users with password: ChangeMe123!
```

### Create File Shares
```powershell
# Create D: drive first (Disk Management)
.\scripts\windows-server\03-create-file-shares.ps1
```

### Configure Roaming Profiles
```powershell
.\scripts\windows-server\04-configure-roaming-profiles.ps1
```

### Install MariaDB
1. Download from: https://mariadb.org/download/
2. Install with root password
3. Create database:
   ```sql
   CREATE DATABASE schoolmanagement;
   CREATE USER 'schoolapp'@'localhost' IDENTIFIED BY 'YourPassword123!';
   GRANT ALL PRIVILEGES ON schoolmanagement.* TO 'schoolapp'@'localhost';
   FLUSH PRIVILEGES;
   ```
4. Import schema:
   ```powershell
   "C:\Program Files\MariaDB\MariaDB 11.4\bin\mysql.exe" -u schoolapp -p schoolmanagement < database_schema.sql
   ```

### Install Apache
1. Download from: https://httpd.apache.org/download.cgi
2. Extract to: `C:\Apache24`
3. Edit `C:\Apache24\conf\httpd.conf`:
   - Change `SRVROOT` to `C:/Apache24`
   - Uncomment `rewrite_module` and `headers_module`
   - Change `DocumentRoot` to `C:/inetpub/wwwroot/schoolmanagement/public`
   - Change `DirectoryIndex` to `index.php index.html`
   - Add PHP module lines at end
4. Install service:
   ```powershell
   cd C:\Apache24\bin
   .\httpd.exe -k install
   .\httpd.exe -k start
   ```

### Install PHP
1. Download PHP 8.2 Thread Safe from: https://windows.php.net/download/
2. Extract to: `C:\PHP`
3. Copy `php.ini-development` to `php.ini`
4. Edit `php.ini`:
   - Uncomment: `mysqli`, `pdo_mysql`, `intl`, `zip`
   - Set `extension_dir = "C:/PHP/ext"`
   - Set `session.save_path = "C:/Windows/Temp"`
5. Restart Apache:
   ```powershell
   cd C:\Apache24\bin
   .\httpd.exe -k restart
   ```

### Install Composer
1. Download from: https://getcomposer.org/download/
2. Run installer

### Deploy Website
```powershell
.\scripts\windows-server\05-deploy-website.ps1
# Enter database password when prompted

cd C:\inetpub\wwwroot\schoolmanagement
php scripts\create_admin.php
```

### Test Website
- Open: `http://192.168.1.10`
- Login: `admin` / `admin123`

---

## Windows 11 Client (30 minutes)

1. Install Windows 11
2. Set static IP: `192.168.1.20`
3. Set DNS: `192.168.1.10`
4. Join domain:
   - System Properties → Change → Domain: `morningstar.local`
   - Credentials: `MORNINGSTAR\Administrator`
5. Restart and login as: `MORNINGSTAR\teacher1` / `ChangeMe123!`
6. Verify:
   - Network drives (P: and G:) appear
   - Website accessible: `http://morningstar.local`

---

## Linux (Zorin) Client (30 minutes)

1. Install Zorin OS
2. Configure network:
   ```bash
   sudo ./scripts/linux-client/04-configure-network.sh
   ```
3. Install packages:
   ```bash
   sudo ./scripts/linux-client/01-install-packages.sh
   ```
4. Join domain:
   ```bash
   sudo ./scripts/linux-client/02-join-domain.sh
   # Enter Administrator password
   ```
5. Mount shares:
   ```bash
   sudo ./scripts/linux-client/03-mount-shares.sh
   # Enter domain username and password
   ```
6. Restart and login as: `MORNINGSTAR\teacher1`

---

## Group Policy (30 minutes)

1. Open: `gpmc.msc`
2. Create GPO: `Map Network Drives`
3. Edit → User Configuration → Preferences → Drive Maps
4. Map P: drive:
   - Location: `\\MORNINGSTAR-DC01\Private\%USERNAME%`
   - Targeting: Security Group = Teachers
5. Map G: drive:
   - Location: `\\MORNINGSTAR-DC01\General`
6. Link to OU=Users
7. On client: `gpupdate /force` and restart

---

## Testing Checklist

- [ ] All users can login
- [ ] Network drives mapped
- [ ] Roaming profiles work
- [ ] Website accessible
- [ ] File permissions correct
- [ ] Printer accessible (except Principal)

---

## Common Issues

**Can't join domain:**
- Check DNS: `nslookup morningstar.local`
- Check time sync
- Check firewall rules

**Drives not mapping:**
- Run: `gpupdate /force`
- Check GPO is linked
- Restart client

**Website not working:**
- Check Apache: `Get-Service Apache2.4`
- Check PHP: Create `test.php` with `<?php phpinfo(); ?>`
- Check database connection in `.env`

---

## Important Passwords to Record

- [ ] Windows Server Administrator
- [ ] AD Safe Mode password
- [ ] MariaDB root password
- [ ] MariaDB schoolapp password
- [ ] IPfire root password
- [ ] Website admin password (change from default!)

---

**For detailed instructions, see: DEPLOYMENT_GUIDE.md**


