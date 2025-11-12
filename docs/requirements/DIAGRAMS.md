# System Diagrams - School Management System
## Preliminary Sketches (Based on Initial Requirements)

---

## Network Topology Diagram

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
        │              │              │
        │              │              │
   [Windows]      [Network]    [Workstations]
   Server VM      Devices      [Printers]
   192.168.1.10
        │
        │
   ┌────┴────┐
   │         │
[Services] [Domain]
   │         │
   ├─ AD DC  │
   ├─ DNS    │
   ├─ DHCP   │
   ├─ File   │
   ├─ Web    │
   └─ DB     │
```

### Network Details:

**Firewall (pfSense/IPfire):**
- IP: 192.168.1.1
- Gateway for all devices
- NAT, Firewall rules, DHCP (optional)

**Proxmox Host:**
- Management IP: 192.168.1.5 (example)
- Hosts Windows Server VM

**Windows Server VM:**
- IP: 192.168.1.10
- Domain Controller
- All server roles

**Client Network:**
- Range: 192.168.1.100-199
- Windows 11 PCs: 192.168.1.100-149
- Linux Client: 192.168.1.150
- Network Printer: 192.168.1.200
- Principal's Printer: Local (not networked)

**VLAN Structure (if needed):**
- VLAN 10: Management (Servers)
- VLAN 20: Clients
- VLAN 30: Guest/Public (if needed)

---

## Entity Relationship Diagram (ERD) - Preliminary Sketch

```
┌─────────────────┐
│     Users       │
├─────────────────┤
│ user_id (PK)    │
│ username        │
│ password_hash   │
│ email           │
│ role            │
│ created_at      │
└────────┬────────┘
         │
         │ 1:N
         │
┌────────▼────────┐         ┌──────────────┐
│   Teachers     │         │   Students   │
├────────────────┤         ├──────────────┤
│ teacher_id(PK) │         │ student_id   │
│ user_id (FK)   │◄───────┤ (PK)         │
│ first_name     │    N:N  │ first_name   │
│ last_name      │         │ last_name    │
│ employee_id    │         │ date_of_birth│
└────────────────┘         │ class        │
                            └──────────────┘
         │                          │
         │                          │ 1:N
         │                          │
┌────────▼────────┐         ┌───────▼────────┐
│   Courses      │         │  Attendance    │
├────────────────┤         ├────────────────┤
│ course_id (PK) │         │ attendance_id  │
│ teacher_id(FK) │         │ (PK)           │
│ course_name    │         │ student_id(FK) │
│ description    │         │ date           │
│ start_date     │         │ status         │
│ end_date       │         │ notes          │
└────────────────┘         └────────────────┘
         │
         │ 1:N
         │
┌────────▼────────┐
│    Progress    │
├────────────────┤
│ progress_id(PK)│
│ student_id(FK) │
│ course_id(FK)  │
│ assessment_date│
│ grade          │
│ comments       │
└────────────────┘

┌─────────────────┐
│ Announcements   │
├─────────────────┤
│ announcement_id │
│ (PK)            │
│ title           │
│ content         │
│ author_id (FK)  │
│ created_at      │
│ updated_at      │
│ is_published    │
└─────────────────┘

┌─────────────────┐
│     Events      │
├─────────────────┤
│ event_id (PK)   │
│ title           │
│ description     │
│ event_date      │
│ event_time      │
│ location        │
│ author_id (FK)  │
│ created_at      │
│ updated_at      │
└─────────────────┘
```

### Entity Descriptions:

**Users:**
- Central authentication table
- Links to role-based tables (Teachers, Admin, Principal, WebDesigner)

**Teachers:**
- Teacher-specific information
- Links to courses and students

**Students:**
- Student information
- Many-to-many with Teachers (through Courses)

**Courses:**
- Course information
- Links Teachers to Students

**Attendance:**
- Daily attendance records
- Links Students to dates

**Progress:**
- Student progress/assessments
- Links Students to Courses

**Announcements:**
- Website announcements
- Created by Admin/Principal/WebDesigner

**Events:**
- School events calendar
- Created by Admin/Principal/WebDesigner

---

## Data Flow Diagram (DFD) - Context Diagram (Level 0)

```
                    ┌─────────────────────┐
                    │   School Management │
                    │      System         │
                    └─────────────────────┘
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
         │                   │                   │
    ┌────▼────┐         ┌────▼────┐        ┌────▼────┐
    │Teachers │         │  Admin  │        │Principal│
    │         │         │  Staff  │        │         │
    └────┬────┘         └────┬────┘        └────┬────┘
         │                   │                   │
         │ Student Data      │ Website Data      │ Website Data
         │ Attendance        │ Announcements     │ Announcements
         │ Progress          │ Events            │ Events
         │                   │                   │
    ┌────▼───────────────────▼───────────────────▼────┐
    │                                                  │
    │            ┌─────────────────────┐              │
    │            │   School Management │              │
    │            │      System         │              │
    │            └─────────────────────┘              │
    │                                                  │
    └──────────────────────────────────────────────────┘
         │
         │
    ┌────▼────┐
    │Web      │
    │Designer │
    └────┬────┘
         │
         │ Website Code
         │ Database Schema
