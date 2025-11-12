# User Access Matrix - School Management System

## User Roles Overview

| Role | Count | Primary Function |
|------|-------|------------------|
| Teachers | 7 | Teaching, attendance, progress tracking |
| Admin Staff | 2 | Administrative tasks, website content |
| Principal | 1 | School leadership, website content |
| Web Designer | 1 | Website design and maintenance |

**Total Users: 11**

---

## Detailed Access Matrix

### Teachers (7 users)

| Resource | Access Level | Notes |
|---------|-------------|-------|
| **General Network Drive** | Read/Write | Shared documents, announcements |
| **Private Network Drive** | Read/Write | Teacher-specific files, student data |
| **Student Management System** | Full Access | Their own students only |
| **Attendance System** | Read/Write | Enter and view attendance |
| **Progress Tracking** | Read/Write | Enter and view student progress |
| **Course Management** | Read/Write | Manage courses |
| **Appointments/Calendar** | Read/Write | Personal appointments |
| **Note-taking** | Read/Write | Personal notes |
| **Website - Announcements** | Read Only | View announcements |
| **Website - Events** | Read Only | View events |
| **Network Printer** | Print | Shared network printer |
| **Roaming Profile** | Enabled | Desktop follows user |
| **Any PC Login** | Yes | Can log in to any fixed PC |

---

### Admin Staff (2 users)

| Resource | Access Level | Notes |
|---------|-------------|-------|
| **General Network Drive** | Read/Write | Administrative documents |
| **Private Network Drive** | No Access | Teachers only |
| **Student Management System** | No Access | Not specified |
| **Website - Announcements** | Read/Write | Can create/edit announcements |
| **Website - Events** | Read/Write | Can create/edit events |
| **Website - Other Content** | Read Only | Limited to announcements/events |
| **Network Printer** | Print | Shared network printer |
| **Roaming Profile** | Enabled | Desktop follows user |
| **Any PC Login** | Yes | Can log in to any fixed PC |

---

### Principal (1 user)

| Resource | Access Level | Notes |
|---------|-------------|-------|
| **General Network Drive** | Read/Write | Administrative documents |
| **Private Network Drive** | No Access | Teachers only (explicitly excluded) |
| **Student Management System** | Unknown | Not specified - likely read-only |
| **Website - Announcements** | Read/Write | Can create/edit announcements |
| **Website - Events** | Read/Write | Can create/edit events |
| **Website - Other Content** | Read Only | Limited to announcements/events |
| **Network Printer** | No Access | Has own printer |
| **Principal's Printer** | Print | Local printer, not shared |
| **Roaming Profile** | Enabled | Desktop follows user |
| **Any PC Login** | Yes | Can log in to any fixed PC |

---

### Web Designer (1 user - Part-time)

| Resource | Access Level | Notes |
|---------|-------------|-------|
| **General Network Drive** | Read Only | Can see structure, not private content |
| **Private Network Drive** | No Access | Explicitly excluded |
| **Website - Full Access** | Read/Write | Design, code, content management |
| **Database** | Read Only | Can see structure, not private data |
| **Network Printer** | Print | Shared network printer |
| **Roaming Profile** | Enabled | Desktop follows user |
| **Any PC Login** | Yes | Can log in to any fixed PC |

**Special Note:** Web designer can see "everything except contents of private drives" - this means:
- Can see file structure
- Can see website code/database structure
- Cannot read content of private teacher files
- Full website development access

---

## Group Structure (Active Directory)

### Recommended AD Groups

```
SchoolManagement (Domain)
├── Teachers
│   ├── Teacher1
│   ├── Teacher2
│   ├── Teacher3
│   ├── Teacher4
│   ├── Teacher5
│   ├── Teacher6
│   └── Teacher7
├── Administration
│   ├── Admin1
│   └── Admin2
├── Principal
│   └── Principal1
└── WebDesigner
    └── WebDesigner1
```

### Security Groups for Permissions

```
SG_Teachers          (Teachers group)
SG_Administration    (Admin staff group)
SG_Principal         (Principal group)
SG_WebDesigner       (Web designer group)
SG_AllStaff          (All authenticated users)
SG_WebsiteEditors    (Admin + Principal)
```

---

## File Server Permissions Matrix

### General Network Drive (`\\server\General`)

| Group | NTFS Permission | Share Permission | Notes |
|-------|----------------|------------------|-------|
| SG_AllStaff | Modify | Change | All authenticated users |
| Authenticated Users | Modify | Change | Default for all |

### Private Network Drive (`\\server\Private`)

| Group | NTFS Permission | Share Permission | Notes |
|-------|----------------|------------------|-------|
| SG_Teachers | Full Control | Change | Teachers only |
| SG_Principal | List Folder Contents | Read | Can see structure, not content |
| SG_WebDesigner | List Folder Contents | Read | Can see structure, not content |
| SG_Administration | No Access | No Access | Explicitly denied |

---

## Website Access Matrix

### Announcements Section

| Role | Create | Edit | Delete | View |
|------|--------|------|--------|------|
| Teachers | No | No | No | Yes |
| Admin Staff | Yes | Yes | Yes | Yes |
| Principal | Yes | Yes | Yes | Yes |
| Web Designer | Yes | Yes | Yes | Yes |

### Events Section

| Role | Create | Edit | Delete | View |
|------|--------|------|--------|------|
| Teachers | No | No | No | Yes |
| Admin Staff | Yes | Yes | Yes | Yes |
| Principal | Yes | Yes | Yes | Yes |
| Web Designer | Yes | Yes | Yes | Yes |

### Website Code/Design

| Role | Access Level |
|------|-------------|
| Teachers | No Access |
| Admin Staff | No Access |
| Principal | No Access |
| Web Designer | Full Access |

---

## Printer Access Matrix

| Resource | Teachers | Admin | Principal | Web Designer |
|----------|----------|-------|-----------|--------------|
| Network Printer | Yes | Yes | No | Yes |
| Principal's Printer | No | No | Yes (local) | No |

---

## Summary Table

| Feature | Teachers | Admin | Principal | Web Designer |
|---------|----------|-------|-----------|--------------|
| General Drive (RW) | ✓ | ✓ | ✓ | R |
| Private Drive (RW) | ✓ | ✗ | ✗ | ✗ |
| Website View | ✓ | ✓ | ✓ | ✓ |
| Website Edit (Announcements) | ✗ | ✓ | ✓ | ✓ |
| Website Edit (Events) | ✗ | ✓ | ✓ | ✓ |
| Website Code Access | ✗ | ✗ | ✗ | ✓ |
| Student Management | ✓ | ✗ | ? | ✗ |
| Network Printer | ✓ | ✓ | ✗ | ✓ |
| Roaming Profile | ✓ | ✓ | ✓ | ✓ |
| Any PC Login | ✓ | ✓ | ✓ | ✓ |

**Legend:**
- ✓ = Full Access
- R = Read Only
- ✗ = No Access
- ? = Not Specified

