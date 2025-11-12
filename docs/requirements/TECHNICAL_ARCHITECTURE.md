# Technical Architecture - School Management System

## System Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Internet / External Network              │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│              pfSense / IPfire Firewall                      │
│              - Network Security                              │
│              - Traffic Filtering                            │
│              IP: 192.168.1.1 (Gateway)                      │
└────────────────────────┬────────────────────────────────────┘
                         │
         ┌───────────────┴───────────────┐
         │                               │
┌────────▼────────┐          ┌─────────▼─────────┐
│   Proxmox Host  │          │   Client Network   │
│                 │          │                    │
│  ┌────────────┐ │          │  - Windows 11 PCs │
│  │ Windows    │ │          │    192.168.1.100+ │
│  │ Server VM  │ │          │  - Linux Client    │
│  │ 2025       │ │          │    192.168.1.150+ │
│  │            │ │          │  - Network Printer │
│  │ IP:        │ │          │    192.168.1.200  │
│  │ 192.168.1.10│          └────────────────────┘
│  │            │ │
│  │ ┌────────┐ │ │
│  │ │AD DC   │ │ │
│  │ │DNS     │ │ │
│  │ │DHCP    │ │ │
│  │ └────────┘ │ │
│  │            │ │
│  │ ┌────────┐ │ │
│  │ │File    │ │ │
│  │ │Server  │ │ │
│  │ └────────┘ │ │
│  │            │ │
│  │ ┌────────┐ │ │
│  │ │NGINX   │ │ │
│  │ │Web     │ │ │
│  │ │Server  │ │ │
│  │ └────────┘ │ │
│  │            │ │
│  │ ┌────────┐ │ │
│  │ │MySQL   │ │ │
│  │ │DB      │ │ │
│  │ │PHPMy   │ │ │
│  │ │Admin   │ │ │
│  │ └────────┘ │ │
│  └────────────┘ │
└─────────────────┘
```

---

## Component Specifications

### 1. Proxmox Host Environment

#### Proxmox Setup:
- **Proxmox VE** - Virtualization platform
- **Windows Server 2025 VM** - Primary server virtual machine
- **Resource Allocation:**
  - CPU: 4+ cores
  - RAM: 8GB+ recommended
  - Storage: 100GB+ for OS and applications
  - Network: Bridge adapter to physical network

#### VM Configuration:
- **VM Name:** `MORNINGSTAR-DC01`
- **OS:** Windows Server 2025
- **IP Address:** `192.168.1.10` (Static)
- **Subnet Mask:** `255.255.255.0`
- **Gateway:** `192.168.1.1` (Firewall)
- **DNS:** `192.168.1.10` (Self - AD DNS)

### 2. Windows Server 2025 VM

#### Roles and Features Required:

**Core Roles:**
- **Active Directory Domain Services (AD DS)**
  - Domain Controller
  - DNS Server (integrated)
  - Global Catalog

- **File and Storage Services**
  - File Server
  - Storage Services
  - SMB Share

- **Web Server (IIS)** - Optional
  - Can be used for internal services
  - External web server will be separate (Apache/Nginx)

**Features:**
- .NET Framework 4.8
- PowerShell (included)
- Remote Server Administration Tools
- Windows Defender (security)

#### Domain Configuration:

**Domain Name:** `morningstar.local` (example)
- Internal domain name
- Not exposed to internet
- DNS resolution for internal resources

**Domain Controller:**
- Primary Domain Controller (PDC)
- DNS integration
- DHCP (optional, if not using router)
- Time synchronization (NTP)

---

### 2. Active Directory Structure

#### Organizational Units (OUs):

```
morningstar.local
├── Users
│   ├── Teachers
│   ├── Administration
│   ├── Principal
│   └── WebDesigner
├── Computers
│   ├── Servers
│   ├── Workstations
│   └── Printers
└── Groups
    ├── Security Groups
    └── Distribution Groups