```

### External Entities:
- **Teachers** - Input: Student data, attendance, progress
- **Admin Staff** - Input: Website announcements, events
- **Principal** - Input: Website announcements, events
- **Web Designer** - Input: Website code, database schema

### System:
- **School Management System** - Processes all inputs, manages data

---

## DFD Level 1 - Main Processes

```
┌─────────────────────────────────────────────────────────────┐
│              School Management System                       │
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │  1.0         │    │  2.0         │    │  3.0         │ │
│  │ Manage      │    │ Manage       │    │ Manage       │ │
│  │ Students    │───▶│ Website      │───▶│ Files       │ │
│  │             │    │ Content      │    │             │ │
│  └──────┬───────┘    └──────┬───────┘    └──────┬──────┘ │
│         │                   │                   │         │
│         │                   │                   │         │
│  ┌──────▼───────┐    ┌──────▼───────┐    ┌──────▼───────┐ │
│  │  4.0         │    │  5.0         │    │  6.0         │ │
│  │ Authenticate│    │ Manage       │    │ Generate     │ │
│  │ Users       │    │ Reports      │    │ Reports      │ │
│  └─────────────┘    └──────────────┘    └──────────────┘ │
│                                                             │
└─────────────────────────────────────────────────────────────┘
         │                   │                   │
         │                   │                   │
    ┌────▼────┐         ┌────▼────┐        ┌────▼────┐
    │Students │         │Website  │        │Files    │
    │Database │         │Database │        │Storage  │
    └─────────┘         └─────────┘        └─────────┘
```

### Processes:

**1.0 Manage Students**
- Input: Student data, attendance, progress
- Output: Student records, attendance records, progress records
- Stores: Students Database

**2.0 Manage Website Content**
- Input: Announcements, events
- Output: Published announcements, events
- Stores: Website Database

**3.0 Manage Files**
- Input: File uploads, file requests
- Output: File access, file storage
- Stores: File Storage

**4.0 Authenticate Users**
- Input: Login credentials
- Output: Authentication status, user roles
- Uses: Active Directory

**5.0 Manage Reports**
- Input: Report requests
- Output: Generated reports
- Uses: Students Database, Website Database

**6.0 Generate Reports**
- Input: Data queries
- Output: Formatted reports
- Uses: Multiple data stores

---

## DFD Level 2 - Process 1.0 (Manage Students) Decomposition

```
┌─────────────────────────────────────────────────────────────┐
│              1.0 Manage Students                            │
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │  1.1         │    │  1.2         │    │  1.3         │ │
│  │ Record      │    │ Record       │    │ Record       │ │
│  │ Attendance  │───▶│ Progress    │───▶│ Student Info │ │
│  └──────┬───────┘    └──────┬───────┘    └──────┬──────┘ │
│         │                   │                   │         │
│         │                   │                   │         │
│  ┌──────▼───────┐    ┌──────▼───────┐    ┌──────▼───────┐ │
│  │  1.4         │    │  1.5         │    │  1.6         │ │
│  │ Assign      │    │ Generate     │    │ Manage       │ │
│  │ Courses     │    │ Reports      │    │ Notes        │ │
│  └─────────────┘    └──────────────┘    └──────────────┘ │
│                                                             │
└─────────────────────────────────────────────────────────────┘
         │                   │                   │
         │                   │                   │
    ┌────▼────┐         ┌────▼────┐        ┌────▼────┐
    │Students │         │Attendance│       │Progress │
    │Database │         │Database  │       │Database │
    └─────────┘         └─────────┘        └─────────┘
```

### Sub-processes:

**1.1 Record Attendance**
- Input: Student ID, date, status
- Output: Attendance record
- Stores: Attendance Database

**1.2 Record Progress**
- Input: Student ID, course ID, grade, comments
- Output: Progress record
- Stores: Progress Database

**1.3 Record Student Info**
- Input: Student details
- Output: Student record
- Stores: Students Database

**1.4 Assign Courses**
- Input: Student ID, course ID
- Output: Course assignment
- Stores: Students Database, Courses Database

**1.5 Generate Reports**
- Input: Report parameters
- Output: Attendance reports, progress reports
- Uses: Multiple databases

**1.6 Manage Notes**
- Input: Notes, student ID
- Output: Saved notes
- Stores: Students Database

---

## DFD Level 2 - Process 2.0 (Manage Website Content) Decomposition

```
┌─────────────────────────────────────────────────────────────┐
│              2.0 Manage Website Content                     │
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │  2.1         │    │  2.2         │    │  2.3         │ │
│  │ Create      │    │ Edit        │    │ Publish      │ │
│  │ Announcement│───▶│ Content     │───▶│ Content     │ │
│  └──────┬───────┘    └──────┬───────┘    └──────┬──────┘ │
│         │                   │                   │         │
│         │                   │                   │         │
│  ┌──────▼───────┐    ┌──────▼───────┐    ┌──────▼───────┐ │
│  │  2.4         │    │  2.5         │    │  2.6         │ │
│  │ Manage      │    │ Schedule     │    │ Archive      │ │
│  │ Events      │    │ Events       │    │ Old Content  │ │
│  └─────────────┘    └──────────────┘    └──────────────┘ │
│                                                             │
└─────────────────────────────────────────────────────────────┘
         │                   │                   │
         │                   │                   │
    ┌────▼────┐         ┌────▼────┐        ┌────▼────┐
    │Announce │         │ Events  │        │Archive  │
    │Database │         │Database │        │Database │
    └─────────┘         └─────────┘        └─────────┘
