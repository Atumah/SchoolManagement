# Infrastructure Deployment Documentation

This directory contains all documentation and scripts for deploying "The Morningstar" school infrastructure.

## 📁 Directory Structure

```
docs/infrastructure/
├── README.md              # This file
├── DEPLOYMENT_GUIDE.md    # Complete step-by-step guide
└── QUICK_START.md         # Quick reference guide

scripts/
├── windows-server/        # PowerShell scripts for Windows Server
│   ├── 01-install-ad-ds.ps1
│   ├── 02-create-users-groups.ps1
│   ├── 03-create-file-shares.ps1
│   ├── 04-configure-roaming-profiles.ps1
│   └── 05-deploy-website.ps1
└── linux-client/          # Bash scripts for Linux client
    ├── 01-install-packages.sh
    ├── 02-join-domain.sh
    ├── 03-mount-shares.sh
    └── 04-configure-network.sh

config/
└── windows-server/        # Configuration file examples
    ├── apache-httpd.conf.example
    └── php.ini.example
```

## 🚀 Getting Started

1. **Read the full guide first**: [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)
2. **Use quick reference**: [QUICK_START.md](QUICK_START.md) for quick commands
3. **Run scripts in order**: Scripts are numbered and should be run sequentially

## 📋 Deployment Order

### Phase 1: Network (IPfire)
- Configure firewall rules
- Set up routing

### Phase 2: Windows Server
1. Initial setup (IP, name, updates)
2. Install AD DS (`01-install-ad-ds.ps1`)
3. Create users/groups (`02-create-users-groups.ps1`)
4. Create file shares (`03-create-file-shares.ps1`)
5. Configure roaming profiles (`04-configure-roaming-profiles.ps1`)
6. Install MariaDB (manual)
7. Install Apache (manual)
8. Install PHP (manual)
9. Install Composer (manual)
10. Deploy website (`05-deploy-website.ps1`)

### Phase 3: Clients
- Windows 11: Join domain manually
- Linux: Run scripts in order

### Phase 4: Group Policy
- Configure drive mapping
- Deploy printer

## 🔧 Script Usage

### Windows Server Scripts

All scripts must be run as Administrator:

```powershell
# Open PowerShell as Administrator
cd C:\SchoolManagement
.\scripts\windows-server\01-install-ad-ds.ps1
```

### Linux Client Scripts

All scripts must be run with sudo:

```bash
cd /path/to/SchoolManagement
sudo ./scripts/linux-client/01-install-packages.sh
```

## 📝 Important Notes

1. **Passwords**: Write down all passwords in a secure location
2. **Backups**: Create backups before major changes
3. **Testing**: Test each component after installation
4. **Documentation**: Keep notes of any customizations

## 🆘 Troubleshooting

See the troubleshooting section in [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for common issues and solutions.

## 📞 Support

If you encounter issues:
1. Check the full deployment guide
2. Review error logs
3. Verify network connectivity
4. Check firewall rules

---

**Last Updated**: 2025-01-27


