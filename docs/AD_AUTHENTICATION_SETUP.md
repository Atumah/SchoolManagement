# Active Directory Authentication Setup

This guide explains how to set up AD authentication for the web application.

## Overview

The web application now supports **hybrid authentication**:
- **Primary**: Active Directory (LDAP) authentication
- **Fallback**: Database authentication (for admin account or if AD is unavailable)

## Benefits

✅ **Single Sign-On (SSO)** - Users log in with their AD credentials  
✅ **Centralized Management** - Manage users in one place (AD)  
✅ **Consistent Security** - Leverages AD security policies  
✅ **Automatic Sync** - AD users automatically synced to database  

## Prerequisites

1. ✅ Active Directory Domain Services installed
2. ✅ LDAP ports open in firewall (Step 2.11)
3. ✅ PHP LDAP extension enabled

## Step 1: Enable PHP LDAP Extension

**On Windows Server:**

1. Open `C:\PHP\php.ini`
2. Find the line: `;extension=ldap`
3. Remove the semicolon: `extension=ldap`
4. Save the file
5. Restart Apache:
   ```powershell
   Restart-Service Apache2.4
   ```

6. Verify LDAP is enabled:
   ```powershell
   php -m | findstr ldap
   ```
   Should output: `ldap`

## Step 2: Sync AD Users to Database

Run the sync script to import all AD users:

```powershell
cd C:\inetpub\wwwroot\schoolmanagement
php scripts\sync_ad_users.php
```

This will:
- Connect to AD
- Find all active users
- Create/update database records
- Map AD groups to web app roles

**Expected output:**
```
=== Syncing AD Users to Database ===

Connecting to AD server: 192.168.1.10...
Binding to AD...
Connected successfully!

Searching for users in AD...
Found 11 users in AD

  ✓ Created: teacher1 (Teacher)
  ✓ Created: teacher2 (Teacher)
  ...
  ✓ Updated: admin1 (Admin)

=== Sync Complete ===
Created: 11
Updated: 0
Errors: 0
```

## Step 3: Test AD Authentication

1. Open the website: `http://morningstar.local`
2. Try logging in with AD credentials:
   - **Email**: `teacher1@morningstar.local`
   - **Password**: `ChangeMe123!` (or current AD password)

3. The login should work using AD authentication!

## How It Works

### Authentication Flow

1. User enters email/username and password
2. System tries **AD authentication first**:
   - Connects to LDAP server
   - Binds with user credentials
   - Retrieves user info and groups
3. If AD auth succeeds:
   - Syncs user to database (if needed)
   - Completes login
4. If AD auth fails:
   - Falls back to **database authentication**
   - Checks database password hash

### Role Mapping

AD groups are mapped to web app roles:

| AD Group | Web App Role |
|----------|-------------|
| Teachers | Teacher |
| Administration | Admin |
| Principal | Principal |
| WebDesigner | Web Designer |
| (default) | Student |

## Configuration

AD settings are in `public/includes/ad_auth.php`:

```php
$adServer = '192.168.1.10';      // Windows Server IP
$adDomain = 'morningstar.local'; // Domain name
$adBaseDN = 'DC=morningstar,DC=local'; // Base DN
```

## Troubleshooting

### "LDAP extension not available"
- Enable `extension=ldap` in `php.ini`
- Restart Apache

### "Failed to connect to AD server"
- Check firewall rules (Step 2.11)
- Verify AD server is running
- Check IP address is correct

### "Failed to bind to AD"
- Verify user credentials
- Check user account is enabled in AD
- Try UPN format: `username@morningstar.local`

### Users not syncing
- Run sync script manually
- Check AD user has email address set
- Verify AD groups are correct

## Maintenance

### Re-sync Users

Run sync script periodically or when AD users change:

```powershell
php scripts\sync_ad_users.php
```

### Add New AD User

1. Create user in AD (using scripts or manually)
2. Add to appropriate AD group
3. Run sync script:
   ```powershell
   php scripts\sync_ad_users.php
   ```
4. User can immediately log in with AD credentials!

## Security Notes

- ✅ Passwords never stored in database (AD handles authentication)
- ✅ AD security policies apply (password complexity, expiration, etc.)
- ✅ Database stores user data and relationships only
- ✅ Roles synced from AD groups automatically

## Migration from Database Auth

If you have existing database users:

1. **Run sync script** - This will update existing users
2. **Database passwords cleared** - AD handles authentication now
3. **Admin account** - Can still use database auth as fallback
4. **Test login** - All users should use AD credentials

---

**Next Steps:**
- Complete Step 2.11 (Windows Firewall) if not done
- Enable PHP LDAP extension
- Run sync script
- Test login with AD credentials