```

### Sub-processes:

**2.1 Create Announcement**
- Input: Title, content, author
- Output: Draft announcement
- Stores: Announcements Database

**2.2 Edit Content**
- Input: Content ID, changes
- Output: Updated content
- Stores: Announcements/Events Database

**2.3 Publish Content**
- Input: Content ID
- Output: Published content on website
- Updates: Website frontend

**2.4 Manage Events**
- Input: Event details
- Output: Event record
- Stores: Events Database

**2.5 Schedule Events**
- Input: Event date, time, location
- Output: Scheduled event
- Stores: Events Database

**2.6 Archive Old Content**
- Input: Content ID, archive date
- Output: Archived content
- Moves: To Archive Database

---

## DFD Level 3 - Process 1.1 (Record Attendance) Decomposition

```
┌─────────────────────────────────────────────────────────────┐
│              1.1 Record Attendance                          │
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │  1.1.1       │    │  1.1.2       │    │  1.1.3       │ │
│  │ Validate    │    │ Check        │    │ Save        │ │
│  │ Student ID  │───▶│ Date         │───▶│ Attendance  │ │
│  └──────┬───────┘    └──────┬───────┘    └──────┬──────┘ │
│         │                   │                   │         │
│         │                   │                   │         │
│  ┌──────▼───────┐    ┌──────▼───────┐    ┌──────▼───────┐ │
│  │  1.1.4       │    │  1.1.5       │    │  1.1.6       │ │
│  │ Check       │    │ Generate     │    │ Notify       │ │
│  │ Duplicate   │    │ Confirmation │    │ Absences     │ │
│  └─────────────┘    └──────────────┘    └──────────────┘ │
│                                                             │
└─────────────────────────────────────────────────────────────┘
         │                   │                   │
         │                   │                   │
    ┌────▼────┐         ┌────▼────┐        ┌────▼────┐
    │Students │         │Attendance│       │Email    │
    │Database │         │Database  │       │System   │
    └─────────┘         └─────────┘        └─────────┘
```

### Sub-processes:

**1.1.1 Validate Student ID**
- Input: Student ID
- Output: Validated student
- Checks: Students Database

**1.1.2 Check Date**
- Input: Date
- Output: Valid date
- Validates: Date format, school calendar

**1.1.3 Save Attendance**
- Input: Validated data
- Output: Attendance record
- Stores: Attendance Database

**1.1.4 Check Duplicate**
- Input: Student ID, date
- Output: Duplicate status
- Checks: Attendance Database

**1.1.5 Generate Confirmation**
- Input: Attendance record
- Output: Confirmation message
- Displays: To user

**1.1.6 Notify Absences**
- Input: Absence records
- Output: Notification
- Sends: Email/Alert

---

## Data Store Descriptions

### D1: Students Database
- Stores: Student information, personal data
- Used by: Processes 1.0, 1.1, 1.3, 1.4

### D2: Attendance Database
- Stores: Daily attendance records
- Used by: Processes 1.0, 1.1, 1.5

### D3: Progress Database
- Stores: Student progress, grades, assessments
- Used by: Processes 1.0, 1.2, 1.5

### D4: Courses Database
- Stores: Course information, assignments
- Used by: Processes 1.0, 1.4

### D5: Announcements Database
- Stores: Website announcements
- Used by: Processes 2.0, 2.1, 2.2, 2.3

### D6: Events Database
- Stores: School events, calendar
- Used by: Processes 2.0, 2.4, 2.5

### D7: Files Storage
- Stores: Network files, documents
- Used by: Process 3.0

### D8: Active Directory
- Stores: User accounts, authentication
- Used by: Process 4.0

---

## Notes

- **These are preliminary sketches** based on initial requirements
- **Will be refined** as full requirements are received
- **ERD** shows main entities and relationships
- **DFDs** show data flow through the system
- **Level 3** shows detailed process breakdown
- **All diagrams** should be recreated in proper tools (Visio, Draw.io, etc.)

---

## Next Steps

1. Validate with client requirements
2. Add missing entities/processes
3. Refine relationships
4. Create proper diagrams in Visio/Draw.io
5. Add data dictionary
6. Add process specifications

