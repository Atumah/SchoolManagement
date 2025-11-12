# Implementation Checklist - School Management System

## Phase 1: Planning & Preparation

### Documentation
- [ ] Network architecture diagram created
- [ ] IP addressing scheme defined
- [ ] Domain name decided (`morningstar.local` or similar)
- [ ] User account naming convention defined
- [ ] File server structure planned
- [ ] Security policy documented
- [ ] Backup strategy defined

### Hardware/Software Preparation
- [ ] Windows Server 2025 license obtained
- [ ] Server hardware ready
- [ ] Network infrastructure ready
- [ ] Firewall hardware/software ready
- [ ] Client workstations ready
- [ ] Printers ready
- [ ] Network cables/switches ready

### Prerequisites
- [ ] Basic network connectivity tested
- [ ] Server hardware tested
- [ ] Installation media ready
- [ ] Documentation reviewed

---

## Phase 2: Windows Server 2025 Installation

### Server Installation
- [ ] Windows Server 2025 installed
- [ ] Server configured with static IP
- [ ] Server renamed (e.g., `MORNINGSTAR-DC01`)
- [ ] Windows Update completed
- [ ] Antivirus installed and updated
- [ ] Remote Desktop enabled (if needed)

### Initial Configuration
- [ ] Time zone configured
- [ ] Regional settings configured
- [ ] Administrator password set (strong)
- [ ] Local administrator account secured
- [ ] Firewall rules configured (basic)

---

## Phase 3: Active Directory Setup

### AD DS Installation
- [ ] AD DS role installed
- [ ] Domain Controller promoted
- [ ] Domain created (`morningstar.local`)
- [ ] DNS integrated and working
- [ ] Forest functional level set
- [ ] Domain functional level set

### DNS Configuration
- [ ] DNS zones created
- [ ] Forward lookup zones configured
- [ ] Reverse lookup zones configured
- [ ] DNS records verified
- [ ] DNS resolution tested

### Domain Configuration
- [ ] Domain name verified
- [ ] NetBIOS name verified
- [ ] Domain controller health checked
- [ ] Replication tested (if multiple DCs)

---

## Phase 4: User & Group Management

### Security Groups Created
- [ ] SG_Teachers created
- [ ] SG_Administration created
- [ ] SG_Principal created
- [ ] SG_WebDesigner created
- [ ] SG_AllStaff created
- [ ] SG_WebsiteEditors created
- [ ] File server groups created

### User Accounts Created
- [ ] Teacher1 account created
- [ ] Teacher2 account created
- [ ] Teacher3 account created
- [ ] Teacher4 account created
- [ ] Teacher5 account created
- [ ] Teacher6 account created
- [ ] Teacher7 account created
- [ ] Admin1 account created
- [ ] Admin2 account created
- [ ] Principal account created
- [ ] WebDesigner account created

### Group Membership
- [ ] All teachers added to SG_Teachers
- [ ] Admin users added to SG_Administration
- [ ] Principal added to SG_Principal
- [ ] Web designer added to SG_WebDesigner
- [ ] All users added to SG_AllStaff
- [ ] Admin + Principal added to SG_WebsiteEditors

### User Configuration
- [ ] Password policy configured
- [ ] Account lockout policy configured
- [ ] User profiles configured
- [ ] Home directories created (if needed)
- [ ] Email addresses configured (if needed)

---

## Phase 5: File Server Setup

### Share Creation
- [ ] General share folder created (`C:\Shares\General`)
- [ ] Private share folder created (`C:\Shares\Private`)
- [ ] General share published (`\\server\General`)
- [ ] Private share published (`\\server\Private`)

### NTFS Permissions
- [ ] General share NTFS permissions configured
- [ ] Private share NTFS permissions configured
- [ ] Teacher subfolders created in Private share
- [ ] Permissions tested for each group

### Share Permissions
- [ ] General share permissions configured
- [ ] Private share permissions configured
- [ ] Share access tested

### Folder Structure
- [ ] General/Announcements folder created
- [ ] General/Events folder created
- [ ] General/Documents folder created
- [ ] General/Shared folder created
- [ ] Private/Teacher1 folder created
- [ ] Private/Teacher2 folder created
- [ ] Private/Teacher3 folder created
- [ ] Private/Teacher4 folder created
- [ ] Private/Teacher5 folder created
- [ ] Private/Teacher6 folder created
- [ ] Private/Teacher7 folder created
- [ ] Private/StudentData folder created

