# Project 2 – School Management System
## Primary School "The Morningstar"

## Project Overview

The primary school "The Morningstar" is currently in the middle of a turbulent start-up period. In the new housing estate, which will soon be largely completed, the primary school is almost ready to start. To prevent staff from using various systems of their own, such as agendas and Excel sheets, etc., a fixed working environment must be created. The security of the sensitive data is paramount, in connection with the law on privacy. The infrastructure has a high urgency, due to the ordering and timely delivery of the necessary network components.

---

## User Requirements

### Total Users: 11

#### 1. Teachers (7 users)
**Responsibilities:**
- Enter attendance and progress
- Access to private network drive
- Access to general network drive
- Keep track of courses, progress and attendance
- Appointments and note-taking
- Overview of their own students

**Access Requirements:**
- Private network drive (read/write)
- General network drive (read/write)
- Student management system
- Attendance tracking system
- Progress tracking system
- Calendar/appointment system

#### 2. Admin Staff (2 users)
**Responsibilities:**
- Access to general network drive
- Make changes to general website (announcements and events)

**Access Requirements:**
- General network drive (read/write)
- Website content management (announcements, events)

#### 3. Principal (1 user)
**Responsibilities:**
- Access to general network drive
- Make changes to general website (announcements and events)
- Has own printer (separate from network printer)

**Access Requirements:**
- General network drive (read/write)
- Website content management (announcements, events)
- Private printer (local, not network)

#### 4. Part-time Administrator and Web Designer (1 user)
**Responsibilities:**
- Can see everything except contents of private drives
- Website design and maintenance

**Access Requirements:**
- General network drive (read)
- Website full access (design, content, code)
- Database access (read-only for private data)
- Cannot access private teacher drives

---

## Technical Requirements

### 1. Windows Server 2025 AD Server

#### Components:
- **Active Directory Domain Services (AD DS)**
  - Domain Controller
  - User authentication and authorization
  - Group Policy management
  - Roaming profiles

- **Users & Groups:**
  - Teachers Group (7 users)
  - Administration Group (2 users)
  - Principal Group (1 user)
  - Website Designer Group (1 user)

- **Database:**
  - Open-source database solution
  - Requirements: MySQL/MariaDB/PostgreSQL

- **Web Server:**
  - Open-source web server
  - Requirements: Apache/Nginx

- **File Server:**
  - Network shares:
    - General network drive (accessible by all)
    - Private network drive (teachers only)
  - NTFS permissions management
  - Shared folder access control

### 2. Network Security

- **pfSense Firewall**
  - Alternative: IPfire
  - Network security and traffic filtering
  - Firewall rules configuration

### 3. Client Operating Systems

- **Windows 11 Clients**
  - Multiple fixed PCs
  - Domain-joined
  - Roaming profiles enabled
  - Access to network drives
  - Network printer access

- **Linux Client (1 user)**
  - One teacher wants Linux instead of Windows 11
  - Must integrate with Active Directory
  - Access to network drives
  - Access to network resources

### 4. Printing Infrastructure

- **Network Printer**
  - Shared by all users (except Principal)
  - Accessible via domain authentication

- **Principal's Printer**
  - Separate from network printer
  - Local to Principal's workstation
  - Not accessible by other users

---

## Functional Requirements

### 1. User Authentication & Profiles

- **Single Sign-On (SSO)**
  - Users log in once with domain credentials
  - Access all resources without re-authentication

- **Roaming Profiles**
  - User's desktop, documents, and settings follow them
  - Works on any fixed PC at the school
  - Profile synchronization across workstations

- **Any PC Login**
  - Users can log in to any fixed PC
  - Their personalized environment appears
  - Desktop, programs, and files available

### 2. File Management

- **General Network Drive**
  - Accessible by: All users (read/write)
  - Purpose: Shared documents, announcements, general files
  - Permissions: Full access for all authenticated users

- **Private Network Drive**
  - Accessible by: Teachers only (read/write)
  - Purpose: Teacher-specific files, student data, private notes
  - Permissions: Teachers group only, Principal excluded from content

### 3. Website Management

- **Content Management**
  - Admin staff can update announcements
  - Admin staff can update events
  - Principal can update announcements
  - Principal can update events
  - Web designer has full access (except private drive content)

- **Website Features:**
  - Announcements section
  - Events calendar
  - General information

### 4. Student Management System

- **Features Required:**
  - Attendance tracking
  - Progress tracking
  - Course management
  - Student overview (per teacher)
  - Note-taking capabilities

### 5. Privacy & Security

- **Data Protection**
  - Compliance with privacy laws
  - Sensitive data protection
  - Access control based on roles
  - Audit logging (implied requirement)

- **Access Control:**
  - Role-based access control (RBAC)
  - Principle of least privilege
  - Private data isolation

---

## Technical Constraints

### Software Requirements

- **Server OS:** Windows Server 2025
- **Database:** Open-source (MySQL/MariaDB/PostgreSQL)
- **Web Server:** Open-source (Apache/Nginx)
- **Firewall:** pfSense or IPfire
- **Client OS:** Windows 11 (majority), Linux (1 user)

### Hardware Considerations

- **Fixed PCs:** Multiple workstations
- **Network Infrastructure:** Required for domain environment
- **Printers:** 1 network printer + 1 local printer for Principal

### Integration Requirements

- **Linux Client Integration:**
  - Must authenticate against Windows AD
  - Must access Windows file shares
  - Must use network resources
  - Solutions: Samba, SSSD, or LDAP integration

---

## Non-Functional Requirements

### 1. Security

- Secure authentication
- Encrypted data transmission
- Access control enforcement
- Privacy law compliance
- Regular security updates

### 2. Usability

- Easy login process
- Familiar Windows environment
- Consistent user experience across PCs
- Intuitive file access

### 3. Reliability

- High availability for critical services
- Backup and recovery procedures
- System monitoring

### 4. Performance

- Fast login times
- Quick file access
- Responsive web interface
- Efficient profile synchronization

### 5. Maintainability

- Centralized management
- Easy user account management
- Simple permission changes
- Documentation required

---

## Assumptions & Notes

### Assumptions

1. Network infrastructure is in place or will be provided
2. Hardware (servers, workstations, printers) will be available
3. Internet connectivity for updates and external resources
4. Backup solution will be implemented (implied requirement)
5. Antivirus/security software will be installed

### Notes

- Project has high urgency
- Infrastructure delivery timing is critical
- Must prevent use of personal systems (Excel, personal agendas)
- Privacy law compliance is mandatory
- One teacher specifically wants Linux client

---

## Deliverables Expected

### Documentation

1. Network architecture diagram
2. Active Directory structure design
3. User and group structure
4. File server permissions matrix
5. Security configuration documentation
6. User guides for each role
7. Administrator documentation
8. Installation and configuration guides

### Technical Implementation

1. Working Windows Server 2025 with AD
2. Configured user accounts and groups
3. File server with proper permissions
4. Web server and database running
5. Firewall configured
6. All clients joined to domain
7. Linux client integrated
8. Printers configured and working

---

## Questions to Clarify (Future Requirements)

1. Specific database schema requirements?
2. Website functionality details?
3. Student management system specifications?
4. Backup and disaster recovery requirements?
5. Network topology details?
6. IP addressing scheme?
7. Domain name requirements?
8. Specific software versions?
9. Testing and acceptance criteria?
10. Timeline and milestones?

