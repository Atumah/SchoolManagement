# Infrastructure Setup - Overview

This document provides an overview of the infrastructure deployment for "The Morningstar" primary school.

## 📦 What Has Been Created

All necessary scripts, documentation, and configuration templates have been created in this repository:

### ✅ PowerShell Scripts (Windows Server)
Located in: `scripts/windows-server/`

1. **01-install-ad-ds.ps1** - Installs Active Directory Domain Services and promotes server to Domain Controller
2. **02-create-users-groups.ps1** - Creates all users, groups, and organizational units
3. **03-create-file-shares.ps1** - Creates Private and General network drives with proper permissions
4. **04-configure-roaming-profiles.ps1** - Configures roaming profiles for all users
5. **05-deploy-website.ps1** - Deploys the website to the server

### ✅ Bash Scripts (Linux Client)
Located in: `scripts/linux-client/`

1. **01-install-packages.sh** - Installs Samba, Winbind, and required packages
2. **02-join-domain.sh** - Joins Linux client to Active Directory domain
3. **03-mount-shares.sh** - Mounts Private and General network drives
4. **04-configure-network.sh** - Configures static IP address

### ✅ Documentation
Located in: `docs/infrastructure/`

1. **DEPLOYMENT_GUIDE.md** - Complete step-by-step guide with detailed instructions
2. **QUICK_START.md** - Quick reference guide for experienced users
3. **README.md** - Overview of infrastructure documentation

### ✅ Configuration Templates
Located in: `config/`

1. **windows-server/apache-httpd.conf.example** - Apache configuration example
2. **windows-server/php.ini.example** - PHP configuration example
3. **linux-client/smb.conf.example** - Samba configuration example

## 🚀 Getting Started

1. **Read the full deployment guide first**:
   ```
   docs/infrastructure/DEPLOYMENT_GUIDE.md
   ```

2. **Clone this repository on Windows Server**:
   ```powershell
   cd C:\
   git clone <your-repository-url> SchoolManagement
   ```

3. **Follow the deployment guide step by step**

## 📋 Infrastructure Components

### VMs Required
- **IPfire Firewall** - Network security and routing
- **Windows Server 2022** - AD, File Server, Web Server, Database
- **Windows 11 Client** - Primary client OS
- **Linux (Zorin) Client** - Linux client for one teacher

### Network Configuration
- **IPfire**: 192.168.1.1
- **Windows Server**: 192.168.1.10
- **Windows 11 Client**: 192.168.1.20
- **Linux Client**: 192.168.1.21

### Domain Information
- **Domain Name**: morningstar.local
- **Domain NetBIOS**: MORNINGSTAR
- **Server Name**: MORNINGSTAR-DC01

### Users Created
- 7 Teachers (teacher1 - teacher7)
- 2 Administration (admin1, admin2)
- 1 Principal (principal1)
- 1 Web Designer (webdesigner1)

**Default Password**: ChangeMe123! (users must change on first login)

### Network Shares
- **Private Drive**: `\\MORNINGSTAR-DC01\Private` (Teachers only, mapped as P:)
- **General Drive**: `\\MORNINGSTAR-DC01\General` (All staff, mapped as G:)
- **Profiles**: `\\MORNINGSTAR-DC01\Profiles` (Roaming profiles)

### Website
- **URL**: http://morningstar.local or http://192.168.1.10
- **Default Admin**: admin / admin123 (change immediately!)

## 🔒 Security Checklist

- [ ] Change all default passwords
- [ ] Enable Windows Firewall on all systems
- [ ] Configure IPfire firewall rules
- [ ] Set up regular backups
- [ ] Enable audit logging
- [ ] Keep all systems updated

## 📝 Important Notes

1. **All scripts must be run as Administrator** (Windows) or **with sudo** (Linux)
2. **Write down all passwords** in a secure location
3. **Test each component** after installation
4. **Create backups** before major changes
5. **Follow the scripts in order** - they are numbered sequentially

## 🆘 Need Help?

1. Check the troubleshooting section in `docs/infrastructure/DEPLOYMENT_GUIDE.md`
2. Review error logs:
   - Windows Event Viewer
   - Apache logs: `C:\Apache24\logs\error.log`
   - MariaDB logs: `C:\Program Files\MariaDB\MariaDB 11.4\data\*.err`
3. Verify network connectivity and firewall rules

## 📚 Documentation Structure

```
SchoolManagement/
├── scripts/
│   ├── windows-server/     # PowerShell scripts
│   └── linux-client/      # Bash scripts
├── docs/
│   └── infrastructure/    # Deployment documentation
├── config/
│   ├── windows-server/    # Configuration examples
│   └── linux-client/      # Configuration examples
└── INFRASTRUCTURE_SETUP.md  # This file
```

---

**Ready to deploy? Start with**: `docs/infrastructure/DEPLOYMENT_GUIDE.md`