---

## Phase 6: Roaming Profiles

### Profile Configuration
- [ ] Roaming profile path configured in AD
- [ ] Profile share created (`\\server\Profiles`)
- [ ] Profile permissions configured
- [ ] Profile storage quota set (if needed)

### Group Policy
- [ ] Roaming profile GPO created
- [ ] Profile path configured via GPO
- [ ] Profile settings configured
- [ ] GPO linked to appropriate OUs
- [ ] GPO tested

### Testing
- [ ] User logs in to PC1, creates file
- [ ] User logs out
- [ ] User logs in to PC2
- [ ] File appears on PC2 (profile roaming works)

---

## Phase 7: Network & Firewall

### pfSense/IPfire Installation
- [ ] Firewall installed
- [ ] Basic configuration completed
- [ ] Network interfaces configured
- [ ] Default gateway configured

### Firewall Rules
- [ ] Inbound HTTP rule created
- [ ] Inbound HTTPS rule created
- [ ] Inbound RDP rule created (restricted)
- [ ] Inbound SMB rule created (internal)
- [ ] Outbound DNS rule created
- [ ] Outbound HTTP/HTTPS rule created
- [ ] Outbound NTP rule created
- [ ] Default deny rule active

### Network Configuration
- [ ] DHCP configured (if on firewall)
- [ ] DNS forwarding configured
- [ ] NAT rules configured
- [ ] Port forwarding configured (if needed)

### Testing
- [ ] Internet connectivity tested
- [ ] Internal network connectivity tested
- [ ] Firewall rules tested
- [ ] Logging verified

---

## Phase 8: Web Server Setup

### Web Server Installation
- [ ] Apache/Nginx installed
- [ ] PHP installed (if needed)
- [ ] Web server started
- [ ] Web server configured to start on boot

### Configuration
- [ ] Virtual host configured
- [ ] Document root configured
- [ ] PHP configuration (if applicable)
- [ ] SSL certificate installed (if HTTPS)
- [ ] Firewall rules for web server

### Website Structure
- [ ] Website directory created
- [ ] Basic index page created
- [ ] Announcements section created
- [ ] Events section created
- [ ] Content management system integrated

### Testing
- [ ] Website accessible locally
- [ ] Website accessible from network
- [ ] PHP working (if applicable)
- [ ] Database connection working

---

## Phase 9: Database Setup

### Database Installation
- [ ] MySQL/MariaDB/PostgreSQL installed
- [ ] Database service started
- [ ] Database configured to start on boot
- [ ] Root password set

### Database Configuration
- [ ] Database created (`morningstar_db`)
- [ ] Character set configured (utf8mb4)
- [ ] Collation configured
- [ ] Database user created
- [ ] Database user permissions set

### Schema Creation
- [ ] Users table created (if custom auth)
- [ ] Announcements table created
- [ ] Events table created
- [ ] Students table created
- [ ] Attendance table created
- [ ] Courses table created
- [ ] Progress table created

### Access Control
- [ ] Web application user created
- [ ] Web designer read-only user created
- [ ] Backup user created
- [ ] Permissions tested

---

## Phase 10: Windows 11 Client Setup

### Domain Join
- [ ] PC1 joined to domain
- [ ] PC2 joined to domain
- [ ] PC3 joined to domain
- [ ] ... (all Windows 11 PCs)

### Client Configuration
- [ ] Network drives mapped via GPO
- [ ] Printer installed via GPO
- [ ] Group Policy applied
- [ ] Roaming profile working

### Testing
- [ ] User can log in with domain credentials
- [ ] Roaming profile works
- [ ] Network drives accessible
- [ ] Printer accessible
- [ ] Website accessible

---

## Phase 11: Linux Client Setup

### Linux Installation
- [ ] Linux distribution installed (Ubuntu 22.04+)
- [ ] System updated
- [ ] Basic configuration completed

### AD Integration
- [ ] Samba installed
- [ ] Winbind installed (or SSSD)
- [ ] AD domain joined
- [ ] Domain authentication working

### Share Access
- [ ] Windows shares accessible
- [ ] General share mounted
- [ ] Private share mounted (if teacher)
- [ ] Credentials stored securely