```

#### Security Groups:

```
SG_Teachers          - All 7 teachers
SG_Administration    - 2 admin staff
SG_Principal        - Principal
SG_WebDesigner       - Web designer
SG_AllStaff          - All authenticated users
SG_WebsiteEditors    - Admin + Principal
SG_FileServer_General_RW - General drive access
SG_FileServer_Private_RW - Private drive access
```

#### User Accounts:

**Naming Convention:** `firstname.lastname` or `firstinitial.lastname`

**Teachers:**
- teacher1@morningstar.local
- teacher2@morningstar.local
- ... (7 total)

**Administration:**
- admin1@morningstar.local
- admin2@morningstar.local

**Principal:**
- principal@morningstar.local

**Web Designer:**
- webdesigner@morningstar.local

---

### 3. File Server Configuration

#### Network Shares:

**General Share:**
- Path: `C:\Shares\General`
- Share Name: `General`
- UNC: `\\server\General`
- Permissions: All authenticated users (Modify)

**Private Share:**
- Path: `C:\Shares\Private`
- Share Name: `Private`
- UNC: `\\server\Private`
- Permissions: Teachers group only (Full Control)

#### Folder Structure:

```
C:\Shares\
├── General\
│   ├── Announcements\
│   ├── Events\
│   ├── Documents\
│   └── Shared\
└── Private\
    ├── Teacher1\
    ├── Teacher2\
    ├── ... (7 teachers)
    └── StudentData\
```

#### NTFS Permissions:

**General Share:**
- Authenticated Users: Modify
- SYSTEM: Full Control
- Administrators: Full Control

**Private Share:**
- SG_Teachers: Full Control (subfolders)
- SG_Principal: List Folder Contents (root only)
- SG_WebDesigner: List Folder Contents (root only)
- SYSTEM: Full Control
- Administrators: Full Control

---

### 4. Web Server Configuration

#### Options:

**Option A: Apache**
- Version: 2.4.x or latest
- PHP: 8.2+ (for dynamic content)
- Modules: mod_rewrite, mod_ssl, mod_php

**Option B: Nginx**
- Version: Latest stable
- PHP-FPM: 8.2+
- Reverse proxy capabilities

#### Installation Location:

- **On Windows Server VM:** `C:\WebServer\` or `C:\inetpub\wwwroot\`
- **Running in Proxmox VM:** Same Windows Server instance

#### Website Structure:

```
/var/www/morningstar/
├── public/
│   ├── index.php
│   ├── announcements/
│   ├── events/
│   └── assets/
├── app/
│   ├── controllers/
│   ├── models/
│   └── views/
└── config/
```

---

### 5. Database Configuration

#### Options:

**Option A: MariaDB**
- Version: 11.x or latest
- Port: 3306
- Character Set: utf8mb4
- Collation: utf8mb4_unicode_ci

**Option B: MySQL**
- Version: 8.0+
- Same configuration as MariaDB

**Option C: PostgreSQL**
- Version: 15+
- Port: 5432

#### Database Structure:

```
morningstar_db
├── users (if custom auth)
├── announcements
├── events
├── students
├── attendance
├── courses
└── progress
```

#### Access Control:

- Web application user: Limited permissions
- Web designer: Read-only access
- Backup user: Backup permissions only

---

### 6. Network Configuration

#### IP Addressing Scheme:

**Server Network:**
- Server IP: `192.168.1.10` (example)
- Subnet: `255.255.255.0` (/24)
- Gateway: `192.168.1.1`
- DNS: `192.168.1.10` (self)

**Client Network:**
- Range: `192.168.1.100-200`
- DHCP: Enabled (via router or server)
- DNS: `192.168.1.10`

#### Firewall Rules (pfSense/IPfire):

**Inbound Rules:**
- Allow: HTTP (80) → Web Server
- Allow: HTTPS (443) → Web Server
- Allow: RDP (3389) → Server (restricted IPs)
- Allow: SMB (445) → File Server (internal only)
- Deny: All other inbound

**Outbound Rules:**
- Allow: DNS (53)
- Allow: HTTP/HTTPS (updates)
- Allow: NTP (123)
- Allow: All established connections

---

### 7. Client Configuration

#### Windows 11 Clients:

**Domain Join Process:**
1. Configure network settings
2. Join domain: `morningstar.local`
3. Authenticate with domain admin
4. Restart and log in with domain credentials

**Group Policy:**
- Roaming profiles enabled
- Network drives mapped automatically
- Printer installation
- Security settings

**Mapped Drives:**
- `G:` → `\\server\General`
- `P:` → `\\server\Private` (teachers only)

#### Linux Client:

**Integration Methods:**

**Option A: Samba + Winbind**
- Authenticate against AD
- Access Windows shares
- Use domain credentials

**Option B: SSSD (System Security Services Daemon)**
- Modern approach
- Better integration
- Kerberos authentication

**Configuration:**
- Join to AD domain
- Configure `/etc/samba/smb.conf`
- Mount Windows shares
- Use domain credentials for login

**Distribution:** Ubuntu 22.04 LTS or similar

---

### 8. Printing Configuration

#### Network Printer:

**Configuration:**
- Shared on Windows Server or dedicated print server
- Accessible via `\\server\NetworkPrinter`
- Domain authentication required
- Available to: Teachers, Admin, Web Designer

**Driver Installation:**
- Via Group Policy (automatic)
- Or manual installation per workstation

#### Principal's Printer:

**Configuration:**
- Local USB/Network printer
- Installed on Principal's workstation only
- Not shared on network
- Local access only

---

### 9. Security Configuration

#### Active Directory Security:

**Password Policy:**
- Minimum length: 12 characters
- Complexity: Required
- Maximum age: 90 days
- Minimum age: 1 day
- Password history: 12 passwords

**Account Lockout:**
- Lockout threshold: 5 attempts
- Lockout duration: 30 minutes
- Reset counter: 30 minutes

**Kerberos:**
- Ticket lifetime: 10 hours
- Renewal: Enabled

#### File Server Security:

**Encryption:**
- BitLocker (if available)
- SMB encryption enabled
- TLS for web traffic

**Auditing:**
- File access auditing enabled
- Failed login attempts logged
- Permission changes logged

#### Network Security:

**Firewall:**
- Default deny policy
- Explicit allow rules only
- Regular rule review

**Updates:**
- Windows Update: Automatic
- Antivirus: Windows Defender + updates
- Regular security patches

---

### 10. Backup and Recovery

#### Backup Strategy:

**Daily Backups:**
- Active Directory (system state)
- File server data
- Database backups
- Web server configuration

**Backup Storage:**
- External drive or network storage
- Retention: 30 days
- Offsite backup (recommended)

**Recovery Testing:**
- Monthly restore tests
- Document recovery procedures
- RTO: 4 hours
- RPO: 24 hours

---

## Network Topology

```
                    Internet
                       │
                  [Firewall]
              pfSense/IPfire
           192.168.1.1 (Gateway)
                       │
        ┌──────────────┼──────────────┐
        │              │              │
   [Proxmox]      [Switch]      [Clients]
   Host           192.168.1.x   192.168.1.100+
   192.168.1.5         │              │
        │              │              │
   [Windows]      [Network]    [Workstations]
   Server VM      Devices      [Printers]
   192.168.1.10
        │
   ┌────┴────┐
   │         │