### Testing
- [ ] User can log in with domain credentials
- [ ] Windows shares accessible
- [ ] File operations work
- [ ] Network printer accessible (if applicable)

---

## Phase 12: Printer Configuration

### Network Printer
- [ ] Network printer installed on server
- [ ] Printer shared on network
- [ ] Printer drivers installed
- [ ] Printer permissions configured
- [ ] GPO created for automatic installation
- [ ] Printer accessible from Windows clients
- [ ] Printer accessible from Linux client (if applicable)

### Principal's Printer
- [ ] Printer installed on Principal's PC
- [ ] Printer configured locally
- [ ] Printer NOT shared
- [ ] Printer tested
- [ ] Other users verified cannot access

---

## Phase 13: Security Hardening

### Server Security
- [ ] Unnecessary services disabled
- [ ] Firewall configured
- [ ] Antivirus installed and updated
- [ ] Windows Update configured
- [ ] Security patches applied
- [ ] Event logging configured
- [ ] Audit policies configured

### AD Security
- [ ] Password policy enforced
- [ ] Account lockout policy enforced
- [ ] Kerberos configured
- [ ] LDAP signing enforced
- [ ] SMB encryption enabled

### Network Security
- [ ] Firewall rules reviewed
- [ ] Unnecessary ports closed
- [ ] VPN configured (if remote access needed)
- [ ] Intrusion detection configured (if applicable)

---

## Phase 14: Backup & Recovery

### Backup Configuration
- [ ] Backup software installed
- [ ] Backup schedule configured
- [ ] AD backup configured
- [ ] File server backup configured
- [ ] Database backup configured
- [ ] Web server backup configured

### Backup Testing
- [ ] Backup job runs successfully
- [ ] Backup verified
- [ ] Restore tested
- [ ] Recovery procedure documented

---

## Phase 15: Testing & Validation

### User Testing
- [ ] Teacher1 can log in
- [ ] Teacher1 can access General drive
- [ ] Teacher1 can access Private drive
- [ ] Teacher1 can use student management
- [ ] Admin1 can log in
- [ ] Admin1 can access General drive
- [ ] Admin1 CANNOT access Private drive
- [ ] Admin1 can edit website announcements
- [ ] Principal can log in
- [ ] Principal can access General drive
- [ ] Principal CANNOT access Private drive content
- [ ] Principal can use local printer
- [ ] Web designer can log in
- [ ] Web designer can access website code
- [ ] Web designer CANNOT access Private drive content

### Functionality Testing
- [ ] Roaming profiles work on all PCs
- [ ] Network drives accessible
- [ ] Website accessible
- [ ] Database queries work
- [ ] Printers work
- [ ] File permissions correct
- [ ] Group policies applied

### Performance Testing
- [ ] Login time acceptable (<30s)
- [ ] File access fast (<2s)
- [ ] Website loads quickly (<3s)
- [ ] Profile sync acceptable (<1min)

---

## Phase 16: Documentation

### Technical Documentation
- [ ] Network diagram completed
- [ ] AD structure documented
- [ ] User accounts documented
- [ ] Group structure documented
- [ ] File server permissions documented
- [ ] Firewall rules documented
- [ ] Backup procedures documented
- [ ] Recovery procedures documented

### User Documentation
- [ ] Teacher user guide created
- [ ] Admin user guide created
- [ ] Principal user guide created
- [ ] Web designer guide created
- [ ] Login instructions created
- [ ] FAQ created

### Administrator Documentation
- [ ] Installation guide created
- [ ] Configuration guide created
- [ ] Troubleshooting guide created
- [ ] Maintenance procedures documented
- [ ] Contact information documented

---

## Phase 17: Handover & Training

### Training
- [ ] Teachers trained
- [ ] Admin staff trained
- [ ] Principal trained
- [ ] Web designer trained
- [ ] Administrator trained (if different person)

### Documentation Handover
- [ ] All documentation provided
- [ ] Passwords documented (securely)
- [ ] Access credentials provided
- [ ] Support procedures explained

### Final Checklist
- [ ] All requirements met
- [ ] All users can work
- [ ] All systems operational
- [ ] Documentation complete
- [ ] Training completed
- [ ] Project signed off

---

## Notes

- Check off items as you complete them
- Add notes for any issues encountered
- Update this checklist as requirements change
- Use this for progress tracking