[Services] [Domain]
   │         │
   ├─ AD DC  │
   ├─ DNS    │
   ├─ DHCP   │
   ├─ File   │
   ├─ NGINX  │
   └─ MySQL  │
```

### Network Components:

**Firewall (pfSense/IPfire):**
- IP: 192.168.1.1
- Gateway for entire network
- NAT, Firewall rules

**Proxmox Host:**
- Management IP: 192.168.1.5 (example)
- Virtualization platform
- Hosts Windows Server VM

**Windows Server VM (in Proxmox):**
- IP: 192.168.1.10
- All server roles running on this VM
- Domain Controller, DNS, DHCP, File Server, Web Server, Database Server

**Client Network:**
- Range: 192.168.1.100-199
- Windows 11 PCs: 192.168.1.100-149
- Linux Client: 192.168.1.150
- Network Printer: 192.168.1.200
- Principal's Printer: Local (not networked)

---

## Software Versions

| Component | Version | Notes |
|-----------|---------|-------|
| Windows Server | 2025 | Latest available |
| Active Directory | 2025 | Integrated |
| Windows Client | 11 | Latest |
| Linux Client | Ubuntu 22.04+ | LTS recommended |
| Web Server | Apache 2.4+ / Nginx 1.24+ | Latest stable |
| Database | MariaDB 11+ / MySQL 8+ | Latest stable |
| PHP | 8.2+ | If using PHP |
| Firewall | pfSense / IPfire | Latest stable |

---

## Port Requirements

| Service | Port | Protocol | Direction |
|---------|------|----------|-----------|
| DNS | 53 | TCP/UDP | Both |
| DHCP | 67/68 | UDP | Both |
| HTTP | 80 | TCP | Inbound |
| HTTPS | 443 | TCP | Inbound |
| LDAP | 389 | TCP/UDP | Internal |
| LDAPS | 636 | TCP | Internal |
| Kerberos | 88 | TCP/UDP | Internal |
| SMB | 445 | TCP | Internal |
| RDP | 3389 | TCP | Internal (restricted) |
| MySQL/MariaDB | 3306 | TCP | Internal |
| PostgreSQL | 5432 | TCP | Internal |

---

## Performance Requirements

- **Login Time:** < 30 seconds
- **File Access:** < 2 seconds
- **Website Load:** < 3 seconds
- **Profile Sync:** < 1 minute
- **Concurrent Users:** 11+ (with headroom)

---

## Scalability Considerations

- Design for future growth
- Easy to add users
- Easy to add storage
- Easy to add workstations
- Modular architecture

